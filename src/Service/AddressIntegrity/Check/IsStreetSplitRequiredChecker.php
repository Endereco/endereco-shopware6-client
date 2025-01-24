<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Check;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Checks if addresses need street name and house number split based on country format
 */
final class IsStreetSplitRequiredChecker implements IsStreetSplitRequiredCheckerInterface
{
    /** @var EnderecoService */
    private EnderecoService $enderecoService;

    /** @var CountryCodeFetcherInterface */
    private CountryCodeFetcherInterface $countryCodeFetcher;

    /**
     * @param EnderecoService $enderecoService Handles address operations
     * @param CountryCodeFetcherInterface $countryCodeFetcher Gets country codes
     */
    public function __construct(
        EnderecoService $enderecoService,
        CountryCodeFetcherInterface $countryCodeFetcher
    ) {
        $this->enderecoService = $enderecoService;
        $this->countryCodeFetcher = $countryCodeFetcher;
    }

    /**
     * @inheritDoc
     */
    public function checkIfStreetSplitIsRequired(
        CustomerAddressEntity $addressEntity,
        EnderecoCustomerAddressExtensionEntity $addressExtension,
        Context $context
    ): bool {
        // Construct the expected full street string
        $expectedFullStreet = $this->enderecoService->buildFullStreet(
            $addressExtension->getStreet(),
            $addressExtension->getHouseNumber(),
            $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressEntity->getCountryId(),
                $context,
                'DE'
            )
        );

        // Fetch the current full street string from the address entity
        $currentFullStreet = $addressEntity->getStreet();

        // Compare the expected and current full street strings and return the result
        return $expectedFullStreet !== $currentFullStreet;
    }

    /**
     * @inheritDoc
     */
    public function checkIfOrderAddressStreetSplitIsRequired(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension,
        Context $context
    ): bool {
        // Construct the expected full street string
        $expectedFullStreet = $this->enderecoService->buildFullStreet(
            $addressExtension->getStreet(),
            $addressExtension->getHouseNumber(),
            $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressEntity->getCountryId(),
                $context,
                'DE'
            )
        );

        // Fetch the current full street string from the address entity
        $currentFullStreet = $addressEntity->getStreet();

        // Compare the expected and current full street strings and return the result
        return $expectedFullStreet !== $currentFullStreet;
    }
}
