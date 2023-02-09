<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Entity\EnderecoAddressExtension;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class EnderecoAddressExtensionEntity extends Entity
{
    public const AMS_STATUS_CHECKED = 'checked';
    public const AMS_STATUS_NOT_CHECKED = 'not-checked';

    public const AMS_STATUSES_MAP = [
        self::AMS_STATUS_CHECKED,
        self::AMS_STATUS_NOT_CHECKED
    ];

    protected string $addressId;
    protected string $amsStatus;
    protected string $street;
    protected string $houseNumber;

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
        return $this->amsStatus === self::AMS_STATUS_CHECKED;
    }
}
