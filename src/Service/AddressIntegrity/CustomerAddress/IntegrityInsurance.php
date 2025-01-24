<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

interface IntegrityInsurance
{
    public static function getPriority(): int;

    /**
     * @param CustomerAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(CustomerAddressEntity $addressEntity,  Context $context): void;
}
