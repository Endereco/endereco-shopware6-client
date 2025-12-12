<?php

namespace Endereco\Shopware6Client\Model;

/**
 * Represents an address check payload using combined street format.
 *
 * This class encapsulates address components where the street address
 * is provided as a single field containing both street name and house number.
 * This is the traditional format used by the plugin.
 */
class AddressCheckPayloadCombined implements AddressCheckPayloadInterface
{
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
     * Additional info found in one of the two possible fields in the address form.
     */
    private ?string $additionalInfo;

    /**
     * Creates a new combined format address check payload.
     *
     * @param string $country The country code in ISO format
     * @param string $postCode The postal/ZIP code
     * @param string $cityName The city name
     * @param string $streetFull The complete street address including house number
     * @param string|null $subdivisionCode The state/province code or null/empty string based on availability
     * @param string|null $additionalInfo The additional info sometimes provided in the forms
     */
    public function __construct(
        string $country,
        string $postCode,
        string $cityName,
        string $streetFull,
        ?string $subdivisionCode,
        ?string $additionalInfo
    ) {
        $this->country = $country;
        $this->postCode = $postCode;
        $this->cityName = $cityName;
        $this->streetFull = $streetFull;
        $this->subdivisionCode = $subdivisionCode;
        $this->additionalInfo = $additionalInfo;
    }

    /**
     * Converts the payload into an array format suitable for API submission.
     *
     * The subdivision code is only included in the output if it's not null,
     * allowing the API to distinguish between "no states exist" and "no state selected"
     * scenarios.
     *
     * @return array<string, string> Array representation of the payload
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

        if ($this->additionalInfo !== null) {
            $data['additionalInfo'] = $this->additionalInfo;
        }

        // Ensure the same order of fields.
        ksort($data);

        return $data;
    }

    /**
     * Converts payload to JSON string with proper UTF-8 handling
     *
     * @throws \JsonException On encoding failure
     * @return string JSON representation of address data
     */
    public function toJSON(): string
    {
        return json_encode($this->data(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Returns the format type of this payload.
     *
     * @return string Always returns FORMAT_COMBINED for this implementation
     */
    public function getFormatType(): string
    {
        return AddressCheckPayloadInterface::FORMAT_COMBINED;
    }
}
