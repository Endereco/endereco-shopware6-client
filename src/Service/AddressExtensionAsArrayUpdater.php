<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;

/**
 * Utility class for updating extension data arrays with normalized data from extension payloads
 *
 * Ensures that extension arrays (like POST data extensions) never contain strings longer
 * than database constraints
 * by using normalized data from extension payload objects. Only updates extension fields that are
 * present in the payload.
 * Works with arrays passed by reference.
 *
 * Example usage:
 * $updater = new AddressExtensionAsArrayUpdater();
 * $extensionData = new EnderecoExtensionData();
 * $extensionData->setStreet('Some very long street name...');
 * $updater->updateFromExtensionPayload($extensionData, $addressData);
 */
final class AddressExtensionAsArrayUpdater
{
    /**
     * Updates extension data array from normalized extension payload data
     *
     * Only updates extension fields that are present in the payload's internal data array.
     * All data comes from the payload which has already been normalized to respect
     * database field length constraints.
     *
     * @param EnderecoExtensionData $extensionPayload Normalized extension payload with field length
     *                                                  constraints applied
     * @param array<string, mixed> $addressData Address data array to update (passed by reference)
     */
    public function updateFromExtensionPayload(
        EnderecoExtensionData $extensionPayload,
        array &$addressData
    ): void {
        $extensionDataArray = $extensionPayload->toArray();

        // Only proceed if there's extension data to update
        if (empty($extensionDataArray)) {
            return;
        }

        // Map extension payload fields to array keys
        $fieldMap = [
            EnderecoExtensionField::STREET,
            EnderecoExtensionField::HOUSE_NUMBER,
            EnderecoExtensionField::AMS_STATUS,
            EnderecoExtensionField::AMS_PREDICTIONS,
            EnderecoExtensionField::AMS_TIMESTAMP,
        ];

        // Initialize extensions structure if it doesn't exist
        if (!isset($addressData[CustomerAddressField::EXTENSIONS])) {
            $addressData[CustomerAddressField::EXTENSIONS] = [];
        }

        if (!isset($addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION])) {
            $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION] = [];
        }

        // Update only extension fields that are present in the payload data
        foreach ($fieldMap as $fieldName) {
            if (array_key_exists($fieldName, $extensionDataArray)) {
                $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION][$fieldName]
                    = $extensionDataArray[$fieldName];
            }
        }
    }
}
