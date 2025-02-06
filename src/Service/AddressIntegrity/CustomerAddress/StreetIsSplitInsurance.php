<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
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
    private EntityRepository $customerAddressRepository;
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;

    public function __construct(
        IsStreetSplitRequiredCheckerInterface $isStreetSplitRequiredChecker,
        CountryCodeFetcherInterface $countryCodeFetcher,
        EnderecoService $enderecoService,
        EntityRepository $addressExtensionRepository,
        EntityRepository $customerAddressRepository,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker
    ) {
        $this->isStreetSplitRequiredChecker = $isStreetSplitRequiredChecker;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->enderecoService = $enderecoService;
        $this->addressExtensionRepository = $addressExtensionRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
    }

    public static function getPriority(): int
    {
        return -10;
    }

    /**
     * Ensures that the full street address of a given address entity is properly split into street name and building
     * number.
     *
     * This method accepts an AddressEntity. It retrieves the corresponding EnderecoAddressExtension for the address
     * and the full street address stored in the AddressEntity.
     * It checks whether a street splitting operation is needed by comparing the expected full street (constructed using
     * data from the EnderecoAddressExtensionEntity) with the current full street.
     *
     * If the street address is not empty and street splitting is needed, the method splits the full street address into
     * street name and building number using the 'splitStreet' method of the Endereco service. The country code for
     * splitting the street is retrieved using the 'getCountryCodeById' method (defaulting to 'DE' if unknown). The
     * split street name and building number are then saved back into the EnderecoAddressExtension for the
     * address.
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $fullStreet = $addressEntity->getStreet();
        if (empty($fullStreet)) {
            return;
        }

        $isStreetSplitRequired = $this->isStreetSplitRequiredChecker->checkIfStreetSplitIsRequired(
            $addressEntity,
            $addressExtension,
            $context
        );

        if (!$isStreetSplitRequired) {
            return;
        }

        // If country is unknown, use Germany as default
        $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
            $addressEntity->getCountryId(),
            $context,
            'DE'
        );

        $additionalInfo = null;
        $additionalFieldName = null;
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $additionalFieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
            switch ($additionalFieldName) {
                case 'additionalAddressLine1':
                    $additionalInfo = $addressEntity->getAdditionalAddressLine1();
                    break;
                case 'additionalAddressLine2':
                    $additionalInfo = $addressEntity->getAdditionalAddressLine2();
                    break;
                default:
                    $additionalInfo = '';
                    break;
            }
        }

        list($normalizedFullStreet, $streetName, $buildingNumber, $normalizedAdditionalInfo) = $this->enderecoService->splitStreet(
            $fullStreet,
            $additionalInfo,
            $countryCode,
            $context,
            $this->enderecoService->fetchSalesChannelId($context)
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


        // We update the address entity and persist the full street and additional info in the database to ensure
        // integrity between the full street and splitted parts. streetSplit sometimes normalizes the data therefore,
        // we need to overwrite the original input or the split will be triggered endlessly in some cases.
        $updateData = [
            'id'     => $addressEntity->getId(),
            'street' => $normalizedFullStreet,
        ];
        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context) && $additionalFieldName !== null) {
            $updateData[$additionalFieldName] = $normalizedAdditionalInfo;
        }

        $this->customerAddressRepository->upsert([$updateData], $context);

        $addressEntity->setStreet($normalizedFullStreet);

        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($context)) {
            $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
            switch ($fieldName) {
                case 'additionalAddressLine1':
                    $addressEntity->setAdditionalAddressLine1($normalizedAdditionalInfo ?? '');
                    break;
                case 'additionalAddressLine2':
                    $addressEntity->setAdditionalAddressLine2($normalizedAdditionalInfo ?? '');
                    break;
            }
        }
    }
}
