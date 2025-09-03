<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

/**
 * Type-safe data structure for Endereco extension fields
 *
 * This class defines the exact structure of extension data to satisfy PHPStan requirements
 * and provide better type safety for Endereco extension fields.
 */
final class EnderecoExtensionData
{
    private const MAX_STRING_LENGTH = 255;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct()
    {
    }

    public function setAddressId(?string $addressId): self
    {
        $this->data[EnderecoExtensionField::ADDRESS_ID] = $addressId;
        return $this;
    }

    public function setStreet(?string $street): self
    {
        $this->data[EnderecoExtensionField::STREET] = $street !== null
            ? $this->normalizeString($street, self::MAX_STRING_LENGTH)
            : null;
        return $this;
    }

    public function setHouseNumber(?string $houseNumber): self
    {
        $this->data[EnderecoExtensionField::HOUSE_NUMBER] = $houseNumber !== null
            ? $this->normalizeString($houseNumber, self::MAX_STRING_LENGTH)
            : null;
        return $this;
    }

    public function setAmsStatus(?string $amsStatus): self
    {
        // LongTextField - no normalization needed
        $this->data[EnderecoExtensionField::AMS_STATUS] = $amsStatus;
        return $this;
    }

    public function setAmsRequestPayload(?string $amsRequestPayload): self
    {
        // LongTextField - no normalization needed
        $this->data[EnderecoExtensionField::AMS_REQUEST_PAYLOAD] = $amsRequestPayload;
        return $this;
    }

    public function setAmsTimestamp(?int $amsTimestamp): self
    {
        $this->data[EnderecoExtensionField::AMS_TIMESTAMP] = $amsTimestamp;
        return $this;
    }

    /**
     * @param array<int, mixed>|null $amsPredictions
     */
    public function setAmsPredictions(?array $amsPredictions): self
    {
        $this->data[EnderecoExtensionField::AMS_PREDICTIONS] = $amsPredictions;
        return $this;
    }

    public function setIsPayPalAddress(?bool $isPayPalAddress): self
    {
        $this->data[EnderecoExtensionField::IS_PAYPAL_ADDRESS] = $isPayPalAddress;
        return $this;
    }

    public function setIsAmazonPayAddress(?bool $isAmazonPayAddress): self
    {
        $this->data[EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS] = $isAmazonPayAddress;
        return $this;
    }

    // Getters
    public function getAddressId(): ?string
    {
        return $this->data[EnderecoExtensionField::ADDRESS_ID] ?? null;
    }

    public function getStreet(): ?string
    {
        return $this->data[EnderecoExtensionField::STREET] ?? null;
    }

    public function getHouseNumber(): ?string
    {
        return $this->data[EnderecoExtensionField::HOUSE_NUMBER] ?? null;
    }

    public function getAmsStatus(): ?string
    {
        return $this->data[EnderecoExtensionField::AMS_STATUS] ?? null;
    }

    public function getAmsRequestPayload(): ?string
    {
        return $this->data[EnderecoExtensionField::AMS_REQUEST_PAYLOAD] ?? null;
    }

    public function getAmsTimestamp(): ?int
    {
        return $this->data[EnderecoExtensionField::AMS_TIMESTAMP] ?? null;
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getAmsPredictions(): ?array
    {
        return $this->data[EnderecoExtensionField::AMS_PREDICTIONS] ?? null;
    }

    public function getIsPayPalAddress(): ?bool
    {
        return $this->data[EnderecoExtensionField::IS_PAYPAL_ADDRESS] ?? null;
    }

    public function getIsAmazonPayAddress(): ?bool
    {
        return $this->data[EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS] ?? null;
    }

    /**
     * Convert to array format for repository operations
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Normalize string to maximum length with word boundary trimming
     */
    private function normalizeString(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        // Trim to max length, preferring word boundaries when possible
        $trimmed = mb_substr($value, 0, $maxLength);

        // If we're in the middle of a word and there's a space nearby, trim to the last space
        $lastSpace = mb_strrpos($trimmed, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
            $trimmed = mb_substr($trimmed, 0, $lastSpace);
        }

        return rtrim($trimmed);
    }
}
