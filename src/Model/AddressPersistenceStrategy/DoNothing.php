<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;


final class DoNothing implements CustomerAddressPersistenceStrategy
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param string $normalizedStreetFull
     * @param string|null $normalizedAdditionalInfo
     * @param string $streetName
     * @param string $buildingNumber
     * @param CustomerAddressDTO $customerAddressDTO
     * @return void
     */
    public function execute(
        string $normalizedStreetFull,
        ?string $normalizedAdditionalInfo,
        string $streetName,
        string $buildingNumber,
        CustomerAddressDTO $customerAddressDTO
    ): void {
        // Crickets.
    }
}
