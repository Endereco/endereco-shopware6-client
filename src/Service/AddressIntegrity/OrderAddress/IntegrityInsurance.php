<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

interface IntegrityInsurance
{
    public static function getPriority(): int;

    /**
     * @param OrderAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(
        OrderAddressEntity $addressEntity,
        Context $context
    ): void;
}
