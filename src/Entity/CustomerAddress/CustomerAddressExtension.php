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

    public function getDefinitionClass(): string
    {
        return CustomerAddressDefinition::class;
    }
}
