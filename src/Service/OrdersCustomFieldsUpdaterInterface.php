<?php

namespace Endereco\Shopware6Client\Service;

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
    /**
     * Updates custom fields on orders with address validation data.
     *
     * Implementations should handle loading the necessary order data and updating
     * the custom fields with current address validation information. The method
     * should be able to process updates based on either order IDs directly or
     * order address IDs.
     *
     * @param string[] $orderIds IDs of orders to update
     * @param string[] $orderAddressIds IDs of order addresses whose orders should be updated
     * @param Context $context The Shopware context for the operation
     *
     * @return void
     */
    public function updateOrdersCustomFields(array $orderIds, array $orderAddressIds, Context $context): void;
}
