<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Struct\OrderAddressDataForComparison;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Matches order addresses to customer addresses during cart-to-order conversion.
 *
 * This service is responsible for finding exact matches between an order address
 * and existing customer addresses (both billing and shipping). The matching process
 * uses a strict comparison approach to ensure data integrity during address conversion.
 *
 * The matching algorithm:
 * 1. Normalizes addresses by transforming them to a comparable format
 * 2. Sorts all address fields to ensure order-independent comparison
 * 3. Uses serialized comparison for exact matching
 *
 * @final
 */
final class OrderAddressToCustomerAddressDataMatcher implements OrderAddressToCustomerAddressDataMatcherInterface
{
    /**
     * Finds all customer addresses that exactly match the provided order address data.
     *
     * The matching process follows these steps:
     * 1. Creates an empty collection for matching addresses
     * 2. Normalizes and sorts the order address data
     * 3. Checks the customer's active billing address for a match
     * 4. Checks all shipping addresses in the cart's deliveries for matches
     *
     * The comparison is done by:
     * - Transforming addresses to a consistent format
     * - Sorting all fields to eliminate order differences
     * - Using serialization for deep equality comparison
     *
     * @param OrderAddressDataForComparison $orderAddressData The order address to find matches for
     * @param Cart $cart The current cart containing potential matching addresses
     * @param SalesChannelContext $context The sales channel context containing customer data
     * @return CustomerAddressCollection Collection of all matching customer addresses
     */
    public function findCustomerAddressesForOrderAddressDataInCart(
        OrderAddressDataForComparison $orderAddressData,
        Cart $cart,
        SalesChannelContext $context
    ): CustomerAddressCollection {
        $customerAddressCollection = new CustomerAddressCollection();

        $orderAddressDataToCompare = $orderAddressData->data();
        array_multisort($orderAddressDataToCompare);

        // Check billing address match
        $customer = $context->getCustomer();
        if ($customer instanceof CustomerEntity) {
            $billingAddress = $customer->getActiveBillingAddress();
            if ($billingAddress instanceof CustomerAddressEntity) {
                $billingAddressDataToCompare = $this->generateCustomerAddressDataToCompare($billingAddress)->data();
                array_multisort($billingAddressDataToCompare);

                if (serialize($orderAddressDataToCompare) === serialize($billingAddressDataToCompare)) {
                    $customerAddressCollection->add($billingAddress);
                }
            }
        }

        // Check shipping address matches
        foreach ($cart->getDeliveries() as $delivery) {
            $shippingAddress = $delivery->getLocation()->getAddress();
            if ($shippingAddress instanceof CustomerAddressEntity) {
                $shippingAddressDataToCompare = $this->generateCustomerAddressDataToCompare($shippingAddress)->data();
                array_multisort($shippingAddressDataToCompare);

                if (serialize($orderAddressDataToCompare) === serialize($shippingAddressDataToCompare)) {
                    $customerAddressCollection->add($shippingAddress);
                }
            }
        }

        return $customerAddressCollection;
    }

    /**
     * Transforms a customer address entity into a comparable format.
     *
     * This method:
     * 1. Uses Shopware's AddressTransformer to convert the address to array format
     * 2. Wraps the transformed data in an OrderAddressDataForComparison object
     *
     * The transformation ensures that the address data is in a consistent format
     * for comparison, regardless of its original source.
     *
     * @param CustomerAddressEntity $customerAddressEntity The customer address to transform
     * @return OrderAddressDataForComparison The address in comparable format
     */
    private function generateCustomerAddressDataToCompare(
        CustomerAddressEntity $customerAddressEntity
    ): OrderAddressDataForComparison {
        /** @var array<string, mixed> $customerAddressDataToCompare */
        $customerAddressDataToCompare = AddressTransformer::transform($customerAddressEntity);

        return OrderAddressDataForComparison::fromCartToOrderConversionData($customerAddressDataToCompare);
    }
}
