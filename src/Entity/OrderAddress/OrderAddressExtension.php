<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderAddressExtension extends EntityExtension
{
    public const ENDERECO_EXTENSION = 'enderecoAddress';

    /**
     * Extend the fields of the OrderAddressDefinition with the EnderecoOrderAddressExtensionDefinition.
     *
     * @param FieldCollection $collection The collection of fields to extend.
     */
    public function extendFields(FieldCollection $collection): void
    {
        $associationField = new OneToOneAssociationField(
            self::ENDERECO_EXTENSION,
            'id',
            'address_id',
            EnderecoOrderAddressExtensionDefinition::class,
            true // This is marked as bad practise by Shopware and should be replaced with conditional loading.
        );
        // The CascadeDelete flag tells Shopware that this extension is marked as cascade delete in the database.
        // Shopware will only version-copy extensions that are flagged like this during it's merge process.
        // This prevents the loss of this data in process.
        $associationField->addFlags(new CascadeDelete());
        $collection->add($associationField);
    }

    /**
     * Get the class name of the definition that is extended by this extension.
     *
     * @return string The class name of the extended definition.
     */
    public function getDefinitionClass(): string
    {
        return OrderAddressDefinition::class;
    }

    /**
     * Get the entity name that is extended by this extension.
     *
     * @return string The entity name.
     */
    public function getEntityName(): string
    {
        return 'order_address';
    }
}
