<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;

/**
 * Interface for services that update order custom fields with address validation data.
 *
 * This interface defines the contract for services that handle the synchronization
 * of address validation data to order custom fields. Implementing services should be
 * able to update orders based on either order IDs or order address IDs.
 */
interface OrdersCustomFieldsUpdaterInterface
{
    public function updateOrdersCustomFields(
        OrderAddressCollection $orderAddresses,
        Context $context
    ): void;
}
