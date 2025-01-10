<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Retrieves ISO country codes from Shopware's country repository with fallback handling.
 *
 * This service provides reliable access to country ISO codes by looking up country entities
 * in Shopware's data store. It includes fallback behavior to ensure a valid country code
 * is always returned, defaulting to 'DE' (Germany) if no other value can be determined.
 */
final class CountryCodeFetcher implements CountryCodeFetcherInterface
{
    /**
     * Repository for accessing country entities
     */
    private EntityRepository $countryRepository;

    /**
     * Creates a new CountryCodeFetcher with required dependencies.
     *
     * @param EntityRepository $countryRepository Repository for country entity access
     */
    public function __construct(
        EntityRepository $countryRepository
    ) {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @inheritDoc
     *
     * Attempts to fetch a country's ISO code from the repository using the following logic:
     * 1. Searches for country entity by ID
     * 2. If found, returns its ISO code (or default if ISO is null)
     * 3. If not found, returns the default country code
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
     * @param string $countryId The Shopware country ID to look up
     * @param Context $context Current Shopware context
     * @param string $defaultCountryCode Fallback country code, defaults to 'DE'
     *
     * @return string A valid country ISO code, either from the database or the default
     */
    public function fetchCountryCodeByCountryIdAndContext(
        string $countryId,
        Context $context,
        string $defaultCountryCode = 'DE'
    ): string {
        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search(new Criteria([$countryId]), $context)->first();

        // Check if the country was found
        if ($country !== null) {
            // If country is found, get the ISO code
            $countryCode = $country->getIso() ?? $defaultCountryCode;
        } else {
            // If no country is found, default to the provided default country code
            $countryCode = $defaultCountryCode;
        }

        return $countryCode;
    }
}
