<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\FailedAddressCheckResult;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Class AmsStatusIsSetOperation
 *
 * Ensures that an AMS status is set for a given CustomerAddressEntity if needed. It checks whether
 * the extension requires validation, and if so, validates the address using the Endereco service.
 */
final class Validate implements Operation
{
    private const MAX_VALIDATION_ATTEMPTS = 3;

    private EnderecoService $enderecoService;

    private AddressCheckerInterface $addressChecker;

    private IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker;
    private ProcessContextService $processContext;

    /**
     * AmsStatusIsSetOperation constructor.
     *
     * @param IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker Checker service
     * @param AddressCheckerInterface $addressChecker Service (with cache) for interacting
     * with the Endereco API for the address check
     * @param EnderecoService $enderecoService Service for interacting with the Endereco API
     */
    public function __construct(
        IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker,
        AddressCheckerInterface $addressChecker,
        EnderecoService $enderecoService,
        ProcessContextService $processContext,
    ) {
        $this->enderecoService = $enderecoService;
        $this->addressChecker = $addressChecker;
        $this->isAmsRequestPayloadIsUpToDateChecker = $isAmsRequestPayloadIsUpToDateChecker;
        $this->processContext = $processContext;
    }

    public static function getPriority(): int
    {
        return -50;
    }

    /**
     * Determines whether this insurance applies to the given address
     *
     * @param Workspace $workspace Customer address workspace
     * @return bool Always returns true to maintain current behavior
     */
    public function applies(Workspace $workspace): bool
    {

        // Skip if workspace is marked to skip
        if ($workspace->shouldSkip()) {
            return false;
        }

        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();
        
        if ($addressEntity === null || $context === null) {
            return false;
        }

        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        // We don't support address validation outside of saleschannel yet.
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        if (is_null($salesChannelId) || !$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return false;
        }

        if (!$this->isValidationNeeded($addressExtension)) {
            return false;
        }

        // We check, if we are allowed to validate the address.
        if (!$this->canValidate($addressEntity, $salesChannelId)) {
            return false;
        }
        
        return true;
    }

    /**
     * Ensures that an AMS status is set for the given customer address entity.
     *
     * 1) Checks whether the address extension needs a new address check status.
     * 2) Determines if the address is eligible for validation based on configuration (existing customer or PayPal).
     * 3) If eligible, validates the address using the Endereco service.
     * 4) Applies the validation result to the address entity if successful.
     * 5) Caches the updated entity so others can reuse the validated data.
     *
     * @param Workspace $workspace   The customer address workspace
     *
     * @throws \RuntimeException If the address extension is not present on the entity
     */
    public function process(Workspace $workspace): void
    {
        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();
        
        if ($addressEntity === null || $context === null) {
            return;
        }
        
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        if (is_null($salesChannelId)) {
            return;
        }

        $attempts = 1;
        $sessionId = '';
        while (1) {
            if ($attempts >= self::MAX_VALIDATION_ATTEMPTS) {
                throw new \RuntimeException(
                    sprintf('Address validation exceeded maximum attempts (%d)', self::MAX_VALIDATION_ATTEMPTS)
                );
            }

            // Then we validate the address.
            $addressCheckResult = $this->addressChecker->checkAddress(
                $addressEntity,
                $context,
                $salesChannelId,
                $sessionId
            );

            // We dont throw exceptions, we just gracefully stop here. Maybe the API will be available later again.
            if ($addressCheckResult instanceof FailedAddressCheckResult) {
                return;
            }

            // Here we save the status codes and predictions. If it's an automatic correction, then we also save
            // the data from the correction to customer address entity and generate a new,
            // "virtual" address check result.
            $this->enderecoService->applyAddressCheckResult($addressCheckResult, $addressEntity, $context);

            $isRequestPayloadUpToDate = $this->isAmsRequestPayloadIsUpToDateChecker->checkIfCustomerAddressMetaIsUpToDate(
                $addressEntity,
                $addressExtension,
                $context
            );

            $sessionId = $addressCheckResult->getUsedSessionId();
            $attempts++;

            // If the signature is still valid after applying the results of address check, then
            if ($isRequestPayloadUpToDate) {
                break;
            }
        }

        // Count the validation for accounting if address check is complete or during import process.
        // For normal validation: address must be selected automatically, otherwise check will be resumed in frontend.
        // For import process: all checked addresses with sessions should be accounted for billing.
        if ($sessionId !== '') {
            $isImportProcess = $this->enderecoService->isImport;
            $amsStatus = $addressExtension->getAmsStatus();
            
            $shouldAccountForBilling = $isImportProcess || 
                ($amsStatus && strpos($amsStatus, 'address_selected_automatically') !== false);
            
            if ($shouldAccountForBilling) {
                $this->enderecoService->addAccountableSessionIdsToStorage([$sessionId]);
            }
        }
    }

    /**
     * Determines if the given customer address can be validated based on sales channel configuration.
     *
     * @param CustomerAddressEntity $addressEntity   The customer address entity to check
     * @param string                $salesChannelId  The ID of the sales channel
     *
     * @return bool True if validation is applicable, false otherwise
     */
    protected function canValidate(CustomerAddressEntity $addressEntity, string $salesChannelId): bool
    {

        // TODO: extract into configuration as iterable list of filters.
        $existingCustomerCheckIsRelevant =
            $this->enderecoService->isExistingAddressCheckFeatureEnabled($salesChannelId)
            && !$this->enderecoService->isAddressFromRemote($addressEntity)
            && !$this->enderecoService->isAddressRecent($addressEntity)
            && $this->processContext->isStorefront();

        $paypalExpressCheckoutCheckIsRelevant =
            $this->enderecoService->isPayPalCheckoutAddressCheckFeatureEnabled($salesChannelId)
            && $this->enderecoService->isAddressFromPayPal($addressEntity)
            && $this->processContext->isStorefront();

        // Determine if check for freshly imported/updated through import customer address is required
        $importFileCheckIsRelevant =
            $this->enderecoService->isImportExportCheckFeatureEnabled($salesChannelId)
            && $this->enderecoService->isImport;

        return $existingCustomerCheckIsRelevant || $paypalExpressCheckoutCheckIsRelevant || $importFileCheckIsRelevant;
    }

    /**
     * Determines if a new AMS status check is needed based on the current AMS status of the address extension.
     *
     * A check is needed if the AMS status is empty or matches the constant AMS_STATUS_NOT_CHECKED.
     *
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension The address extension entity holding AMS status
     *
     * @return bool True if a new AMS status check is required, false otherwise
     */
    public function isValidationNeeded(EnderecoCustomerAddressExtensionEntity $addressExtension): bool
    {
        $currentStatus = $addressExtension->getAmsStatus();

        $isEmpty = empty($currentStatus);
        $hasDefaultValue =  ($currentStatus === EnderecoCustomerAddressExtensionEntity::AMS_STATUS_NOT_CHECKED);

        $isCheckNeeded = $isEmpty || $hasDefaultValue;

        return $isCheckNeeded;
    }
}
