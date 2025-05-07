<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Ensures street addresses are properly split into street name and building number
 *
 * This insurance class is responsible for taking full street addresses and splitting them
 * into their component parts (street name and building number) for more structured data storage
 * and better address validation.
 */
final class StreetIsSplitInsurance implements IntegrityInsurance
{
    private CountryCodeFetcherInterface $countryCodeFetcher;
    private EnderecoService $enderecoService;
    private AddressPersistenceStrategyProviderInterface $addressPersistenceStrategyProvider;
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private ProcessContextService $processContext;

    /**
     * Constructor for the StreetIsSplitInsurance class
     *
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service to retrieve country codes
     * @param EnderecoService $enderecoService Service to handle address processing via Endereco API
     * @param AddressPersistenceStrategyProviderInterface $addressPersistenceStrategyProvider Provider for address persistence strategies
     * @param AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker Service to check additional address fields availability
     */
    public function __construct(
        CountryCodeFetcherInterface $countryCodeFetcher,
        EnderecoService $enderecoService,
        AddressPersistenceStrategyProviderInterface $addressPersistenceStrategyProvider,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        ProcessContextService $processContext,
    ) {
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->enderecoService = $enderecoService;
        $this->addressPersistenceStrategyProvider = $addressPersistenceStrategyProvider;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->processContext = $processContext;
    }

    /**
     * Defines the execution priority of this insurance
     *
     * A lower value means higher priority. This insurance runs at a high priority (-10)
     * to ensure address components are available for other services that might need them.
     *
     * @return int The priority value
     */
    public static function getPriority(): int
    {
        return -10;
    }

    /**
     * Splits a customer's full street address into components using Endereco service and saves the result.
     *
     * Retrieves the address extension, extracts relevant data (country code, full street,
     * additional info), and if a street is not empty, splits it into normalized components.
     * Then applies the appropriate persistence strategy to save these components based on
     * system configuration.
     *
     * @param CustomerAddressEntity $addressEntity The address to process
     * @param Context $context The current context
     * @throws \RuntimeException If required address extension is missing
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        if (!$this->processContext->isStorefront()) {
            return;
        }

        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        list($countryCode, $fullStreet, $additionalInfo) = $this->getRelevantData($addressEntity, $context);

        if (empty($fullStreet)) {
            return;
        }

        list($normalizedFullStreet, $streetName, $buildingNumber, $normalizedAdditionalInfo) = $this->enderecoService->splitStreet(
            $fullStreet,
            $additionalInfo,
            $countryCode,
            $context,
            $this->enderecoService->fetchSalesChannelId($context)
        );

        $addressDTO = new CustomerAddressDTO(
            $addressEntity,
            $addressExtension
        );

        $addressPersistenceStrategy = $this->addressPersistenceStrategyProvider->getStrategy(
            $addressDTO,
            $context
        );

        $addressPersistenceStrategy->execute(
            $normalizedFullStreet,
            $normalizedAdditionalInfo,
            $streetName,
            $buildingNumber,
            $addressDTO
        );
    }

    /**
     * Extracts relevant address data needed for street splitting
     *
     * This method gathers the country code, full street address, and any additional address information
     * from the customer address entity. If the country is unknown, it defaults to Germany ('DE').
     * It also checks for and retrieves any additional address lines based on system configuration.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to extract data from
     * @param Context $context The current context
     *
     * @return array{0: string, 1: string, 2: string|null} Array containing [countryCode, fullStreet, additionalInfo]
     */
    private function getRelevantData(CustomerAddressEntity $addressEntity, Context $context): array
    {
        // If country is unknown, use Germany as default
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressEntity->getCountryId(),
            $context,
            'DE'
        );

        $fullStreet = $addressEntity->getStreet();

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
            $additionalFields = [
                'additionalAddressLine1' => $addressEntity->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $addressEntity->getAdditionalAddressLine2(),
            ];
            $additionalInfo = $additionalFields[$fieldName] ?? '';
        }

        return [$countryCode, $fullStreet, $additionalInfo];
    }
}