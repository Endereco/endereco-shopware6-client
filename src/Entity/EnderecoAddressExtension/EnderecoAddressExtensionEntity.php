<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class EnderecoAddressExtensionEntity extends Entity
{
    public const AMS_STATUS_NOT_CHECKED = 'not-checked';
    public const AMS_STATUS_MINOR_CORRECTION = 'address_minor_correction';
    public const AMS_STATUS_SELECTED_AUTOMATICALLY = 'address_selected_automatically';
    public const AMS_STATUS_SELECTED_BY_CUSTOMER = 'address_selected_by_customer';
    public const CORRECT_STATUS_CODES = [
        'A1000',
        'address_correct',
        'country_code_correct',
        'postal_code_correct',
        'locality_correct',
        'street_name_correct',
        'building_number_correct',
    ];

    protected string $addressId;
    protected string $amsStatus = self::AMS_STATUS_NOT_CHECKED;
    protected int $amsTimestamp = 0;
    protected array $amsPredictions = [];

    protected bool $isPayPalAddress = false;
    protected string $street = '';
    protected string $houseNumber = '';

    protected ?CustomerAddressEntity $address = null;

    public function getAddressId(): string
    {
        return $this->addressId;
    }

    public function setAddressId(string $addressId): void
    {
        $this->addressId = $addressId;
    }

    public function getAmsStatus(): string
    {
        return $this->amsStatus;
    }

    public function setAmsStatus(string $amsStatus): void
    {
        $this->amsStatus = $amsStatus;
    }

    public function getAmsTimestamp(): int
    {
        return $this->amsTimestamp;
    }

    public function setAmsTimestamp(int $amsTimestamp): void
    {
        $this->amsTimestamp = $amsTimestamp;
    }

    public function getAmsPredictions(): array
    {
        return $this->amsPredictions;
    }

    public function setAmsPredictions(array $amsPredictions): void
    {
        $this->amsPredictions = $amsPredictions;
    }

    public function isPayPalAddress(): bool
    {
        return $this->isPayPalAddress;
    }

    public function setIsPayPalAddress(bool $isPayPalAddress): void
    {
        $this->isPayPalAddress = $isPayPalAddress;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(string $houseNumber): void
    {
        $this->houseNumber = $houseNumber;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function isAddressChecked(): bool
    {
        return $this->amsStatus !== self::AMS_STATUS_NOT_CHECKED;
    }

    public function needsCorrection(): bool
    {
        return
            $this->amsStatus === self::AMS_STATUS_NOT_CHECKED ||
            str_contains($this->amsStatus, 'needs_correction') ||
            str_contains($this->amsStatus, 'not_found');
    }

    public function hasMinorCorrection(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_MINOR_CORRECTION);
    }

    public function isSelectedAutomatically(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_SELECTED_AUTOMATICALLY);
    }

    public function isSelectedByCustomer(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_SELECTED_BY_CUSTOMER);
    }
}
