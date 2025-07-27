<?php

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Class EnderecoCustomerAddressExtensionCollection
 *
 * Represents a collection of EnderecoCustomerAddressExtensionEntity objects.
 * Provides methods for managing and processing multiple customer address extensions.
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress
 * @extends EntityCollection<EnderecoCustomerAddressExtensionEntity>
 */
class EnderecoCustomerAddressExtensionCollection extends EntityCollection
{
    /**
     * @return string API alias for collection
     */
    public function getApiAlias(): string
    {
        return 'endereco_customer_address_extension_collection';
    }

    /**
     * Returns the expected class name for collection items.
     * Used for type checking when adding items to the collection.
     *
     * @return string The fully qualified class name of EnderecoCustomerAddressExtensionEntity
     */
    protected function getExpectedClass(): string
    {
        return EnderecoCustomerAddressExtensionEntity::class;
    }
}
