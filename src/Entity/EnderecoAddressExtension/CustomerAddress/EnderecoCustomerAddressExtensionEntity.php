<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoCustomerAddressExtensionEntity
 *
 * Represents a custom entity for managing Endereco address verification extensions
 * specifically for customer addresses in Shopware 6.
 *
 * This class extends the base address extension functionality to provide:
 * - Association management with Shopware customer addresses
 * - Data conversion capabilities for order processing
 * - Address verification status and metadata management
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress
 * @author Michal Daniel
 * @author Ilja Weber
 * @author Martin Bens
 *
 * @property-read CustomerAddressEntity|null $address The associated customer address entity
 * @property-read string $addressId The ID of the associated customer address
 */
class EnderecoCustomerAddressExtensionEntity extends EnderecoBaseAddressExtensionEntity
{
    /** @var ?CustomerAddressEntity The associated customer address entity. */
    protected ?CustomerAddressEntity $address = null;

    /**
     * Gets the associated customer address entity.
     *
     * @return CustomerAddressEntity|null The associated customer address entity or null if not set
     */
    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    /**
     * Set the associated customer address entity.
     *
     * @param CustomerAddressEntity|null $address The associated customer address entity to set.
     */
    public function setAddress(?Entity $address): void
    {
        if (!$address instanceof CustomerAddressEntity) {
            throw new \InvalidArgumentException('The address must be an instance of CustomerAddressEntity.');
        }

        $this->address = $address;
    }

    /**
     * Creates a new order address extension entity based on this customer address extension.
     * Copies all relevant address verification data to the new order address extension.
     *
     * This method is used when converting customer addresses to order addresses during
     * the checkout process, ensuring all Endereco verification data is preserved.
     *
     * @param string $orderAddressId The ID of the new order address to associate with
     * @return EnderecoOrderAddressExtensionEntity A new order address extension populated with this entity's data
     */
    public function createOrderAddressExtension(string $orderAddressId): EnderecoOrderAddressExtensionEntity
    {
        $entity = new EnderecoOrderAddressExtensionEntity();
        $entity->setAddressId($orderAddressId);
        $entity->setAmsStatus($this->getAmsStatus());
        $entity->setAmsTimestamp($this->getAmsTimestamp());
        $entity->setAmsPredictions($this->getAmsPredictions());
        $entity->setIsPayPalAddress($this->isPayPalAddress());
        $entity->setIsAmazonPayAddress($this->isAmazonPayAddress());
        $entity->setStreet($this->getStreet());
        $entity->setHouseNumber($this->getHouseNumber());

        return $entity;
    }
}
