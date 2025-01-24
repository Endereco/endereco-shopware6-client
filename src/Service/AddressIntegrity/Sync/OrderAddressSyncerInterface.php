<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Sync;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

/**
 * Syncs order address data between cache and entity
 */
interface OrderAddressSyncerInterface
{
    /**
     * Updates entity with cached address data within same request
     * @param OrderAddressEntity $addressEntity
     */
    public function syncOrderAddressEntity(OrderAddressEntity $addressEntity): void;
}
