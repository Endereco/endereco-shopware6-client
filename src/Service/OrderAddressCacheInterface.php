<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

interface OrderAddressCacheInterface
{
    public function get(string $addressEntityId): ?OrderAddressEntity;

    public function has(string $addressEntityId): bool;

    public function set(OrderAddressEntity $addressEntity): void;
}
