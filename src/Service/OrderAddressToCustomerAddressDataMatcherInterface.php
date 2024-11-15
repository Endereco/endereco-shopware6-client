<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Struct\OrderAddressDataForComparison;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface OrderAddressToCustomerAddressDataMatcherInterface
{
    /**
     * The matcher finds all customer addresses from the order/cart billing address and shipping addresses.
     * As multiple customer addresses could match the order address data, the matcher should return all possible matches
     * and let the caller decide which one to use.
     * E.g. multiple customer addresses could math but only one has an Endereco extension.
     *
     * @param OrderAddressDataForComparison $orderAddressData
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return CustomerAddressCollection
     */
    public function findCustomerAddressesForOrderAddressDataInCart(
        OrderAddressDataForComparison $orderAddressData,
        Cart $cart,
        SalesChannelContext $context
    ): CustomerAddressCollection;
}
