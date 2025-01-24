<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionCollection;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

/**
 * Builds custom fields containing address validation data for Shopware orders.
 *
 * This service extracts address validation data from order addresses and their
 * Endereco extensions, converting them into a format suitable for order custom fields.
 * It handles both billing and shipping addresses separately.
 *
 * @final
 * @package Endereco\Shopware6Client\Service
 */
final class OrderCustomFieldsBuilder implements OrderCustomFieldsBuilderInterface
{
    /**
     * Compresses data from address extension into an array. This data will be saved in custom fields of order.
     * This method gathers data from the billing address.
     *
     * @param OrderEntity $orderEntity The order containing billing addresses
     * @return array<string, array<string, mixed>> Formatted validation data for order custom fields
     */
    public function buildOrderBillingAddressValidationData(
        OrderEntity $orderEntity
    ): array {
        $orderAddressExtensionCollection = new EnderecoOrderAddressExtensionCollection();
        $orderAddresses = $orderEntity->getAddresses();

        if (is_null($orderAddresses)) {
            return [];
        }
        /** @var OrderAddressCollection $orderAddresses */

        /** @var array<int, OrderAddressEntity> $billingAddresses */
        $billingAddresses = $orderAddresses->filter(
            function(OrderAddressEntity $address) use ($orderEntity) {
                return $address->getId() === $orderEntity->getBillingAddressId();
            }
        );

        /** @var OrderAddressEntity $orderAddressEntity */
        foreach ($billingAddresses as $orderAddressEntity) {
            $this->addOrderAddressExtensionToCollection(
                $orderAddressEntity,
                $orderAddressExtensionCollection
            );
        }

        return $orderAddressExtensionCollection->buildDataForOrderCustomField();
    }

    /**
     * Compresses data from address extension into an array. This data will be saved in custom fields of order.
     * This method gathers data from the shipping address or addresses if there are many.
     *
     * @param OrderEntity $orderEntity The order containing billing addresses
     * @return array<string, array<string, mixed>> Formatted validation data for order custom fields
     */
    public function buildOrderShippingAddressValidationData(
        OrderEntity $orderEntity
    ): array {
        $orderAddressExtensionCollection = new EnderecoOrderAddressExtensionCollection();

        foreach ($orderEntity->getDeliveries() ?? [] as $deliveryEntity) {
            $shippingOrderAddress = $deliveryEntity->getShippingOrderAddress();
            if ($shippingOrderAddress instanceof OrderAddressEntity) {
                $this->addOrderAddressExtensionToCollection(
                    $shippingOrderAddress,
                    $orderAddressExtensionCollection
                );
            }
        }

        return $orderAddressExtensionCollection->buildDataForOrderCustomField();
    }

    /**
     * Adds an order address extension to the collection if it exists.
     *
     * Extracts the Endereco extension from the order address and adds it to the
     * provided collection if present.
     *
     * @param OrderAddressEntity $orderAddressEntity The order address to process
     * @param EnderecoOrderAddressExtensionCollection $orderAddressExtensionCollection Collection to add the
     *                                                                                 extension to
     * @return void
     *
     * @internal
     */
    private function addOrderAddressExtensionToCollection(
        OrderAddressEntity $orderAddressEntity,
        EnderecoOrderAddressExtensionCollection $orderAddressExtensionCollection
    ): void {
        $orderAddressExtension = $orderAddressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
        if ($orderAddressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            $orderAddressExtensionCollection->set(
                $orderAddressExtension->getAddressId(),
                $orderAddressExtension
            );
        }
    }
}
