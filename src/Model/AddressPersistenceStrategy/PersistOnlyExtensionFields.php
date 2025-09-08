<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Service\EnderecoExtensionEntityUpdater;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;


final class PersistOnlyExtensionFields implements CustomerAddressPersistenceStrategy
{

    private EntityRepository $extensionRepository;
    private Context $context;
    private EnderecoExtensionEntityUpdater $extensionEntityUpdater;

    public function __construct(
        EntityRepository $customerAddressExtensionRepository,
        Context $context,
        EnderecoExtensionEntityUpdater $extensionEntityUpdater
    )
    {
        $this->extensionRepository = $customerAddressExtensionRepository;
        $this->context = $context;
        $this->extensionEntityUpdater = $extensionEntityUpdater;
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
        $addressExtension = $customerAddressDTO->getEnderecoCustomerAddressExtension();
        if ($addressExtension === null) {
            throw new \RuntimeException('Address extension cannot be null');
        }

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
}
