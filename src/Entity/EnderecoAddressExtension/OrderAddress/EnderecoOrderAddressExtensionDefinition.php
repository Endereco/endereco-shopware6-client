<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;

/**
 * Class EnderecoAddressExtensionDefinition
 *
 * Entity definition for Endereco Address Extension.
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

    protected function addressAssociationForeignKeyField(): FkField
    {
        return new FkField('address_id', 'addressId', OrderAddressDefinition::class);
    }

    protected function addressAssociationField(): OneToOneAssociationField
    {
        return new OneToOneAssociationField('address', 'address_id', 'id', OrderAddressDefinition::class, false);
    }
}
