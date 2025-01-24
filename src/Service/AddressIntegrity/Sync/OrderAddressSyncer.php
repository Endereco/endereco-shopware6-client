<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Sync;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Service\OrderAddressCacheInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

/**
 * Handles order address data sync logic
 */
final class OrderAddressSyncer implements OrderAddressSyncerInterface
{
    /** @var OrderAddressCacheInterface */
    private OrderAddressCacheInterface $addressCache;

    /**
     * @param OrderAddressCacheInterface $addressCache For accessing cached data
     * @throws \Exception If cached entity missing extension
     */
    public function __construct(OrderAddressCacheInterface $addressCache)
    {
        $this->addressCache = $addressCache;
    }

    /** @inheritDoc */
    public function syncOrderAddressEntity(OrderAddressEntity $addressEntity): void
    {
        $cachedAddressEntity = $this->addressCache->get($addressEntity->getId());
        if ($cachedAddressEntity === null) {
            return;
        }

        $cachedAddressExtension = $cachedAddressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
        if (!$cachedAddressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            throw new \Exception('Cached address entities should always have the extension, but this one has not.');
        }

        $addressExtension = $addressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            $addressExtension = new EnderecoOrderAddressExtensionEntity();
            $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $addressExtension);
        }

        $addressEntity->setZipcode($cachedAddressEntity->getZipcode());
        $addressEntity->setCity($cachedAddressEntity->getCity());
        $addressEntity->setStreet($cachedAddressEntity->getStreet());
        if ($cachedAddressEntity->getCountryStateId() !== null) {
            $addressEntity->setCountryStateId($cachedAddressEntity->getCountryStateId());
        }

        $addressExtension->sync($addressExtension);
    }
}
