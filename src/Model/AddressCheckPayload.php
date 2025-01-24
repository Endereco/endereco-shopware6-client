<?php

namespace Endereco\Shopware6Client\Model;

/**
 * Represents a structured payload for address validation requests to the Endereco API.
 *
 * This class encapsulates all necessary address components required for validation:
 * - Language for localized responses
 * - Country code
 * - Postal/ZIP code
 * - City name
 * - Full street address
 * - Administrative subdivision code (state/province)
 *
 * The subdivision code has three possible states:
 * - null: The country has no states/subdivisions
 * - empty string: The country has states but none was selected
 * - string value: A specific state/subdivision was selected
 */
class AddressCheckPayload
{
    /**
     * The language code for API responses (e.g., 'de' for German)
     */
    private string $language;

    /**
     * The country code (ISO format) for the address
     */
    private string $country;

    /**
     * The postal/ZIP code of the address
     */
    private string $postCode;

    /**
     * The city name
     */
    private string $cityName;

    /**
     * The complete street address including house number
     */
    private string $streetFull;

    /**
     * The administrative subdivision (state/province) code
     * null: country has no states
     * empty string: country has states but none selected
     * string value: specific state code
     */
    private ?string $subdivisionCode;

    /**
     * Creates a new address check payload with all required components.
     *
     * @param string $language The language code for API responses
     * @param string $country The country code in ISO format
     * @param string $postCode The postal/ZIP code
     * @param string $cityName The city name
     * @param string $streetFull The complete street address
     * @param string|null $subdivisionCode The state/province code or null/empty string based on availability
     */
    public function __construct(
        string $language,
        string $country,
        string $postCode,
        string $cityName,
        string $streetFull,
        ?string $subdivisionCode
    ) {
        $this->language = $language;
        $this->country = $country;
        $this->postCode = $postCode;
        $this->cityName = $cityName;
        $this->streetFull = $streetFull;
        $this->subdivisionCode = $subdivisionCode;
    }

    /**
     * Converts the payload into an array format suitable for API submission.
     *
     * The subdivision code is only included in the output if it's not null,
     * allowing the API to distinguish between "no states exist" and "no state selected"
     * scenarios.
     *
     * @return array{
     *     language: string,
     *     country: string,
     *     postCode: string,
     *     cityName: string,
     *     streetFull: string,
     *     subdivisionCode?: string
     * } Array representation of the payload
     */
    public function data(): array
    {
        $data = [
            'language' => $this->language,
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
