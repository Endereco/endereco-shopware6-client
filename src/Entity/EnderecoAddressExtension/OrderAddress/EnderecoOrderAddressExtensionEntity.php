<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\Model\AddressCheckData;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoOrderAddressExtensionEntity
 *
 * Represents an extension entity for order addresses in the Endereco address verification system.
 * Provides functionality for managing address verification data and status for order addresses.
 *
 * This class provides a custom entity to manage extensions for the Order Address object in the context
 * of the Endereco plugin.
 *
 * @phpstan-import-type AddressCheckDataData from AddressCheckData
 */
class EnderecoOrderAddressExtensionEntity extends EnderecoBaseAddressExtensionEntity
{
    /** @var ?OrderAddressEntity The associated order address entity. */
    protected ?OrderAddressEntity $address = null;

    /**
     * Gets the associated order address entity.
     *
     * @return OrderAddressEntity|null The associated order address entity or null if not set
     */
    public function getAddress(): ?OrderAddressEntity
    {
        return $this->address;
    }

    /**
     * Sets the associated order address entity.
     * Validates that the provided entity is of the correct type.
     *
     * @param OrderAddressEntity|null $address The order address entity to associate
     * @throws \InvalidArgumentException If the provided address is not an OrderAddressEntity
     */
    public function setAddress(?Entity $address): void
    {
        if (!$address instanceof OrderAddressEntity) {
            throw new \InvalidArgumentException('The address must be an instance of OrderAddressEntity.');
        }

        $this->address = $address;
    }

    /**
     * Builds data array for cart to order conversion.
     * Prepares entity data for use in order creation process.
     *
     * @return array<string, mixed> Array of entity data with system fields removed
     */
    public function buildCartToOrderConversionData(): array
    {
        $data = $this->getVars();
        unset($data['extensions']);
        unset($data['_uniqueIdentifier']);
        unset($data['versionId']);
        unset($data['translated']);
        unset($data['createdAt']);
        unset($data['updatedAt']);
        unset($data['address']);

        return $data;
    }

    /**
     * Builds data array for order custom fields.
     * Currently returns the same data as buildCartToOrderConversionData().
     *
     * @return array<string, mixed> Array of data for order custom fields
     */
    public function buildDataForOrderCustomField(): array
    {
        // The required data is currently the same. This might change in the future.
        return $this->buildCartToOrderConversionData();
    }

    /**
     * Syncs the data of this address extension entity with the data of another address extension entity.
     */
    public function sync(EnderecoBaseAddressExtensionEntity $addressExtensionToSyncFrom): void
    {
        if (!$addressExtensionToSyncFrom instanceof self) {
            throw new \InvalidArgumentException(
                'The address extension to sync from must be an instance of EnderecoOrderAddressExtensionEntity.'
            );
        }

        $this->setStreet($addressExtensionToSyncFrom->getStreet());
        $this->setHouseNumber($addressExtensionToSyncFrom->getHouseNumber());
        $this->setIsPayPalAddress($addressExtensionToSyncFrom->isPayPalAddress());
        $this->setAmsRequestPayload($addressExtensionToSyncFrom->getAmsRequestPayload());
        $this->setAmsStatus($addressExtensionToSyncFrom->getAmsStatus());
        $this->setAmsPredictions($addressExtensionToSyncFrom->getAmsPredictions());
        $this->setAmsTimestamp($addressExtensionToSyncFrom->getAmsTimestamp());
    }

    /**
     * Resets the data of this address extension entity to default values and creates a data array for persistence.
     *
     * @return array{
     *     addressId: string,
     *     amsRequestPayload: string,
     *     amsStatus: string,
     *     amsPredictions: array<array<string, string>>,
     *     amsTimestamp: int
     * }
     */
    public function resetAndCreateDataForPersistence(): array
    {
        // The AMS request payload will be updated by a listener.
        $this->setAmsRequestPayload('');
        $this->setAmsStatus(EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED);
        $this->setAmsPredictions([]);
        $this->setAmsTimestamp(0);

        return [
            'addressId' => $this->getAddressId(),
            'amsRequestPayload' => '',
            'amsStatus' => EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED,
            'amsPredictions' => [],
            'amsTimestamp' => 0,
        ];
    }
}
