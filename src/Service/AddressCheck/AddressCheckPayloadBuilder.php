<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Endereco\Shopware6Client\Model\AddressCheckData;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Implements building address check payloads for the Endereco API.
 *
 * Transforms addresses into structured API payloads by handling:
 * - Country code resolution
 * - State/subdivision code processing
 * - Address component extraction and formatting
 *
 * @phpstan-type AddressDataStructure array{
 *   countryId: string,
 *   countryStateId?: string|null,
 *   zipcode: string,
 *   city: string,
 *   street: string,
 *   additionalAddressLine1: string|null,
 *   additionalAddressLine2: string|null
 * }
 */
final class AddressCheckPayloadBuilder implements AddressCheckPayloadBuilderInterface
{
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

    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;

    /**
     * Creates a new AddressCheckPayloadBuilder with required dependencies.
     *
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service for country code lookup
     * @param SubdivisionCodeFetcherInterface $subdivisionCodeFetcher Service for subdivision code resolution
     * @param CountryHasStatesCheckerInterface $countryHasStatesChecker Service for checking country subdivision support
     * @param AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker Checks if additional info is present
     */
    public function __construct(
        CountryCodeFetcherInterface $countryCodeFetcher,
        SubdivisionCodeFetcherInterface $subdivisionCodeFetcher,
        CountryHasStatesCheckerInterface $countryHasStatesChecker,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker
    ) {
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->subdivisionCodeFetcher = $subdivisionCodeFetcher;
        $this->countryHasStatesChecker = $countryHasStatesChecker;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
    }

    /**
     * @param AddressDataStructure $addressData Address data to transform
     * @param Context $context Shopware context
     * @return AddressCheckPayload Payload (not ready for API)
     */
    public function buildFromArray(
        array $addressData,
        Context $context
    ): AddressCheckPayload {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressData['countryId'],
            $context
        );

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressData, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressData, $context);
        }

        return new AddressCheckPayload(
            $countryCode,
            $addressData['zipcode'],
            $addressData['city'],
            $addressData['street'],
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds payload by extracting data from CustomerAddressEntity.
     *
     * @param CustomerAddressEntity $address Customer address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayload Payload (not ready for API)
     */
    public function buildFromCustomerAddress(
        CustomerAddressEntity $address,
        Context $context
    ): AddressCheckPayload {
        return $this->buildFromArray(
            [
                'countryId' => $address->getCountryId(),
                'countryStateId' => $address->getCountryStateId(),
                'zipcode' => $address->getZipcode() ?? '', // We dont support no zip code yet.
                'city' => $address->getCity(),
                'street' => $address->getStreet(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            ],
            $context
        );
    }

    /**
     * Builds payload by extracting data from OrderAddressEntity.
     *
     * @param OrderAddressEntity $address Order address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayload Payload (not ready for API)
     */
    public function buildFromOrderAddress(
        OrderAddressEntity $address,
        Context $context
    ): AddressCheckPayload {
        return $this->buildFromArray(
            [
                'countryId' => $address->getCountryId(),
                'countryStateId' => $address->getCountryStateId(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'street' => $address->getStreet(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
            ],
            $context
        );
    }

    /**
     * Extracts and processes subdivision code from address data.
     *
     * @param AddressDataStructure $addressData Address data in array, we just need "countryStateId" and "countryId"
     * @param Context $context
     * @return string|null Subdivision code or null if not applicable
     */
    private function getSubdivisionCodeFromArray(array $addressData, Context $context): ?string
    {
        if (!empty($addressData['countryStateId'])) {
            $subdivisionCode = $this->subdivisionCodeFetcher->fetchSubdivisionCodeByCountryStateId(
                $addressData['countryStateId'],
                $context
            );

            if ($subdivisionCode !== null) {
                return $subdivisionCode;
            }
        }

        if ($this->countryHasStatesChecker->hasCountryStates($addressData['countryId'], $context)) {
            return '';
        }

        return null;
    }

    /**
     * Retrieves the additional address information from the provided address data array.
     *
     * @param AddressDataStructure $addressData The address data array containing address components.
     * @param Context $context The Shopware context for current execution.
     * @return string The value of the additional address information, or an empty string if not available.
     */
    private function getAdditionalInfoFromArray(array $addressData, Context $context): string
    {
        $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
        return $addressData[$fieldName] ?? '';
    }
}
