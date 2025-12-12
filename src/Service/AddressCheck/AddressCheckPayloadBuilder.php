<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Endereco\Shopware6Client\Model\AddressCheckPayloadInterface;
use Endereco\Shopware6Client\Model\AddressCheckPayloadCombined;
use Endereco\Shopware6Client\Model\AddressCheckPayloadSplit;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionCollection;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionCollection;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Model\AddressCheckData;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Implements building address check payloads for the Endereco API.
 *
 * Transforms addresses into structured API payloads by handling:
 * - Country code resolution
 * - State/subdivision code processing
 * - Address component extraction and formatting
 *
 * @phpstan-type AddressDataStructure array{
 *   countryId: string,
 *   countryStateId?: string|null,
 *   zipcode: string,
 *   city: string,
 *   street: string,
 *   additionalAddressLine1: string|null,
 *   additionalAddressLine2: string|null
 * }
 */
final class AddressCheckPayloadBuilder implements AddressCheckPayloadBuilderInterface
{
    /**
     * Service for resolving country codes
     */
    private CountryCodeFetcherInterface $countryCodeFetcher;

    /**
     * Service for fetching subdivision codes
     */
    private SubdivisionCodeFetcherInterface $subdivisionCodeFetcher;

    /**
     * Service for checking if a country has states/subdivisions
     */
    private CountryHasStatesCheckerInterface $countryHasStatesChecker;

    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;

    /**
     * Service for accessing system configuration
     */
    private SystemConfigService $systemConfigService;

    /**
     * Service for splitting street addresses
     */
    private StreetSplitterInterface $streetSplitter;

    /**
     * Repository for customer address extensions
     * @var EntityRepository<EnderecoCustomerAddressExtensionCollection>
     */
    private EntityRepository $customerAddressExtensionRepository;

    /**
     * Repository for order address extensions
     * @var EntityRepository<EnderecoOrderAddressExtensionCollection>
     */
    private EntityRepository $orderAddressExtensionRepository;

    /**
     * Service for getting sales channel ID from context
     */
    private EnderecoService $enderecoService;
    
    /**
     * Creates a new AddressCheckPayloadBuilder with required dependencies.
     *
     * @param CountryCodeFetcherInterface $countryCodeFetcher Service for country code lookup
     * @param SubdivisionCodeFetcherInterface $subdivisionCodeFetcher Service for subdivision code resolution
     * @param CountryHasStatesCheckerInterface $countryHasStatesChecker Service for checking country subdivision support
     * @param AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker Checks if additional info is present
     */
    /**
     * @param EntityRepository<EnderecoCustomerAddressExtensionCollection> $customerAddressExtensionRepository
     * @param EntityRepository<EnderecoOrderAddressExtensionCollection> $orderAddressExtensionRepository
     */
    public function __construct(
        CountryCodeFetcherInterface $countryCodeFetcher,
        SubdivisionCodeFetcherInterface $subdivisionCodeFetcher,
        CountryHasStatesCheckerInterface $countryHasStatesChecker,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        SystemConfigService $systemConfigService,
        StreetSplitterInterface $streetSplitter,
        EntityRepository $customerAddressExtensionRepository,
        EntityRepository $orderAddressExtensionRepository,
        EnderecoService $enderecoService
    ) {
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->subdivisionCodeFetcher = $subdivisionCodeFetcher;
        $this->countryHasStatesChecker = $countryHasStatesChecker;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->systemConfigService = $systemConfigService;
        $this->streetSplitter = $streetSplitter;
        $this->customerAddressExtensionRepository = $customerAddressExtensionRepository;
        $this->orderAddressExtensionRepository = $orderAddressExtensionRepository;
        $this->enderecoService = $enderecoService;
    }

    /**
     * @param AddressDataStructure $addressData Address data to transform
     * @param Context $context Shopware context
     * @return AddressCheckPayloadInterface Payload (not ready for API)
     */
    public function buildFromArray(
        array $addressData,
        Context $context
    ): AddressCheckPayloadInterface {
        if ($this->shouldUseSplitFormat($context)) {
            return $this->buildSplitPayload($addressData, $context);
        }
        
        return $this->buildCombinedPayload($addressData, $context);
    }

    /**
     * Builds payload by extracting data from CustomerAddressEntity.
     *
     * @param CustomerAddressEntity $address Customer address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayload Payload (not ready for API)
     */
    public function buildFromCustomerAddress(
        CustomerAddressEntity $address,
        Context $context
    ): AddressCheckPayloadInterface {
        if ($this->shouldUseSplitFormat($context)) {
            return $this->buildSplitPayloadFromCustomerAddress($address, $context);
        }
        
        return $this->buildCombinedPayloadFromCustomerAddress($address, $context);
    }

