<?php

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

/**
 * Trait for handling persistence operations for customer address extension entities.
 *
 * Provides methods for building payloads, checking if values have changed,
 * and updating extension entity fields.
 */
trait CustomerAddressExtensionPersistenceStrategyTrait
{

    /**
     * Checks if the extension entity values differ from the provided values. We can use this information to skip
     * writing to the exception.
     *
     * @param string $streetName                          The street name to compare
     * @param string $houseNumber                         The house number to compare
     * @param EnderecoCustomerAddressExtensionEntity $extension  The extension entity to check against
     *
     * @return bool  True if any values have changed, false otherwise
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
     * Updates the fields of the extension entity with the provided values. We update them, to have correct display in
     * the frontend.
     *
     * @param string $streetName                          The new street name
     * @param string $houseNumber                         The new house number
     * @param EnderecoCustomerAddressExtensionEntity $extension  The extension entity to update
     *
     * @return void
     */
    private function updateExtensionEntityFields(
        string $streetName,
        string $houseNumber,
        EnderecoCustomerAddressExtensionEntity $extension
    ): void {
        $extension->setStreet($streetName);
        $extension->setHouseNumber($houseNumber);
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
        if(!$this->areExtensionValuesChanged($streetName, $buildingNumber, $addressExtension)) {
            return;
        }

        $extensionData = (new EnderecoExtensionData())
            ->setAddressId($addressExtension->getAddressId())
            ->setStreet($streetName)
            ->setHouseNumber($buildingNumber);
        
        $this->extensionRepository->update([$extensionData->toArray()], $this->context);

        $this->updateExtensionEntityFields($streetName, $buildingNumber, $addressExtension);
    }
}
