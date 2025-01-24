<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Sync;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

/**
 * Syncs customer address data between cache and entity
 */
interface CustomerAddressSyncerInterface
{
    /**
     * Updates entity with cached address data within same request
     * @param CustomerAddressEntity $addressEntity
     */
    public function syncCustomerAddressEntity(CustomerAddressEntity $addressEntity): void;
}