    /**
     * Builds payload by extracting data from OrderAddressEntity.
     *
     * @param OrderAddressEntity $address Order address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayloadInterface Payload (not ready for API)
     */
    public function buildFromOrderAddress(
        OrderAddressEntity $address,
        Context $context
    ): AddressCheckPayloadInterface {
        if ($this->shouldUseSplitFormat($context)) {
            return $this->buildSplitPayloadFromOrderAddress($address, $context);
        }
        
        return $this->buildCombinedPayloadFromOrderAddress($address, $context);
    }

    /**
     * Extracts and processes subdivision code from address data.
     *
     * @param AddressDataStructure $addressData Address data in array, we just need "countryStateId" and "countryId"
     * @param Context $context
     * @return string|null Subdivision code or null if not applicable
     */
    private function getSubdivisionCodeFromArray(array $addressData, Context $context): ?string
    {
        if (!empty($addressData['countryStateId'])) {
            $subdivisionCode = $this->subdivisionCodeFetcher->fetchSubdivisionCodeByCountryStateId(
                $addressData['countryStateId'],
                $context
            );

            if ($subdivisionCode !== null) {
                return $subdivisionCode;
            }
        }

        if ($this->countryHasStatesChecker->hasCountryStates($addressData['countryId'], $context)) {
            return '';
        }

        return null;
    }

    /**
     * Retrieves the additional address information from the provided address data array.
     *
     * @param AddressDataStructure $addressData The address data array containing address components.
     * @param Context $context The Shopware context for current execution.
     * @return string The value of the additional address information, or an empty string if not available.
     */
    private function getAdditionalInfoFromArray(array $addressData, Context $context): string
    {
        $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
        return $addressData[$fieldName] ?? '';
    }
    
    /**
     * Determines whether to use split address format based on system configuration.
     *
     * @param Context $context Shopware context
     * @return bool True if split format should be used, false for combined format
     */
    private function shouldUseSplitFormat(Context $context): bool
    {
        // Get the sales channel ID from context (if available)
        $salesChannelId = null;
        $source = $context->getSource();
        if ($source instanceof \Shopware\Core\Framework\Api\Context\SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        return $this->systemConfigService->getBool(
            AddressCheckPayloadBuilderInterface::CONFIG_SPLIT_STREET,
            $salesChannelId
        );
    }

    /**
     * Extracts split address data from CustomerAddressEntity's Endereco extension.
     *
     * @param CustomerAddressEntity $address Customer address entity
     * @param Context $context Shopware context
     * @return array{streetName: string, houseNumber: string}|null Split address data or null if not available
     */
    protected function extractSplitAddressFromCustomerAddress(CustomerAddressEntity $address, Context $context): ?array
    {
        $extension = $address->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        
        // If extension is not loaded, try to load it from database
        if (!$extension instanceof EnderecoCustomerAddressExtensionEntity) {
            $extension = $this->loadCustomerAddressExtension($address->getId(), $context);
        }
        
        if (!$extension instanceof EnderecoCustomerAddressExtensionEntity) {
            return null;
        }

        $streetName = $extension->getStreet();
        $houseNumber = $extension->getHouseNumber();

        // Only return if we have at least the street name
        if (empty($streetName)) {
            return null;
        }

        return [
            'streetName' => $streetName,
            'houseNumber' => $houseNumber
        ];
    }

    /**
     * Extracts split address data from OrderAddressEntity's Endereco extension.
     *
     * @param OrderAddressEntity $address Order address entity
     * @param Context $context Shopware context
     * @return array{streetName: string, houseNumber: string}|null Split address data or null if not available
     */
    protected function extractSplitAddressFromOrderAddress(OrderAddressEntity $address, Context $context): ?array
    {
        $extension = $address->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
        
        // If extension is not loaded, try to load it from database
        if (!$extension instanceof EnderecoOrderAddressExtensionEntity) {
            $versionId = $address->getVersionId();
            if ($versionId !== null) {
                $extension = $this->loadOrderAddressExtension($address->getId(), $versionId, $context);
            }
        }
        
        if (!$extension instanceof EnderecoOrderAddressExtensionEntity) {
            return null;
        }

        $streetName = $extension->getStreet();
        $houseNumber = $extension->getHouseNumber();

        // Only return if we have at least the street name
        if (empty($streetName)) {
            return null;
        }

        return [
            'streetName' => $streetName,
            'houseNumber' => $houseNumber
        ];
    }

    /**
     * Extracts split address data from array input (e.g., from frontend).
     * 
     * Checks for various possible field names that might contain split address data.
     *
     * @param array<string, mixed> $addressData Address data array
     * @return array{streetName: string, houseNumber: string}|null Split address data or null if not available
     */
    protected function extractSplitAddressFromArray(array $addressData): ?array
    {
        // Check for Endereco street splitting fields used in the frontend
        $streetName = $addressData['enderecoStreet'] ?? '';
        $houseNumber = $addressData['enderecoHousenumber'] ?? '';

        // Only return if we have at least the street name
        if (empty($streetName)) {
            return null;
        }

        return [
            'streetName' => (string) $streetName,
            'houseNumber' => (string) $houseNumber
        ];
    }

    /**
     * Builds a combined payload from array data.
     *
     * @param array<string, mixed> $addressData Address data array
     * @param Context $context Shopware context
     * @return AddressCheckPayloadCombined
     */
    private function buildCombinedPayload(array $addressData, Context $context): AddressCheckPayloadCombined
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressData['countryId'],
            $context
        );

