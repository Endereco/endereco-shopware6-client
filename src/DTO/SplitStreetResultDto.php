<?php

namespace Endereco\Shopware6Client\DTO;

/**
 * Data Transfer Object for a street address split into its components.
 *
 * This class holds the results of a street address parsing operation,
 * separating a full street address into street name, building number,
 * and optional additional information.
 */
final class SplitStreetResultDto
{
    private string $fullStreet;
    private string $streetName;
    private string $buildingNumber;
    private ?string $additionalInfo;

    public function __construct(
        string $fullStreet,
        string $streetName,
        string $buildingNumber,
        ?string $additionalInfo
    ) {
        $this->fullStreet = $fullStreet;
        $this->streetName = $streetName;
        $this->buildingNumber = $buildingNumber;
        $this->additionalInfo = $additionalInfo;
    }

    public function getFullStreet(): string
    {
        return $this->fullStreet;
    }

    public function getStreetName(): string
    {
        return $this->streetName;
    }

    public function getBuildingNumber(): string
    {
        return $this->buildingNumber;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    /**
     * Converts the DTO to an associative array.
     *
     * @return array<string, string|null> The DTO data as an array
     */
    public function toArray(): array
    {
        return [
            'fullStreet' => $this->fullStreet,
            'streetName' => $this->streetName,
            'buildingNumber' => $this->buildingNumber,
            'additionalInfo' => $this->additionalInfo,
        ];
    }
}
