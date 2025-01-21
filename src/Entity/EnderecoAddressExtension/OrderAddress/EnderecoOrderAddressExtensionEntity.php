<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoOrderAddressExtensionEntity
 *
 * Represents an extension entity for order addresses in the Endereco address verification system.
 * Provides functionality for managing address verification data and status for order addresses.
 *
 * Features:
 * - Manages address verification status and metadata
 * - Handles relationships with Shopware order addresses
 * - Provides data conversion methods for cart and order processes
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress
 * @property-read OrderAddressEntity|null $address Associated order address entity
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
}
