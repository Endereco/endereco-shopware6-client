<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Interface for building custom fields containing address validation data for orders.
 *
 * This interface defines methods for extracting and formatting address validation data
 * from orders. It handles both billing and shipping addresses, converting their validation
 * status and metadata into custom fields format.
 *
 * @package Endereco\Shopware6Client\Service
 */
interface OrderCustomFieldsBuilderInterface
{
    /**
     * Builds custom field data for billing address validation.
     *
     * Extracts and formats validation data from all billing addresses associated
     * with the provided order.
     *
     * @param OrderEntity $orderEntity The order containing the billing addresses
     * @return array<string, array<string, mixed>> Formatted validation data for custom fields
     */
    public function buildOrderBillingAddressValidationData(
        OrderEntity $orderEntity
    ): array;

    /**
     * Builds custom field data for shipping address validation.
     *
     * Extracts and formats validation data from all shipping addresses associated
     * with the provided order's deliveries.
     *
     * @param OrderEntity $orderEntity The order containing the shipping addresses
     * @return array<string, array<string, mixed>> Formatted validation data for custom fields
     */
    public function buildOrderShippingAddressValidationData(
        OrderEntity $orderEntity
    ): array;
}
