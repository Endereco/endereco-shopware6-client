<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;

/**
 * Utility class for updating EnderecoCustomerAddressExtensionEntity fields with normalized data from payloads
 *
 * Ensures that extension entities never contain strings longer than database constraints
 * by using normalized data from payload objects. Only updates fields that are present in the payload.
 *
 * Example usage:
 * $updater = new EnderecoExtensionEntityUpdater();
 * $updater->updateFromPayload($extensionData, $addressExtension);
 */
final class EnderecoExtensionEntityUpdater
{
    /**
     * Updates EnderecoCustomerAddressExtensionEntity fields from normalized payload data
     *
     * Only updates fields that are present in the payload's internal data array.
     * All data comes from the payload which has already been normalized to respect
     * database field length constraints.
     *
     * @param EnderecoExtensionData $extensionData Normalized payload with field length constraints applied
     * @param EnderecoCustomerAddressExtensionEntity $extensionEntity Extension entity to update
     */
    public function updateFromPayload(
        EnderecoExtensionData $extensionData,
        EnderecoCustomerAddressExtensionEntity $extensionEntity
    ): void {
        $payloadData = $extensionData->toArray();

        // Map payload fields to entity setters
        $fieldSetterMap = [
            EnderecoExtensionField::STREET => 'setStreet',
            EnderecoExtensionField::HOUSE_NUMBER => 'setHouseNumber',
            EnderecoExtensionField::AMS_STATUS => 'setAmsStatus',
            EnderecoExtensionField::AMS_REQUEST_PAYLOAD => 'setAmsRequestPayload',
            EnderecoExtensionField::AMS_TIMESTAMP => 'setAmsTimestamp',
            EnderecoExtensionField::AMS_PREDICTIONS => 'setAmsPredictions',
            EnderecoExtensionField::IS_PAYPAL_ADDRESS => 'setIsPayPalAddress',
            EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS => 'setIsAmazonPayAddress',
        ];

        // Update only fields that are present in the payload data
        foreach ($fieldSetterMap as $fieldName => $setterMethod) {
            if (array_key_exists($fieldName, $payloadData)) {
                $extensionEntity->$setterMethod($payloadData[$fieldName]);
            }
        }
    }
}
