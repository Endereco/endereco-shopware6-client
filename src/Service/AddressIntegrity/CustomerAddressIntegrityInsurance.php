<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\IntegrityInsurance;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Coordinates address integrity checks and data synchronization
 */
final class CustomerAddressIntegrityInsurance implements CustomerAddressIntegrityInsuranceInterface
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
     *
     * @param CustomerAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        foreach ($this->insurances as $insurance) {
            $insurance->ensure($addressEntity, $context);
        }
    }
}
