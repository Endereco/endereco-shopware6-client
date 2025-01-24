<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Check;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Verifies if stored address validation data matches current address data
 */
interface IsAmsRequestPayloadIsUpToDateCheckerInterface
{
    /**
     * Checks if customer address validation metadata is current
     *
     * @param CustomerAddressEntity $addressEntity Current address data
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension Stored validation data
     * @param Context $context Shopware context
     * @return bool True if metadata is current
     */
    public function checkIfCustomerAddressMetaIsUpToDate(
        CustomerAddressEntity $addressEntity,
        EnderecoCustomerAddressExtensionEntity $addressExtension,
        Context $context
    ): bool;

    /**
     * Checks if order address validation metadata is current
     *
     * @param OrderAddressEntity $addressEntity Current address
     * @param EnderecoOrderAddressExtensionEntity $addressExtension Stored validation data
     * @param Context $context Shopware context
     * @return bool True if metadata is current
     */
    public function checkIfOrderAddressMetaIsUpToDate(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension,
        Context $context
    ): bool;
}
