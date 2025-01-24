<?php

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Class EnderecoOrderAddressExtensionCollection
 *
 * Represents a collection of EnderecoOrderAddressExtensionEntity objects.
 * Provides methods for managing and processing multiple order address extensions.
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress
 * @extends EntityCollection<EnderecoOrderAddressExtensionEntity>
 */
class EnderecoOrderAddressExtensionCollection extends EntityCollection
{
    /**
     * @return string API alias for collection
     */
    public function getApiAlias(): string
    {
        return 'endereco_order_address_extension_collection';
    }

    /**
     * Builds data array for order custom fields from all entities in the collection.
     * Maps each extension entity to its order custom field representation.
     *
     * @return array<string, array<string, mixed>> Mapped array of order custom field data
     */
    public function buildDataForOrderCustomField(): array
    {
        return $this->fmap(static function (EnderecoOrderAddressExtensionEntity $orderAddressExtensionEntity) {
            return $orderAddressExtensionEntity->buildDataForOrderCustomField();
        });
    }

    /**
     * Returns the expected class name for collection items.
     * Used for type checking when adding items to the collection.
     *
     * @return string The fully qualified class name of EnderecoOrderAddressExtensionEntity
     */
    protected function getExpectedClass(): string
    {
        return EnderecoOrderAddressExtensionEntity::class;
    }
}
