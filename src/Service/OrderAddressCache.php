<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

final class OrderAddressCache implements OrderAddressCacheInterface
{
    /** @var array<string, OrderAddressEntity> $addressEntities */
    private array $addressEntities = [];

    public function get(string $addressEntityId): ?OrderAddressEntity
    {
        return $this->addressEntities[$addressEntityId] ?? null;
    }

    public function has(string $addressEntityId): bool
    {
        return array_key_exists($addressEntityId, $this->addressEntities);
    }

    public function set(OrderAddressEntity $addressEntity): void
    {
        $this->addressEntities[$addressEntity->getId()] = $addressEntity;
    }
}
