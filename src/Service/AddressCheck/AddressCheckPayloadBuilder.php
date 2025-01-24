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
 *   street: string
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

    /**
     * Creates a new AddressCheckPayloadBuilder with required dependencies.
     *
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service for country code lookup
     * @param SubdivisionCodeFetcherInterface $subdivisionCodeFetcher Service for subdivision code resolution
     * @param CountryHasStatesCheckerInterface $countryHasStatesChecker Service for checking country subdivision support
     */
    public function __construct(
        CountryCodeFetcherInterface $countryCodeFetcher,
        SubdivisionCodeFetcherInterface $subdivisionCodeFetcher,
        CountryHasStatesCheckerInterface $countryHasStatesChecker
    ) {
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->subdivisionCodeFetcher = $subdivisionCodeFetcher;
        $this->countryHasStatesChecker = $countryHasStatesChecker;
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

        $subdivisionCode = null;
        if (isset($addressData['countryStateId'])) {
            $subdivisionCode = $this->getSubdivisionCodeFromArray($addressData, $context);
        }

        return new AddressCheckPayload(
            $countryCode,
            $addressData['zipcode'],
            $addressData['city'],
            $addressData['street'],
            $subdivisionCode
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
                'street' => $address->getStreet()
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
                'street' => $address->getStreet()
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
}
