<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\CustomerAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomerAddressExtension extends EntityExtension
{
    public const ENDERECO_EXTENSION = 'enderecoAddress';

    /**
     * Extend the fields of the CustomerAddressDefinition with the EnderecoAddressExtensionDefinition.
     *
     * @param FieldCollection $collection The collection of fields to extend.
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(
                self::ENDERECO_EXTENSION,
                'id',
                'address_id',
                EnderecoAddressExtensionDefinition::class,
                true
            )
        );
    }

    /**
     * Get the class name of the definition that is extended by this extension.
     *
     * @return string The class name of the extended definition.
     */
    public function getDefinitionClass(): string
    {
        return CustomerAddressDefinition::class;
    }
}
