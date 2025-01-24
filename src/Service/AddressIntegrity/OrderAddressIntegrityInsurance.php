<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity;

use Endereco\Shopware6Client\Service\OrderAddressCacheInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\IntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\OrderAddressSyncerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Coordinates order address integrity checks and data sync
 */
final class OrderAddressIntegrityInsurance implements OrderAddressIntegrityInsuranceInterface
{
    /** @var OrderAddressCacheInterface */
    private OrderAddressCacheInterface $addressCache;

    /** @var OrderAddressSyncerInterface */
    private OrderAddressSyncerInterface $addressSyncer;

    /** @var iterable<IntegrityInsurance> */
    private iterable $insurances;

    /**
     * @param OrderAddressCacheInterface $addressCache
     * @param OrderAddressSyncerInterface $addressSyncer
     * @param iterable<IntegrityInsurance> $insurances
     */
    public function __construct(
        OrderAddressCacheInterface $addressCache,
        OrderAddressSyncerInterface   $addressSyncer,
        iterable                      $insurances
    ) {
        $this->addressCache = $addressCache;
        $this->addressSyncer = $addressSyncer;
        $this->insurances = $insurances;
    }

    /**
     * Runs integrity checks or syncs cached data
     * @param OrderAddressEntity $addressEntity
     * @param Context $context
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void
    {
        // IF the address has been processed already, we can be sure the database has all the information
        // So we just sync the entity with this information.
        if ($this->addressCache->has($addressEntity->getId())) {
            $this->addressSyncer->syncOrderAddressEntity($addressEntity);
            return;
        }

        foreach ($this->insurances as $insurance) {
            $insurance->ensure($addressEntity, $context);
        }

        $this->addressCache->set($addressEntity);
    }
}
