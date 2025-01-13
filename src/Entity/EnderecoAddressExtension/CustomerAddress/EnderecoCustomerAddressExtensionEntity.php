<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoCustomerAddressExtensionEntity.
 *
 * @author Michal Daniel
 * @author Ilja Weber
 * @author Martin Bens
 *
 * This class provides a custom entity to manage extensions for the Customer Address object in the context
 * of the Endereco plugin.
 */
class EnderecoCustomerAddressExtensionEntity extends EnderecoBaseAddressExtensionEntity
{
    /** @var ?CustomerAddressEntity The associated customer address entity. */
    protected ?CustomerAddressEntity $address = null;

    /**
     * Get the associated customer address entity.
     *
     * @return CustomerAddressEntity|null The associated customer address entity, or null if none is set.
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
}
