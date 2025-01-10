<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Implements the creation of address check payloads for the Endereco API.
 *
 * This service transforms Shopware customer addresses into structured payloads
 * for the Endereco address validation API. It handles:
 * - Locale detection with German fallback
 * - Country code resolution
 * - State/subdivision code processing
 * - Address component extraction and formatting
 */
final class AddressCheckPayloadBuilder implements AddressCheckPayloadBuilderInterface
{
    /**
     * Service for fetching locale information
     */
    private LocaleFetcherInterface $localeFetcher;

    /**
     * Service for resolving country codes
     */
    private CountryCodeFetcherInterface $countryCodeFetcher;

    /**
     * Service for fetching subdivision codes
     */
    private SubdivisionCodeFetcherInterface $subdivisionCodeFetcher;

    /**
     * Service for checking if a country has states/subdivisions
     */
    private CountryHasStatesCheckerInterface $countryHasStatesChecker;

    /**
     * Creates a new AddressCheckPayloadBuilder with required dependencies.
     *
     * @param LocaleFetcherInterface $localeFetcher Service for locale resolution
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service for country code lookup
     * @param SubdivisionCodeFetcherInterface $subdivisionCodeFetcher Service for subdivision code resolution
     * @param CountryHasStatesCheckerInterface $countryHasStatesChecker Service for checking country subdivision support
     */
    public function __construct(
        LocaleFetcherInterface $localeFetcher,
        CountryCodeFetcherInterface $countryCodeFetcher,
        SubdivisionCodeFetcherInterface $subdivisionCodeFetcher,
        CountryHasStatesCheckerInterface $countryHasStatesChecker
    ) {
        $this->localeFetcher = $localeFetcher;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->subdivisionCodeFetcher = $subdivisionCodeFetcher;
        $this->countryHasStatesChecker = $countryHasStatesChecker;
    }

    /**
     * @inheritDoc
     *
     * Creates an address check payload with the following logic:
     * - Attempts to detect locale, falls back to 'de' if unsuccessful
     * - Resolves country code from address country ID
     * - Processes postal code with empty string fallback
     * - Extracts city name and full street address
     * - Handles subdivision codes with special cases:
     *   - null: country has no states
     *   - empty string: country has states but none selected
     *   - specific code: selected state code
     *
     * @throws \Exception potentially from locale fetching (caught and handled with fallback)
     */
    public function buildAddressCheckPayload(
        string $salesChannelId,
        CustomerAddressEntity $addressEntity,
        Context $context
    ): AddressCheckPayload {
        try {
            $lang = $this->localeFetcher->fetchLocaleBySalesChannelId($salesChannelId, $context);
        } catch (\Exception $e) {
            $lang = 'de'; // set "DE" by default.
        }

        $countryId = $addressEntity->getCountryId();
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext($countryId, $context);
        $postCode = empty($addressEntity->getZipcode()) ? '' : $addressEntity->getZipcode();
        $cityName = $addressEntity->getCity();
        $streetFull = $addressEntity->getStreet();

        $subdivisionCode = null;
        if ($addressEntity->getCountryStateId() !== null) {
            $_subdivisionCode = $this->subdivisionCodeFetcher->fetchSubdivisionCodeByCountryStateId(
                $addressEntity->getCountryStateId(),
                $context
            );

            if ($_subdivisionCode !== null) {
                $subdivisionCode = $_subdivisionCode;
            }
        }
        if ($subdivisionCode === null && $this->countryHasStatesChecker->hasCountryStates($countryId, $context)) {
            // If a state was not assigned, but it would have been possible, check it.
            // Maybe subdivision code must be enriched.
            $subdivisionCode = '';
        }

        return new AddressCheckPayload(
            $lang,
            $countryCode,
            $postCode,
            $cityName,
            $streetFull,
            $subdivisionCode
        );
    }
}
