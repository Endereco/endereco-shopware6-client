<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Ensures AMS request payload matches current address data
 */
final class AssessValidationStatus implements Operation
{
    /** @var EnderecoService */
    private EnderecoService $enderecoService;

    /** @var IsAmsRequestPayloadIsUpToDateCheckerInterface */
    private IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker;

    /**
     * @param IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker Checker service
     * @param EnderecoService $enderecoService Endereco service
     */
    public function __construct(
        IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker,
        EnderecoService $enderecoService
    ) {
        $this->isAmsRequestPayloadIsUpToDateChecker = $isAmsRequestPayloadIsUpToDateChecker;
        $this->enderecoService = $enderecoService;
    }

    /**
     * Gets priority for insurance execution order
     *
     * @return int Priority value
     */
    public static function getPriority(): int
    {
        return -20;
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
        
        return true;
    }

    /**
     * Ensures that the meta data from address check are up to date the the customer address entity. If its not, it
     * would mean that the address was changed, so the meta data is disgarded (reset to default). This would potentially
     * trigger an address check in a later coming ensurance or at leas make sure we dont work with wrong meta data.
     *
     * @param Workspace $workspace Customer address workspace
     * @throws \RuntimeException If address extension not found
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function process(Workspace $workspace): void
    {
        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();

        if ($addressEntity === null || $context === null) {
            return;
        }

        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $isRequestPayloadUpToDate = $this->isAmsRequestPayloadIsUpToDateChecker->checkIfCustomerAddressMetaIsUpToDate(
            $addressEntity,
            $addressExtension,
            $context
        );

        if (!$isRequestPayloadUpToDate) {
            $this->enderecoService->resetCustomerAddressMetaData($addressEntity, $context);
        }

        if ($isRequestPayloadUpToDate && !$this->isValidationNeeded($addressExtension)) {
            $workspace->skipRemaining();
        }
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
