<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

class EnderecoCustomerAddressExtensionDefinition extends EnderecoBaseAddressExtensionDefinition
{
    /** Entity name in database */
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
     * Creates FK field linking to customer address
     *
     * @return FkField The foreign key field
     */
    protected function addressAssociationForeignKeyField(): FkField
    {
        return new FkField('address_id', 'addressId', CustomerAddressDefinition::class);
    }

    /**
     * Creates one-to-one association with customer address
     *
     * @return OneToOneAssociationField The association field
     */
    protected function addressAssociationField(): OneToOneAssociationField
    {
        return new OneToOneAssociationField('address', 'address_id', 'id', CustomerAddressDefinition::class, false);
    }
}
