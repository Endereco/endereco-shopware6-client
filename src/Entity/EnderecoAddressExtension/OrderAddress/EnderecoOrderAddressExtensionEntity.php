<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoCustomerAddressExtensionEntity.
 *
 * @author Michal Daniel
 * @author Ilja Weber
 * @author Martin Bens
 *
 * This class provides a custom entity to manage extensions for the Order Address object in the context
 * of the Endereco plugin.
 */
class EnderecoOrderAddressExtensionEntity extends EnderecoBaseAddressExtensionEntity
{
    /** @var ?OrderAddressEntity The associated order address entity. */
    protected ?OrderAddressEntity $address = null;

    /**
     * Get the associated order address entity.
     *
     * @return OrderAddressEntity|null The associated order address entity, or null if none is set.
     */
    public function getAddress(): ?OrderAddressEntity
    {
        return $this->address;
    }

    /**
     * Set the associated order address entity.
     *
     * @param OrderAddressEntity|null $address The associated order address entity to set.
     */
    public function setAddress(?Entity $address): void
    {
        if (!$address instanceof OrderAddressEntity) {
            throw new \InvalidArgumentException('The address must be an instance of OrderAddressEntity.');
        }

        $this->address = $address;
    }

    /**
     * @return array<string, mixed>
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
}
