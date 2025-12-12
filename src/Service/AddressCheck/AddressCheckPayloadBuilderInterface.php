<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Endereco\Shopware6Client\Model\AddressCheckPayloadInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

interface AddressCheckPayloadBuilderInterface
{
    /**
     * System configuration key for split street format setting
     */
    public const CONFIG_SPLIT_STREET = 'EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber';
    /**
     * Builds payload from array data (e.g. from POST request)
     *
     * @param array{
     *   countryId: string,
     *   countryStateId?: string|null,
     *   zipcode: string,
     *   city: string,
     *   street: string,
     *   additionalAddressLine1: string|null,
     *   additionalAddressLine2: string|null
     * } $addressData
     * @param Context $context
     * @return AddressCheckPayloadInterface
     */
    public function buildFromArray(array $addressData, Context $context): AddressCheckPayloadInterface;

    /**
     * Builds payload from CustomerAddressEntity
     *
     * @param CustomerAddressEntity $address
     * @param Context $context
     * @return AddressCheckPayloadInterface
     */
    public function buildFromCustomerAddress(CustomerAddressEntity $address, Context $context): AddressCheckPayloadInterface;

    /**
     * Builds payload from OrderAddressEntity
     *
     * @param OrderAddressEntity $address
     * @param Context $context
     * @return AddressCheckPayloadInterface
     */
    public function buildFromOrderAddress(OrderAddressEntity $address, Context $context): AddressCheckPayloadInterface;
}