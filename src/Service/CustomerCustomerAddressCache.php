<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

final class CustomerCustomerAddressCache implements CustomerAddressCacheInterface
{
    /** @var array<string, CustomerAddressEntity> $addressEntities */
    private array $addressEntities = [];

    public function get(string $addressEntityId): ?CustomerAddressEntity
    {
        return $this->addressEntities[$addressEntityId] ?? null;
    }

    public function has(string $addressEntityId): bool
    {
        return array_key_exists($addressEntityId, $this->addressEntities);
    }

    public function set(CustomerAddressEntity $addressEntity): void
    {
        $this->addressEntities[$addressEntity->getId()] = $addressEntity;
    }
}
