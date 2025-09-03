<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

/**
 * Constants for Endereco extension field names
 *
 * Provides field name constants for Endereco extension data
 * to prevent typos and improve maintainability.
 */
final class EnderecoExtensionField
{
    public const ADDRESS_ID = 'addressId';
    public const STREET = 'street';
    public const HOUSE_NUMBER = 'houseNumber';
    public const AMS_STATUS = 'amsStatus';
    public const AMS_REQUEST_PAYLOAD = 'amsRequestPayload';
    public const AMS_TIMESTAMP = 'amsTimestamp';
    public const AMS_PREDICTIONS = 'amsPredictions';
    public const IS_PAYPAL_ADDRESS = 'isPayPalAddress';
    public const IS_AMAZON_PAY_ADDRESS = 'isAmazonPayAddress';

    private function __construct()
    {
        // Prevent instantiation
    }
}
