<?php

namespace Endereco\Shopware6Client\Model;

/**
 * Represents structured data for an address check operation.
 *
 * This class encapsulates all necessary components of an address for validation,
 * including country, postal code, city, street information and optional subdivision code.
 * It provides methods for creating instances from payloads and converting to array format.
 *
 * @phpstan-type AddressCheckDataData array{
 *     country: string,
 *     postCode: string,
 *     cityName: string,
 *     streetFull: string,
 *     subdivisionCode?: string
 * }
 *
 * @package Endereco\Shopware6Client\Model
 */
class AddressCheckData
{
    /**
     * Two-letter country code
     *
     * @var string
     */
    private string $country;

    /**
     * Postal/ZIP code
     *
     * @var string
     */
    private string $postCode;

    /**
     * Name of the city
     *
     * @var string
     */
    private string $cityName;

    /**
     * Complete street address including house number
     *
     * @var string
     */
    private string $streetFull;

    /**
     * Subdivision (state/province) code with special handling:
     * null: no country state was chosen and the country has none
     * empty string: no country state was chosen but the country has one
     * non-empty string: specific country state was chosen
     *
     * @var string|null
     */
    private ?string $subdivisionCode;

    /**
     * Creates a new AddressCheckData instance.
     *
     * @param string $country Two-letter country code
     * @param string $postCode Postal/ZIP code
     * @param string $cityName Name of the city
     * @param string $streetFull Complete street address
     * @param string|null $subdivisionCode Subdivision code with special handling:
     *                                     null: country has no states
     *                                     empty string: country has states but none chosen
     *                                     non-empty string: specific state chosen
     */
    public function __construct(
        string $country,
        string $postCode,
        string $cityName,
        string $streetFull,
        ?string $subdivisionCode
    ) {
        $this->country = $country;
        $this->postCode = $postCode;
        $this->cityName = $cityName;
        $this->streetFull = $streetFull;
        $this->subdivisionCode = $subdivisionCode;
    }

    /**
     * Creates an instance from an AddressCheckPayload object.
     *
     * Extracts address data from the payload and constructs a new AddressCheckData instance.
     *
     * @param AddressCheckPayload $addressCheckPayload The payload containing address data
     * @return self New instance with data from payload
     */
    public static function fromAddressCheckPayload(AddressCheckPayload $addressCheckPayload): self
    {
        $addressCheckPayloadData = $addressCheckPayload->data();

        return new self(
            $addressCheckPayloadData['country'],
            $addressCheckPayloadData['postCode'],
            $addressCheckPayloadData['cityName'],
            $addressCheckPayloadData['streetFull'],
            $addressCheckPayloadData['subdivisionCode'] ?? null
        );
    }

    /**
     * Converts the address data to an array format.
     *
     * Creates an array representation of the address data, conditionally including
     * the subdivision code only if it is not null.
     *
     * @return AddressCheckDataData Array containing address components, with optional subdivisionCode
     */
    public function data(): array
    {
        $data = [
            'country' => $this->country,
            'postCode' => $this->postCode,
            'cityName' => $this->cityName,
            'streetFull' => $this->streetFull,
        ];

        if ($this->subdivisionCode !== null) {
            $data['subdivisionCode'] = $this->subdivisionCode;
        }

        return $data;
    }
}
