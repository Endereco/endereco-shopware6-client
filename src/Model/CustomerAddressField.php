<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

/**
 * Constants for Shopware CustomerAddress field names
 *
 * Provides field name constants for standard Shopware CustomerAddress
 * entity fields to prevent typos and improve maintainability.
 */
final class CustomerAddressField
{
    public const ID = 'id';
    public const STREET = 'street';
    public const ZIPCODE = 'zipcode';
    public const CITY = 'city';
    public const ADDITIONAL_ADDRESS_LINE_1 = 'additionalAddressLine1';
    public const ADDITIONAL_ADDRESS_LINE_2 = 'additionalAddressLine2';
    public const COUNTRY_ID = 'countryId';
    public const COUNTRY_STATE_ID = 'countryStateId';
    public const EXTENSIONS = 'extensions';

    private function __construct()
    {
        // Prevent instantiation
    }
}
