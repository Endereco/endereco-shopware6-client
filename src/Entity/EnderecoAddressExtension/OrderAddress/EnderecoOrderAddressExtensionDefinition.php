<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Class EnderecoOrderAddressExtensionDefinition
 *
 * Defines the database schema and structure for order address extensions in the Endereco address verification system.
 * This class extends the base address extension definition to provide specific implementation for order addresses.
 *
 * Key features:
 * - Defines the entity structure for order address extensions
 * - Manages relationships between order addresses and customer addresses
 * - Implements Shopware's DataAbstractionLayer for field definitions
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension
 */
class EnderecoOrderAddressExtensionDefinition extends EnderecoBaseAddressExtensionDefinition
{
    /**
     * The entity name constant.
     */
    public const ENTITY_NAME = 'endereco_order_address_ext_gh';

    /**
     * Get the name of the entity.
     *
     * @return string The entity name.
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * Get the class of the entity.
     *
     * @return string The class of the entity.
     */
    public function getEntityClass(): string
    {
        return EnderecoOrderAddressExtensionEntity::class;
    }

    /**
     * Get the class of the collection.
     *
     * @return string The class of the collection.
     */
    public function getCollectionClass(): string
    {
        return EnderecoOrderAddressExtensionCollection::class;
    }


    /**
     * Creates the foreign key field for the address association.
     * Links this extension to the corresponding order address.
     *
     * @return FkField Foreign key field configured for order addresses
     */
    protected function addressAssociationForeignKeyField(): FkField
    {
        return new FkField(
            'address_id',
            'addressId',
            OrderAddressDefinition::class
        );
    }

    /**
     * Creates the one-to-one association field with the order address.
     * Establishes the relationship between this extension and the order address entity.
     *
     * @return OneToOneAssociationField Association field configured for order addresses
     */
    protected function addressAssociationField(): OneToOneAssociationField
    {
        return new OneToOneAssociationField(
            'address',
            'address_id',
            'id',
            OrderAddressDefinition::class,
            false
        );
    }
}
