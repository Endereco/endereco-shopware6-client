<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

interface CustomerAddressCacheInterface
{
    public function get(string $addressEntityId): ?CustomerAddressEntity;

    public function has(string $addressEntityId): bool;

    public function set(CustomerAddressEntity $addressEntity): void;
}