        $addressDataStructure = [
            'countryId' => (string) $addressData['countryId'],
            'countryStateId' => $addressData['countryStateId'] ?? null,
            'zipcode' => (string) $addressData['zipcode'],
            'city' => (string) $addressData['city'],
            'street' => (string) $addressData['street'],
            'additionalAddressLine1' => $addressData['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $addressData['additionalAddressLine2'] ?? null,
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        return new AddressCheckPayloadCombined(
            $countryCode,
            $addressData['zipcode'],
            $addressData['city'],
            $addressData['street'],
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds a split payload from array data.
     *
     * @param array<string, mixed> $addressData Address data array
     * @param Context $context Shopware context
     * @return AddressCheckPayloadSplit
     */
    private function buildSplitPayload(array $addressData, Context $context): AddressCheckPayloadSplit
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressData['countryId'],
            $context
        );

        $addressDataStructure = [
            'countryId' => (string) $addressData['countryId'],
            'countryStateId' => $addressData['countryStateId'] ?? null,
            'zipcode' => (string) $addressData['zipcode'],
            'city' => (string) $addressData['city'],
            'street' => (string) $addressData['street'],
            'additionalAddressLine1' => $addressData['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $addressData['additionalAddressLine2'] ?? null,
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        // We might have the data already from the frontend.
        $splitData = $this->extractSplitAddressFromArray($addressData);

        if ($splitData === null) {
            // Need to split using API
            $splitResult = $this->streetSplitter->splitStreet(
                $addressData['street'],
                $additionalInfo,
                $countryCode,
                $context,
                $this->enderecoService->fetchSalesChannelId($context)
            );
            $streetName = $splitResult->getStreetName();
            $buildingNumber = $splitResult->getBuildingNumber();
            $additionalInfo = $splitResult->getAdditionalInfo();
        } else {
            $streetName = $splitData['streetName'];
            $buildingNumber = $splitData['houseNumber'];
        }

        return new AddressCheckPayloadSplit(
            $countryCode,
            $addressData['zipcode'],
            $addressData['city'],
            $streetName,
            $buildingNumber,
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds a combined payload from CustomerAddressEntity.
     *
     * @param CustomerAddressEntity $address Customer address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayloadCombined
     */
    private function buildCombinedPayloadFromCustomerAddress(CustomerAddressEntity $address, Context $context): AddressCheckPayloadCombined
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $address->getCountryId(),
            $context
        );

        $addressDataStructure = [
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'zipcode' => $address->getZipcode() ?? '',
            'city' => $address->getCity(),
            'street' => $address->getStreet(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        return new AddressCheckPayloadCombined(
            $countryCode,
            $address->getZipcode() ?? '',
            $address->getCity(),
            $address->getStreet(),
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds a split payload from CustomerAddressEntity.
     *
     * @param CustomerAddressEntity $address Customer address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayloadSplit
     */
    private function buildSplitPayloadFromCustomerAddress(CustomerAddressEntity $address, Context $context): AddressCheckPayloadSplit
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $address->getCountryId(),
            $context
        );

        $addressDataStructure = [
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'zipcode' => $address->getZipcode() ?? '',
            'city' => $address->getCity(),
            'street' => $address->getStreet(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        // We might have the data already from the extension.
        $splitData = $this->extractSplitAddressFromCustomerAddress($address, $context);

        if ($splitData === null) {
            // Need to split using API
            $splitResult = $this->streetSplitter->splitStreet(
                $address->getStreet(),
                $additionalInfo,
                $countryCode,
                $context,
                $this->enderecoService->fetchSalesChannelId($context)
            );
            $streetName = $splitResult->getStreetName();
            $buildingNumber = $splitResult->getBuildingNumber();
            $additionalInfo = $splitResult->getAdditionalInfo();
        } else {
            $streetName = $splitData['streetName'];
            $buildingNumber = $splitData['houseNumber'];
        }

        return new AddressCheckPayloadSplit(
            $countryCode,
            $address->getZipcode() ?? '',
            $address->getCity(),
            $streetName,
            $buildingNumber,
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds a combined payload from OrderAddressEntity.
     *
     * @param OrderAddressEntity $address Order address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayloadCombined
     */
    private function buildCombinedPayloadFromOrderAddress(OrderAddressEntity $address, Context $context): AddressCheckPayloadCombined
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $address->getCountryId(),
            $context
        );

        $addressDataStructure = [
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'zipcode' => $address->getZipcode() ?? '',
            'city' => $address->getCity(),
            'street' => $address->getStreet(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        return new AddressCheckPayloadCombined(
            $countryCode,
            $address->getZipcode() ?? '',
            $address->getCity(),
            $address->getStreet(),
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Builds a split payload from OrderAddressEntity.
     *
     * @param OrderAddressEntity $address Order address entity
     * @param Context $context Shopware context
     * @return AddressCheckPayloadSplit
     */
    private function buildSplitPayloadFromOrderAddress(OrderAddressEntity $address, Context $context): AddressCheckPayloadSplit
    {
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $address->getCountryId(),
            $context
        );

        $addressDataStructure = [
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'zipcode' => $address->getZipcode() ?? '',
            'city' => $address->getCity(),
            'street' => $address->getStreet(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];

        $subdivisionCode = $this->getSubdivisionCodeFromArray($addressDataStructure, $context);

        $additionalInfo = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalInfo = $this->getAdditionalInfoFromArray($addressDataStructure, $context);
        }

        // We might have the data already from the extension.
        $splitData = $this->extractSplitAddressFromOrderAddress($address, $context);

        if ($splitData === null) {
            // Need to split using API
            $splitResult = $this->streetSplitter->splitStreet(
                $address->getStreet(),
                $additionalInfo,
                $countryCode,
                $context,
                $this->enderecoService->fetchSalesChannelId($context)
            );
            $streetName = $splitResult->getStreetName();
            $buildingNumber = $splitResult->getBuildingNumber();
            $additionalInfo = $splitResult->getAdditionalInfo();
        } else {
            $streetName = $splitData['streetName'];
            $buildingNumber = $splitData['houseNumber'];
        }

        return new AddressCheckPayloadSplit(
            $countryCode,
            $address->getZipcode() ?? '',
            $address->getCity(),
            $streetName,
            $buildingNumber,
            $subdivisionCode,
            $additionalInfo
        );
    }

    /**
     * Loads customer address extension from database if not already loaded.
     *
     * @param string $customerAddressId Customer address ID
     * @param Context $context Shopware context
     * @return EnderecoCustomerAddressExtensionEntity|null Extension entity or null if not found
     */
    private function loadCustomerAddressExtension(string $customerAddressId, Context $context): ?EnderecoCustomerAddressExtensionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('addressId', $customerAddressId));
        
        $result = $this->customerAddressExtensionRepository->search($criteria, $context);
        
        $entity = $result->first();
        return $entity instanceof EnderecoCustomerAddressExtensionEntity ? $entity : null;
    }

    /**
     * Loads order address extension from database if not already loaded.
     * 
     * Order addresses are versioned entities, so we need to match both
     * the address ID and version ID for precise identification.
     *
     * @param string $orderAddressId Order address ID
     * @param string $orderAddressVersionId Order address version ID
     * @param Context $context Shopware context
     * @return EnderecoOrderAddressExtensionEntity|null Extension entity or null if not found
     */
    private function loadOrderAddressExtension(string $orderAddressId, string $orderAddressVersionId, Context $context): ?EnderecoOrderAddressExtensionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('addressId', $orderAddressId));
        $criteria->addFilter(new EqualsFilter('addressVersionId', $orderAddressVersionId));
        
        $result = $this->orderAddressExtensionRepository->search($criteria, $context);
        
        $entity = $result->first();
        return $entity instanceof EnderecoOrderAddressExtensionEntity ? $entity : null;
    }
}
