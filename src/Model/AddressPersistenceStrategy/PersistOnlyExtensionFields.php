<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionCollection;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Service\EnderecoExtensionEntityUpdater;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\AcrisCustomField;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Service\CustomerAddressEntityUpdater;
use Endereco\Shopware6Client\Service\PluginStatusService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class PersistOnlyExtensionFields implements CustomerAddressPersistenceStrategy
{
    /** @var EntityRepository<EnderecoCustomerAddressExtensionCollection> */
    private EntityRepository $extensionRepository;

    private Context $context;
    private EnderecoExtensionEntityUpdater $extensionEntityUpdater;

    /** @var EntityRepository<CustomerAddressCollection> */
    private EntityRepository $addressRepository;

    private CustomerAddressEntityUpdater $entityUpdater;

    private PluginStatusService $pluginStatusService;

    /**
     * @param EntityRepository<EnderecoCustomerAddressExtensionCollection> $customerAddressExtensionRepository
     * @param EntityRepository<CustomerAddressCollection> $customerAddressRepository
     */
    public function __construct(
        EntityRepository $customerAddressExtensionRepository,
        Context $context,
        EnderecoExtensionEntityUpdater $extensionEntityUpdater,
        EntityRepository $customerAddressRepository,
        CustomerAddressEntityUpdater $entityUpdater,
        PluginStatusService $pluginStatusService
    ) {
        $this->extensionRepository = $customerAddressExtensionRepository;
        $this->context = $context;
        $this->extensionEntityUpdater = $extensionEntityUpdater;
        $this->addressRepository = $customerAddressRepository;
        $this->entityUpdater = $entityUpdater;
        $this->pluginStatusService = $pluginStatusService;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $normalizedStreetFull
     * @param string|null $normalizedAdditionalInfo
     * @param string $streetName
     * @param string $buildingNumber
     * @param CustomerAddressDTO $customerAddressDTO
     * @return void
     */
    public function execute(
        string $normalizedStreetFull,
        ?string $normalizedAdditionalInfo,
        string $streetName,
        string $buildingNumber,
        CustomerAddressDTO $customerAddressDTO
    ): void {
        $addressEntity = $customerAddressDTO->getCustomerAddress();
        $addressExtension = $customerAddressDTO->getEnderecoCustomerAddressExtension();
        if ($addressExtension === null) {
            throw new \RuntimeException('Address extension cannot be null');
        }

        if ($addressEntity === null) {
            throw new \RuntimeException('Address entity cannot be null');
        }

        $this->maybeUpdateCustomFields(
            $streetName,
            $buildingNumber,
            $addressEntity
        );

        $this->maybeUpdateExtension(
            $streetName,
            $buildingNumber,
            $addressExtension
        );
    }

    /**
     * Updates the address extension fields if values have changed
     *
     * @param string $streetName Street name
     * @param string $buildingNumber Building number
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension Address extension entity
     *
     * @return void
     */
    private function maybeUpdateExtension(
        string $streetName,
        string $buildingNumber,
        EnderecoCustomerAddressExtensionEntity $addressExtension
    ): void {
        if (!$this->areExtensionValuesChanged($streetName, $buildingNumber, $addressExtension)) {
            return;
        }

        $extensionData = (new EnderecoExtensionData())
            ->setAddressId($addressExtension->getAddressId())
            ->setStreet($streetName)
            ->setHouseNumber($buildingNumber);

        $this->extensionRepository->update([$extensionData->toArray()], $this->context);

        // Update in memory using normalized payload data
        $this->extensionEntityUpdater->updateFromPayload($extensionData, $addressExtension);
    }

    /**
     * Checks if the extension entity values differ from the provided values
     *
     * @param string $streetName The street name to compare
     * @param string $houseNumber The house number to compare
     * @param EnderecoCustomerAddressExtensionEntity $extension The extension entity to check against
     *
     * @return bool True if any values have changed, false otherwise
     */
    private function areExtensionValuesChanged(
        string $streetName,
        string $houseNumber,
        EnderecoCustomerAddressExtensionEntity $extension
    ): bool {
        if ($extension->getStreet() !== $streetName) {
            return true;
        }

        if ($extension->getHouseNumber() !== $houseNumber) {
            return true;
        }

        return false;
    }

    /**
     * Updates the custom fields in the payload if ACRIS values have changed
     *
     * @param string $streetName Street name
     * @param string $buildingNumber Building number
     * @param CustomerAddressEntity $addressEntity Address entity to update
     *
     * @return void
     */
    private function maybeUpdateCustomFields(
        string $streetName,
        string $buildingNumber,
        CustomerAddressEntity $addressEntity
    ): void {
        if (!$this->pluginStatusService->isAcrisStreetActive()) {
            return;
        }

        if (!$this->areCustomFieldsChanged($streetName, $buildingNumber, $addressEntity)) {
            return;
        }

        $currentCustomFields = $addressEntity->getCustomFields() ?? [];
        $newCustomFields = array_merge($currentCustomFields, [
            AcrisCustomField::STREET => $streetName,
            AcrisCustomField::HOUSE_NUMBER => $buildingNumber,
        ]);

        $payload = new CustomerAddressUpdatePayload($addressEntity->getId(), $addressEntity->getCustomerId());
        $payload->setCustomFields($newCustomFields);

        $this->addressRepository->update([$payload->toArray()], $this->context);
        $this->entityUpdater->updateFromPayload($payload, $addressEntity);
    }

    /**
     * Checks if ACRIS custom fields have changed
     *
     * @param string $streetName New street name
     * @param string $buildingNumber New building number
     * @param CustomerAddressEntity $addressEntity The address entity to check against
     *
     * @return bool True if any custom values have changed
     */
    private function areCustomFieldsChanged(
        string $streetName,
        string $buildingNumber,
        CustomerAddressEntity $addressEntity
    ): bool {
        $currentCustomFields = $addressEntity->getCustomFields() ?? [];
        $acrisStreet = $currentCustomFields[AcrisCustomField::STREET] ?? null;
        $acrisHouseNo = $currentCustomFields[AcrisCustomField::HOUSE_NUMBER] ?? null;

        return $acrisStreet !== $streetName || $acrisHouseNo !== $buildingNumber;
    }
}
