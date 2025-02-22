<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;


final class PersistOnlyExtensionFields implements CustomerAddressPersistenceStrategy
{
    use CustomerAddressExtensionPersistenceStrategyTrait;

    private EntityRepository $extensionRepository;
    private Context $context;

    public function __construct(
        EntityRepository $customerAddressExtensionRepository,
        Context $context
    )
    {
        $this->extensionRepository = $customerAddressExtensionRepository;
        $this->context = $context;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
        $addressExtension = $customerAddressDTO->getEnderecoCustomerAddressExtension();
        if ($addressExtension === null) {
            throw new \RuntimeException('Address extension cannot be null');
        }

        $this->maybeUpdateExtension(
            $streetName,
            $buildingNumber,
            $addressExtension
        );
    }
}
