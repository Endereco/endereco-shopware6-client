<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\FailedAddressCheckResult;
use Endereco\Shopware6Client\Service\CustomerAddressCacheInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Class AmsStatusIsSetInsurance
 *
 * Ensures that an AMS status is set for a given CustomerAddressEntity if needed. It checks whether
 * the extension requires validation, and if so, validates the address using the Endereco service.
 */
final class AmsStatusIsSetInsurance implements IntegrityInsurance
{
    private EnderecoService $enderecoService;

    /**
     * AmsStatusIsSetInsurance constructor.
     *
     * @param EnderecoService $enderecoService Service for interacting with the Endereco API
     */
    public function __construct(
        EnderecoService $enderecoService
    ) {
        $this->enderecoService = $enderecoService;
    }

    public static function getPriority(): int
    {
        return -20;
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
     * @param CustomerAddressEntity $addressEntity   The customer address entity to validate
     * @param Context               $context         The current Shopware context
     *
     * @throws \RuntimeException If the address extension is not present on the entity
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        // We dont support address validation outside of saleschannel yet.
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        if (is_null($salesChannelId) || !$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return;
        }

        if (!$this->isValidationNeeded($addressExtension)) {
            return;
        }

        // We check, if we are allowed to validate the address.
        if (!$this->canValidate($addressEntity, $salesChannelId)) {
            return;
        }

        // Then we validate the address.
        $addressCheckResult = $this->enderecoService->checkAddress($addressEntity, $context, $salesChannelId);

        // We dont throw exceptions, we just gracefully stop here. Maybe the API will be available later again.
        if ($addressCheckResult instanceof FailedAddressCheckResult) {
            return;
        }

        // Here we save the status codes and predictions. If it's an automatic correction, then we also save
        // the data from the correction to customer address entity and generate a new,
        // "virtual" address check result.
        $this->enderecoService->applyAddressCheckResult($addressCheckResult, $addressEntity, $context);

        // Count the validation for accounting.
        if (!empty($addressCheckResult->getUsedSessionId())) {
            $this->enderecoService->addAccountableSessionIdsToStorage([$addressCheckResult->getUsedSessionId()]);
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
    protected function canValidate(CustomerAddressEntity $addressEntity, string $salesChannelId): bool {

        // TODO: extract into configuration as iterable list of filters.
        $existingCustomerCheckIsRelevant =
            $this->enderecoService->isExistingAddressCheckFeatureEnabled($salesChannelId)
            && !$this->enderecoService->isAddressFromRemote($addressEntity)
            && !$this->enderecoService->isAddressRecent($addressEntity);

        $paypalExpressCheckoutCheckIsRelevant =
            $this->enderecoService->isPayPalCheckoutAddressCheckFeatureEnabled($salesChannelId)
            && $this->enderecoService->isAddressFromPayPal($addressEntity);

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
