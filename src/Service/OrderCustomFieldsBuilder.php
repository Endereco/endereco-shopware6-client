<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionCollection;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

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
     * {@inheritDoc}
     *
     * Processes all addresses associated with the order and extracts their
     * validation data into a format suitable for custom fields.
     */
    public function buildOrderBillingAddressValidationData(OrderEntity $orderEntity): array
    {
        $orderAddressExtensionCollection = new EnderecoOrderAddressExtensionCollection();

        foreach ($orderEntity->getAddresses() ?? [] as $orderAddressEntity) {
            $this->addOrderAddressExtensionToCollection($orderAddressEntity, $orderAddressExtensionCollection);
        }

        return $orderAddressExtensionCollection->buildDataForOrderCustomField();
    }

    /**
     * {@inheritDoc}
     *
     * Processes shipping addresses from all deliveries associated with the order
     * and extracts their validation data into a format suitable for custom fields.
     */
    public function buildOrderShippingAddressValidationData(OrderEntity $orderEntity): array
    {
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
