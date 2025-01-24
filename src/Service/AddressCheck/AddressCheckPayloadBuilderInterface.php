<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

interface AddressCheckPayloadBuilderInterface
{
    /**
     * Builds payload from array data (e.g. from POST request)
     *
     * @param array{
     *   countryId: string,
     *   countryStateId?: string|null,
     *   zipcode: string,
     *   city: string,
     *   street: string
     * } $addressData
     * @param Context $context
     * @return AddressCheckPayload
     */
    public function buildFromArray(array $addressData, Context $context): AddressCheckPayload;

    /**
     * Builds payload from CustomerAddressEntity
     *
     * @param CustomerAddressEntity $address
     * @param Context $context
     * @return AddressCheckPayload
     */
    public function buildFromCustomerAddress(CustomerAddressEntity $address, Context $context): AddressCheckPayload;

    /**
     * Builds payload from OrderAddressEntity
     *
     * @param OrderAddressEntity $address
     * @param Context $context
     * @return AddressCheckPayload
     */
    public function buildFromOrderAddress(OrderAddressEntity $address, Context $context): AddressCheckPayload;
}