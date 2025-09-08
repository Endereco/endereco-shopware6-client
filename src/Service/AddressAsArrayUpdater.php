<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\CustomerAddressField;

/**
 * Utility class for updating address data arrays with normalized data from payloads (native Shopware fields only)
 *
 * Ensures that address arrays (like POST data) never contain strings longer than database constraints
 * by using normalized data from payload objects. Only updates native Shopware fields that are present in the payload.
 * For extension fields, use AddressExtensionAsArrayUpdater. Works with arrays passed by reference.
 *
 * Example usage:
 * $updater = new AddressAsArrayUpdater();
 * $payload = new CustomerAddressUpdatePayload('123');
 * $payload->setStreet('Some very long street name...');
 * $updater->updateFromPayload($payload, $addressData);
 */
final class AddressAsArrayUpdater
{
    /**
     * Updates address data array from normalized payload data (native Shopware fields only)
     *
     * Only updates native Shopware fields that are present in the payload's internal data array.
     * Extension fields are ignored - use AddressExtensionAsArrayUpdater for those.
     * All data comes from the payload which has already been normalized to respect
     * database field length constraints.
     *
     * @param CustomerAddressUpdatePayload $payload Normalized payload with field length constraints applied
     * @param array<string, mixed> $addressData Address data array to update (passed by reference)
     */
    public function updateFromPayload(
        CustomerAddressUpdatePayload $payload,
        array &$addressData
    ): void {
        $payloadData = $payload->toArray();

        // Map payload fields to array keys
        $fieldMap = [
            CustomerAddressField::STREET,
            CustomerAddressField::ZIPCODE,
            CustomerAddressField::CITY,
            CustomerAddressField::COUNTRY_ID,
            CustomerAddressField::COUNTRY_STATE_ID,
            CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1,
            CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2,
        ];

        // Update only native Shopware fields that are present in the payload data
        foreach ($fieldMap as $fieldName) {
            if (array_key_exists($fieldName, $payloadData)) {
                $addressData[$fieldName] = $payloadData[$fieldName];
            }
        }
    }
}
