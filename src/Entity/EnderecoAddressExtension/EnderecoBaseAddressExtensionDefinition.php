<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Base definition for Endereco address extensions
 */
abstract class EnderecoBaseAddressExtensionDefinition extends EntityDefinition
{
    /**
     * Defines entity structure
     *
     * @return FieldCollection Collection of entity fields
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // The primary key field linked to the address.
            $this->addressAssociationForeignKeyField()->addFlags(new Required(), new PrimaryKey()),

            // A field that contains a JSON array of predictions for possible address corrections.
            (new LongTextField('ams_request_payload', 'amsRequestPayload')),

            // A field that saves a list of status codes that describe the current address.
            (new LongTextField('ams_status', 'amsStatus')),

            // A field that contains the timestamp of the last address check.
            (new IntField('ams_timestamp', 'amsTimestamp')),

            // A field that contains a JSON array of predictions for possible address corrections.
            (new JsonField('ams_predictions', 'amsPredictions')),

            // A flag that defines whether the address originates from PayPal, through PayPal express checkout.
            (new BoolField('is_paypal_address', 'isPayPalAddress')),

            // A flag that defines whether the address originates from AmazonPay.
            (new BoolField('is_amazon_pay_address', 'isAmazonPayAddress')),

            // A field that contains the street name of the address (without the building number).
            (new StringField('street', 'street'))->addFlags(new AllowEmptyString()),

            // A field that contains the building number and potentially the building number additions.
            (new StringField('house_number', 'houseNumber'))->addFlags(new AllowEmptyString()),

            // One to one association field with the address.
            $this->addressAssociationField(),
        ]);
    }

    /**
     * Creates FK field for address association
     *
     * @return FkField Foreign key field configuration
     */
    abstract protected function addressAssociationForeignKeyField(): FkField;

    /**
     * Creates one-to-one field for address association
     *
     * @return OneToOneAssociationField Association field configuration
     */
    abstract protected function addressAssociationField(): OneToOneAssociationField;
}
