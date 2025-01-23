<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Endereco\Shopware6Client\Model\AddressCheckData;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
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
        $lang = $this->getLang($salesChannelId, $context);
        $countryCode = $this->getCountryCode($addressEntity, $context);
        $postCode = $this->getPostCode($addressEntity);
        $cityName = $this->getCityName($addressEntity);
        $streetFull = $this->getStreetFull($addressEntity);
        $subdivisionCode = $this->getSubdivisionCode($addressEntity, $context);

        return new AddressCheckPayload(
            $lang,
            $countryCode,
            $postCode,
            $cityName,
            $streetFull,
            $subdivisionCode
        );
    }

    public function buildAddressCheckPayloadWithoutLanguage(
        $addressEntity,
        Context $context
    ): AddressCheckData {
        $countryCode = $this->getCountryCode($addressEntity, $context);
        $postCode = $this->getPostCode($addressEntity);
        $cityName = $this->getCityName($addressEntity);
        $streetFull = $this->getStreetFull($addressEntity);
        $subdivisionCode = $this->getSubdivisionCode($addressEntity, $context);

        return new AddressCheckData(
            $countryCode,
            $postCode,
            $cityName,
            $streetFull,
            $subdivisionCode
        );
    }

    private function getLang(string $salesChannelId, Context $context): string
    {
        try {
            return $this->localeFetcher->fetchLocaleBySalesChannelId($salesChannelId, $context);
        } catch (\Exception $e) {
            return 'de'; // set "de" by default.
        }
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @param Context $context
     * @return string
     */
    private function getCountryCode($addressEntity, Context $context): string
    {
        $countryId = $addressEntity->getCountryId();

        return $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext($countryId, $context);
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @return string
     */
    private function getPostCode($addressEntity): string
    {
        return empty($addressEntity->getZipcode()) ? '' : $addressEntity->getZipcode();
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @return string
     */
    private function getCityName($addressEntity): string
    {
        return $addressEntity->getCity();
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @return string
     */
    private function getStreetFull($addressEntity): string
    {
        return $addressEntity->getStreet();
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @param Context $context
     * @return string|null
     */
    private function getSubdivisionCode($addressEntity, Context $context): ?string
    {
        if ($addressEntity->getCountryStateId() !== null) {
            $subdivisionCode = $this->subdivisionCodeFetcher->fetchSubdivisionCodeByCountryStateId(
                $addressEntity->getCountryStateId(),
                $context
            );

            if ($subdivisionCode !== null) {
                return $subdivisionCode;
            }
        }

        $countryId = $addressEntity->getCountryId();
        if ($this->countryHasStatesChecker->hasCountryStates($countryId, $context)) {
            // If a state was not assigned, but it would have been possible, check it.
            // Maybe subdivision code must be enriched.
            return '';
        }

        return null;
    }
}
