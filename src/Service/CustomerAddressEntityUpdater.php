<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\CustomerAddressField;

/**
 * Utility class for updating CustomerAddressEntity fields with normalized data from payloads
 *
 * Ensures that CustomerAddressEntity never contains strings longer than database constraints
 * by using normalized data from payload objects. Only updates fields that are present in the payload.
 *
 * Example usage:
 * $updater = new CustomerAddressEntityUpdater();
 * $updater->updateFromPayload($payload, $addressEntity);
 */
final class CustomerAddressEntityUpdater
{
    /**
     * Updates CustomerAddressEntity fields from normalized payload data
     *
     * Only updates fields that are present in the payload's internal data array.
     * All data comes from the payload which has already been normalized to respect
     * database field length constraints.
     *
     * @param CustomerAddressUpdatePayload $payload Normalized payload with field length constraints applied
     * @param CustomerAddressEntity $addressEntity Entity to update
     */
    public function updateFromPayload(
        CustomerAddressUpdatePayload $payload,
        CustomerAddressEntity $addressEntity
    ): void {
        $payloadData = $payload->toArray();

        // Map payload fields to entity setters
        $fieldSetterMap = [
            CustomerAddressField::STREET => 'setStreet',
            CustomerAddressField::ZIPCODE => 'setZipcode',
            CustomerAddressField::CITY => 'setCity',
            CustomerAddressField::COUNTRY_ID => 'setCountryId',
            CustomerAddressField::COUNTRY_STATE_ID => 'setCountryStateId',
            CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1 => 'setAdditionalAddressLine1',
            CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2 => 'setAdditionalAddressLine2',
        ];

        // Update only fields that are present in the payload data
        foreach ($fieldSetterMap as $fieldName => $setterMethod) {
            if (array_key_exists($fieldName, $payloadData)) {
                $addressEntity->$setterMethod($payloadData[$fieldName]);
            }
        }
    }
}
