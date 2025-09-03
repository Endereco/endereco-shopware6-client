<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;

/**
 * Data structure for customer address update payloads with automatic field length normalization
 *
 * Use this instead of building arrays manually to ensure all address fields comply with
 * Shopware 6 CustomerAddress entity constraints and prevent database errors.
 * Only contains address-related fields and Endereco extension data.
 *
 * Example usage:
 * $payload = new CustomerAddressUpdatePayload($addressId)
 *     ->setStreet('Some very long street name that might exceed database limits')
 *     ->setCity('Berlin')
 *     ->setZipcode('12345')
 *     ->setAdditionalAddressLine1('Apartment 4B');
 *
 * $repository->update([$payload->toArray()], $context);
 */
class CustomerAddressUpdatePayload
{
    // Shopware CustomerAddress field length constraints
    private const MAX_LENGTH_ZIPCODE = 50;
    private const MAX_LENGTH_CITY = 70;
    private const MAX_LENGTH_DEFAULT_STRING = 255; // Default StringField length

    /** @var array<string, mixed> */
    private array $data = [];

    private ?EnderecoExtensionData $enderecoExtensionData = null;

    public function __construct(string $addressId)
    {
        $this->data[CustomerAddressField::ID] = $addressId;
    }

    /**
     * Set street field (automatically normalized to max 255 chars)
     */
    public function setStreet(?string $street): self
    {
        $this->data[CustomerAddressField::STREET] = $this->normalizeString($street, self::MAX_LENGTH_DEFAULT_STRING);
        return $this;
    }

    /**
     * Set zipcode field (automatically normalized to max 50 chars)
     */
    public function setZipcode(?string $zipcode): self
    {
        $this->data[CustomerAddressField::ZIPCODE] = $this->normalizeString($zipcode, self::MAX_LENGTH_ZIPCODE);
        return $this;
    }

    /**
     * Set city field (automatically normalized to max 70 chars)
     */
    public function setCity(?string $city): self
    {
        $this->data[CustomerAddressField::CITY] = $this->normalizeString($city, self::MAX_LENGTH_CITY);
        return $this;
    }

    /**
     * Set country ID
     */
    public function setCountryId(?string $countryId): self
    {
        $this->data[CustomerAddressField::COUNTRY_ID] = $countryId;
        return $this;
    }

    /**
     * Set country state ID
     */
    public function setCountryStateId(?string $countryStateId): self
    {
        $this->data[CustomerAddressField::COUNTRY_STATE_ID] = $countryStateId;
        return $this;
    }



    /**
     * Set additional address line 1 (automatically normalized to max 255 chars)
     */
    public function setAdditionalAddressLine1(?string $additionalAddressLine1): self
    {
        $this->data[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1] = $this->normalizeString(
            $additionalAddressLine1,
            self::MAX_LENGTH_DEFAULT_STRING
        );
        return $this;
    }

    /**
     * Set additional address line 2 (automatically normalized to max 255 chars)
     */
    public function setAdditionalAddressLine2(?string $additionalAddressLine2): self
    {
        $this->data[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2] = $this->normalizeString(
            $additionalAddressLine2,
            self::MAX_LENGTH_DEFAULT_STRING
        );
        return $this;
    }

    /**
     * Set Endereco extension data with type safety
     */
    public function setEnderecoExtension(EnderecoExtensionData $extensionData): self
    {
        // Store the original object
        $this->enderecoExtensionData = $extensionData;

        if (!isset($this->data[CustomerAddressField::EXTENSIONS])) {
            $this->data[CustomerAddressField::EXTENSIONS] = [];
        }

        $this->data[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION] =
            $extensionData->toArray();

        return $this;
    }

    /**
     * Get the ID field
     */
    public function getId(): ?string
    {
        return $this->data[CustomerAddressField::ID] ?? null;
    }

    /**
     * Get the street field
     */
    public function getStreet(): ?string
    {
        return $this->data[CustomerAddressField::STREET] ?? null;
    }

    /**
     * Get the zipcode field
     */
    public function getZipcode(): ?string
    {
        return $this->data[CustomerAddressField::ZIPCODE] ?? null;
    }

    /**
     * Get the city field
     */
    public function getCity(): ?string
    {
        return $this->data[CustomerAddressField::CITY] ?? null;
    }

    /**
     * Get the country ID field
     */
    public function getCountryId(): ?string
    {
        return $this->data[CustomerAddressField::COUNTRY_ID] ?? null;
    }

    /**
     * Get the country state ID field
     */
    public function getCountryStateId(): ?string
    {
        return $this->data[CustomerAddressField::COUNTRY_STATE_ID] ?? null;
    }


    /**
     * Get the additional address line 1 field
     */
    public function getAdditionalAddressLine1(): ?string
    {
        return $this->data[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1] ?? null;
    }

    /**
     * Get the additional address line 2 field
     */
    public function getAdditionalAddressLine2(): ?string
    {
        return $this->data[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2] ?? null;
    }

    /**
     * Get the extensions array
     *
     * @return array<string, mixed>|null
     */
    public function getExtensions(): ?array
    {
        return $this->data[CustomerAddressField::EXTENSIONS] ?? null;
    }

    /**
     * Get the Endereco extension data
     */
    public function getEnderecoExtension(): ?EnderecoExtensionData
    {
        return $this->enderecoExtensionData;
    }

    /**
     * Get the normalized payload array for use with repository updates
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }


    /**
     * Normalize string field to maximum length with smart word boundary trimming
     */
    private function normalizeString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        if (mb_strlen($stringValue) <= $maxLength) {
            return $stringValue;
        }

        // Trim to max length, preferring word boundaries when possible
        $trimmed = mb_substr($stringValue, 0, $maxLength);

        // If we're in the middle of a word and there's a space nearby, trim to the last space
        if (mb_strlen($stringValue) > $maxLength) {
            $lastSpace = mb_strrpos($trimmed, ' ');
            if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
                $trimmed = mb_substr($trimmed, 0, $lastSpace);
            }
        }

        return rtrim($trimmed);
    }
}
