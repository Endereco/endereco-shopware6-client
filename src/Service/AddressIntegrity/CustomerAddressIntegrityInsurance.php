<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Endereco\Shopware6Client\Service\CustomerAddressCacheInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\IntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\CustomerAddressSyncer;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\CustomerAddressSyncerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Coordinates address integrity checks and data synchronization
 */
final class CustomerAddressIntegrityInsurance implements CustomerAddressIntegrityInsuranceInterface
{
    /** @var CustomerAddressCacheInterface */
    private CustomerAddressCacheInterface $addressCache;

    /** @var CustomerAddressSyncerInterface */
    private CustomerAddressSyncerInterface $addressSyncer;

    /** @var iterable<IntegrityInsurance> */
    private iterable $insurances;

    /**
     * @param CustomerAddressCacheInterface $addressCache
     * @param CustomerAddressSyncer $addressSyncer
     * @param iterable<IntegrityInsurance> $insurances
     */
    public function __construct(
        CustomerAddressCacheInterface $addressCache,
        CustomerAddressSyncerInterface $addressSyncer,
        iterable $insurances
    ) {
        $this->addressCache = $addressCache;
        $this->addressSyncer = $addressSyncer;
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
        // IF the address has been processed already, we can be sure the database has all the information
        // So we just sync the entity with this information.
        if ($this->addressCache->has($addressEntity->getId())) {
            $this->addressSyncer->syncCustomerAddressEntity($addressEntity);
            return;
        }

        foreach ($this->insurances as $insurance) {
            $insurance->ensure($addressEntity, $context);
        }

        $this->addressCache->set($addressEntity);
    }
}
