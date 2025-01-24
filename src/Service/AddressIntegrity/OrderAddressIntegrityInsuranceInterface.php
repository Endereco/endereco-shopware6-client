<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Ensures order address data integrity
 */
interface OrderAddressIntegrityInsuranceInterface
{
    /**
     * Verifies address extension, street format and validation status
     *
     * @param OrderAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void;
}