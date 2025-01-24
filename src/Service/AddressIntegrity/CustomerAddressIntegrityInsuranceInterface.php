<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Ensures address data integrity and validation status
 */
interface CustomerAddressIntegrityInsuranceInterface
{
    /**
     * Verifies address extension, street format, flags and validation status
     *
     * @param CustomerAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void;
}