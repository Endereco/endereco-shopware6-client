<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;

/**
 * Defines interface for retrieving ISO country codes with fallback handling.
 *
 * This interface specifies the contract for services that resolve country ISO codes
 * from Shopware country IDs. Implementations should:
 * - Handle missing or invalid country IDs gracefully
 * - Provide configurable fallback behavior
 * - Return valid ISO country codes in all cases
 */
interface CountryCodeFetcherInterface
{
    /**
     * Retrieves the ISO code of a country by its ID.
     *
     * Resolves a country's ISO code using the following logic:
     * 1. Attempts to find country by provided ID
     * 2. If found, returns its ISO code
     * 3. If not found or if ISO code is missing, returns default country code
     *
     * Example usage:
     * ```php
     * $countryCode = $fetcher->fetchCountryCodeByCountryIdAndContext(
     *     '2bba4393ac1b40c09ad98352394ddf52',
     *     $context,
     *     'DE' // optional custom default
     * );
     * ```
     *
     * @param string $countryId The ID of the country to look up
     * @param Context $context Current Shopware context
     * @param string $defaultCountryCode Fallback country code, defaults to 'DE'
     *
     * @return string A valid country ISO code, either from lookup or default
     */
    public function fetchCountryCodeByCountryIdAndContext(
        string $countryId,
        Context $context,
        string $defaultCountryCode = 'DE'
    ): string;
}
