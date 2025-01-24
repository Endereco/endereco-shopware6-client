<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Check;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Checks if street address needs to be split into street name and house number
 */
interface IsStreetSplitRequiredCheckerInterface
{
    /**
     * Verifies if customer address needs street/house number split
     *
     * @param CustomerAddressEntity $addressEntity Address to check
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension Extension with split data
     * @param Context $context Shopware context
     * @return bool True if split is needed
     */
    public function checkIfStreetSplitIsRequired(
        CustomerAddressEntity $addressEntity,
        EnderecoCustomerAddressExtensionEntity $addressExtension,
        Context $context
    ): bool;

    /**
     * Verifies if order address needs street/house number split
     *
     * @param OrderAddressEntity $addressEntity Order address to check
     * @param EnderecoOrderAddressExtensionEntity $addressExtension Extension with split data
     * @param Context $context Shopware context
     * @return bool True if split is needed
     */
    public function checkIfOrderAddressStreetSplitIsRequired(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension,
        Context $context
    ): bool;
}
