<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Ensures street addresses are properly split into street name and building number
 */
final class StreetIsSplitInsurance implements IntegrityInsurance
{
    private EntityRepository $addressExtensionRepository;
    private IsStreetSplitRequiredCheckerInterface $isStreetSplitRequiredChecker;
    private CountryCodeFetcherInterface $countryCodeFetcher;
    private EnderecoService $enderecoService;

    /**
     * @param IsStreetSplitRequiredCheckerInterface $isStreetSplitRequiredChecker Service to check if street splitting is needed
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service to fetch country codes
     * @param EnderecoService $enderecoService Service for address operations
     * @param EntityRepository $addressExtensionRepository Repository for address extensions
     */
    public function __construct(
        IsStreetSplitRequiredCheckerInterface $isStreetSplitRequiredChecker,
        CountryCodeFetcherInterface $countryCodeFetcher,
        EnderecoService $enderecoService,
        EntityRepository $addressExtensionRepository
    ) {
        $this->isStreetSplitRequiredChecker = $isStreetSplitRequiredChecker;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->enderecoService = $enderecoService;
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    /**
     * Returns priority for insurance execution order
     */
    public static function getPriority(): int
    {
        return -10;
    }

    /**
     * Splits full street address into street name and building number if needed
     *
     * Checks if splitting is required by comparing current state with extension data.
     * If needed, splits using country-specific rules (defaults to DE) and updates extension.
     *
     * @param OrderAddressEntity $addressEntity Address to process
     * @param Context $context Shopware context
     * @throws \RuntimeException If address extension is missing
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void
    {
        /** @var EnderecoOrderAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $fullStreet = $addressEntity->getStreet();
        if (empty($fullStreet)) {
            return;
        }

        $isStreetSplitRequired = $this->isStreetSplitRequiredChecker->checkIfOrderAddressStreetSplitIsRequired(
            $addressEntity,
            $addressExtension,
            $context
        );

        if (!$isStreetSplitRequired) {
            return;
        }

        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressEntity->getCountryId(),
            $context,
            'DE'
        );

        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);
        list($streetName, $buildingNumber) = $this->enderecoService->splitStreet(
            $fullStreet,
            $countryCode,
            $context,
            $salesChannelId
        );

        $this->addressExtensionRepository->upsert(
            [
                [
                    'addressId' => $addressEntity->getId(),
                    'street' => $streetName,
                    'houseNumber' => $buildingNumber
                ]
            ],
            $context
        );

        $addressExtension->setStreet($streetName);
        $addressExtension->setHouseNumber($buildingNumber);
    }
}
