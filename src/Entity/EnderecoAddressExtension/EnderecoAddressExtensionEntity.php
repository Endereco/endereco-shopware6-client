<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class EnderecoAddressExtensionEntity.
 *
 * @author Michal Daniel
 * @author Ilja Weber
 *
 * This class provides a custom entity to manage extensions for the Address object in the context
 * of the Endereco plugin.
 */
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

    /** @var string The ID of the associated address. */
    protected string $addressId;

    /** @var string The status of the AMS (Address Management System) check. */
    protected string $amsStatus = self::AMS_STATUS_NOT_CHECKED;

    /** @var int The timestamp of the last AMS check. */
    protected int $amsTimestamp = 0;

    /** @var array<array<string, string>> The predictions of the AMS check. */
    protected array $amsPredictions = [];

    /** @var bool Indicates if the address is a PayPal address. */
    protected bool $isPayPalAddress = false;

    /** @var bool Indicates if the address is an Amazon Pay address. */
    protected bool $isAmazonPayAddress = false;

    /** @var string The street part of the address. */
    protected string $street = '';

    /** @var string The house number part of the address. */
    protected string $houseNumber = '';

    /** @var ?CustomerAddressEntity The associated address entity. */
    protected ?CustomerAddressEntity $address = null;

    /**
     * Get address ID.
     *
     * @return string The ID of the associated address.
     */
    public function getAddressId(): string
    {
        return $this->addressId;
    }

    /**
     * Set address ID.
     *
     * @param string $addressId The ID of the associated address.
     */
    public function setAddressId(string $addressId): void
    {
        $this->addressId = $addressId;
    }

    /**
     * Get the status of the AMS check.
     *
     * @return string The status of the AMS check.
     */
    public function getAmsStatus(): string
    {
        return $this->amsStatus;
    }

    /**
     * Set the status of the AMS check.
     *
     * @param string $amsStatus The status of the AMS check.
     */
    public function setAmsStatus(string $amsStatus): void
    {
        $this->amsStatus = $amsStatus;
    }

    /**
     * Get the timestamp of the last AMS check.
     *
     * @return int The timestamp of the last AMS check.
     */
    public function getAmsTimestamp(): int
    {
        return $this->amsTimestamp;
    }

    /**
     * Set the timestamp of the last AMS check.
     *
     * @param int $amsTimestamp The timestamp of the last AMS check.
     */
    public function setAmsTimestamp(int $amsTimestamp): void
    {
        $this->amsTimestamp = $amsTimestamp;
    }

    /**
     * Get the predictions of the AMS check.
     *
     * @return array<array<string, string>> The predictions of the AMS check.
     */
    public function getAmsPredictions(): array
    {
        return $this->amsPredictions;
    }

    /**
     * Set the predictions of the AMS check.
     *
     * @param array<array<string, string>> $amsPredictions The predictions of the AMS check.
     */
    public function setAmsPredictions(array $amsPredictions): void
    {
        $this->amsPredictions = $amsPredictions;
    }

    /**
     * Check if the address is a PayPal address.
     *
     * @return bool True if the address is a PayPal address, false otherwise.
     */
    public function isPayPalAddress(): bool
    {
        return $this->isPayPalAddress;
    }

    /**
     * Set the flag indicating whether the address is a PayPal address.
     *
     * @param bool $isPayPalAddress True if the address is a PayPal address, false otherwise.
     */
    public function setIsPayPalAddress(bool $isPayPalAddress): void
    {
        $this->isPayPalAddress = $isPayPalAddress;
    }

    /**
     * Check if the address is an Amazon Pay address.
     *
     * @return bool True if the address is an Amazon Pay address, false otherwise.
     */
    public function isAmazonPayAddress(): bool
    {
        return $this->isAmazonPayAddress;
    }

    /**
     * Set the flag indicating whether the address is a Amazon Pay address.
     *
     * @param bool $isAmazonPayAddress True if the address is an Amazon Pay address, false otherwise.
     */
    public function setIsAmazonPayAddress(bool $isAmazonPayAddress): void
    {
        $this->isAmazonPayAddress = $isAmazonPayAddress;
    }

    /**
     * Get the street part of the address.
     *
     * @return string The street part of the address.
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * Set the street part of the address.
     *
     * @param string $street The street part of the address.
     */
    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    /**
     * Get the house number part of the address.
     *
     * @return string The house number part of the address.
     */
    public function getHouseNumber(): string
    {
        return $this->houseNumber ?? '';
    }

    /**
     * Set the house number part of the address.
     *
     * @param string $houseNumber The house number part of the address.
     */
    public function setHouseNumber(string $houseNumber): void
    {
        $this->houseNumber = $houseNumber;
    }

    /**
     * Get the associated address entity.
     *
     * @return CustomerAddressEntity|null The associated address entity, or null if none is set.
     */
    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    /**
     * Set the associated address entity.
     *
     * @param CustomerAddressEntity|null $address The associated address entity to set.
     */
    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    /**
     * Check if the address has been checked by the AMS.
     *
     * @return bool True if the address has been checked, false otherwise.
     */
    public function isAddressChecked(): bool
    {
        return $this->amsStatus !== self::AMS_STATUS_NOT_CHECKED;
    }

    /**
     * Check if the address needs correction.
     *
     * @return bool True if the address needs correction, false otherwise.
     */
    public function needsCorrectionInFrontend(): bool
    {
        return
            str_contains($this->amsStatus, 'address_multiple_variants') ||
            str_contains($this->amsStatus, 'address_needs_correction') ||
            str_contains($this->amsStatus, 'address_not_found');
    }

    /**
     * Check if the address has minor corrections.
     *
     * @return bool True if the address has minor corrections, false otherwise.
     */
    public function hasMinorCorrection(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_MINOR_CORRECTION);
    }

    /**
     * Check if the address was selected automatically by the AMS.
     *
     * @return bool True if the address was selected automatically, false otherwise.
     */
    public function isSelectedAutomatically(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_SELECTED_AUTOMATICALLY);
    }

    /**
     * Check if the address was selected by the customer in the AMS.
     *
     * @return bool True if the address was selected by the customer, false otherwise.
     */
    public function isSelectedByCustomer(): bool
    {
        return str_contains($this->amsStatus, self::AMS_STATUS_SELECTED_BY_CUSTOMER);
    }
}
