<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * Class EnderecoAddressExtensionDefinition
 *
 * Entity definition for Endereco Address Extension.
 *
 * @package Endereco\Shopware6Client\Entity\EnderecoAddressExtension
 */
class EnderecoCustomerAddressExtensionDefinition extends EnderecoBaseAddressExtensionDefinition
{
    /**
     * The entity name constant.
     */
    public const ENTITY_NAME = 'endereco_customer_address_ext_gh';

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
        return EnderecoCustomerAddressExtensionEntity::class;
    }

    /**
     * Creates the foreign key field that links this extension to a customer address.
     * Implements the abstract method from the base definition.
     *
     * @return FkField The configured foreign key field for customer address association
     */
    protected function addressAssociationForeignKeyField(): FkField
    {
        return new FkField('address_id', 'addressId', CustomerAddressDefinition::class);
    }

    /**
     * Creates the one-to-one association field with the customer address.
     * Implements the abstract method from the base definition.
     *
     * @return OneToOneAssociationField The configured one-to-one association field
     */
    protected function addressAssociationField(): OneToOneAssociationField
    {
        return new OneToOneAssociationField('address', 'address_id', 'id', CustomerAddressDefinition::class, false);
    }
}
