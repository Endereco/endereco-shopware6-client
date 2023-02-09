<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EnderecoAddressExtensionDefinition extends EntityDefinition
{
    private const ENTITY_NAME = 'endereco_address_ext';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return EnderecoAddressExtensionEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('address_id', 'addressId', CustomerAddressDefinition::class))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('ams_status', 'amsStatus')),
            (new StringField('street', 'street')),
            (new StringField('house_number', 'houseNumber')),
            new OneToOneAssociationField('address', 'address_id', 'id', CustomerAddressDefinition::class, false)
        ]);
    }
}
