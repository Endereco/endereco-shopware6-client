<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Sync;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\CustomerAddressCacheInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

/**
 * Handles customer address data sync logic
 */
final class CustomerAddressSyncer implements CustomerAddressSyncerInterface
{
    /** @var CustomerAddressCacheInterface */
    private CustomerAddressCacheInterface $addressCache;

    /**
     * @param CustomerAddressCacheInterface $addressCache For accessing cached data
     * @throws \Exception If cached entity missing extension
     */
    public function __construct(CustomerAddressCacheInterface $addressCache)
    {
        $this->addressCache = $addressCache;
    }

    /** @inheritDoc */
    public function syncCustomerAddressEntity(CustomerAddressEntity $addressEntity): void
    {
        $cachedAddressEntity = $this->addressCache->get($addressEntity->getId());
        if ($cachedAddressEntity === null) {
            return;
        }

        $cachedAddressExtension = $cachedAddressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        if (!$cachedAddressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \Exception('Cached address entities should always have the extension, but this one has not.');
        }

        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            $addressExtension = new EnderecoCustomerAddressExtensionEntity();
            $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
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
