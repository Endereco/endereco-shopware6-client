<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\IntegrityInsurance;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Coordinates order address integrity checks and data sync
 */
final class OrderAddressIntegrityInsurance implements OrderAddressIntegrityInsuranceInterface
{
    /** @var iterable<IntegrityInsurance> */
    private iterable $insurances;

    /**
     * @param iterable<IntegrityInsurance> $insurances
     */
    public function __construct(iterable $insurances)
    {
        $this->insurances = $insurances;
    }

    /**
     * Runs integrity checks or syncs cached data
     * @param OrderAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void
    {
        foreach ($this->insurances as $insurance) {
            $insurance->ensure($addressEntity, $context);
        }
    }
}
