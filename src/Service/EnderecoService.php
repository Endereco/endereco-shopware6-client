<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Misc\EnderecoConstants;
use Endereco\Shopware6Client\Model\AddressCheckResult;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressPersistenceStrategyProvider;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterInterface;
use Endereco\Shopware6Client\Service\EnderecoService\AgentInfoGeneratorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\SessionManagementService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

class EnderecoService
{
    private Client $httpClient;

    private EntityRepository $countryStateRepository;

    private EntityRepository $customerAddressRepository;

    private EntityRepository $orderAddressRepository;

    private LoggerInterface $logger;

    private SystemConfigService $systemConfigService;

    private CountryCodeFetcherInterface $countryCodeFetcher;

    private AddressPersistenceStrategyProvider $addressPersistenceStrategyProvider;

    private AgentInfoGeneratorInterface $agentInfoGenerator;

    private PayloadPreparatorInterface $payloadPreparator;

    private StreetSplitterInterface $streetSplitter;

    private SessionManagementService $sessionManagementService;

    public bool $isImport = false;

    protected RequestStack $requestStack;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $countryStateRepository,
        EntityRepository $customerAddressRepository,
        EntityRepository $orderAddressRepository,
        CountryCodeFetcherInterface $countryCodeFetcher,
        AddressPersistenceStrategyProvider $addressPersistenceStrategyProvider,
        AgentInfoGeneratorInterface $agentInfoGenerator,
        PayloadPreparatorInterface $payloadPreparator,
        StreetSplitterInterface $streetSplitter,
        RequestStack $requestStack,
        SessionManagementService $sessionManagementService,
        LoggerInterface $logger
    ) {
        $this->httpClient = new Client(['timeout' => 3.0, 'connection_timeout' => 2.0]);
        $this->systemConfigService = $systemConfigService;
        $this->countryStateRepository = $countryStateRepository;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->countryCodeFetcher = $countryCodeFetcher;
        $this->addressPersistenceStrategyProvider = $addressPersistenceStrategyProvider;
        $this->agentInfoGenerator = $agentInfoGenerator;
        $this->payloadPreparator = $payloadPreparator;
        $this->streetSplitter = $streetSplitter;
        $this->requestStack = $requestStack;
        $this->sessionManagementService = $sessionManagementService;
        $this->logger = $logger;
    }

    /**
     * Delegates to SessionManagementService for sending doAccounting requests.
     *
     * @param array<string> $sessionIds An array of session IDs for which to process requests.
     * @param Context $context The context providing details of the event that initiated this method.
     * @param string $salesChannelId The identifier of the sales channel associated with these sessions.
     *
     * @return void
     */
    public function sendDoAccountings(array $sessionIds, Context $context, string $salesChannelId): void
    {
        $this->sessionManagementService->sendDoAccountings($sessionIds, $context, $salesChannelId);
    }

    /**
     * Delegates to SessionManagementService for closing stored sessions.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     * @param string $salesChannelId The identifier of the sales channel where the address write operation occurred.
     *
     * @return void
     */
    public function closeStoredSessions(Context $context, string $salesChannelId): void
    {
        $this->sessionManagementService->closeStoredSessions($context, $salesChannelId);
    }

    /**
     * Delegates to SessionManagementService for adding accountable session IDs to storage.
     *
     * @param array<string> $sessionIds An array of session IDs to be added to session storage.
     *
     * @return void
     */
    public function addAccountableSessionIdsToStorage(array $sessionIds): void
    {
        $this->sessionManagementService->addAccountableSessionIdsToStorage($sessionIds);
    }

    /**
     * Resets the metadata of a customer address entity.
     *
     * Updates the Endereco extension fields of the address with empty values while preserving
     * the AMS status. Specifically clears the request payload, predictions, and updates the timestamp,
     * then persists these changes to the database.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to reset
     * @param Context $context The context for database operations
     *
     * @return void
     */
    public function resetCustomerAddressMetaData(
        CustomerAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        $addressExtension = new EnderecoCustomerAddressExtensionEntity();
        $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);

        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
            = $addressExtension->getAmsRequestPayload();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
            = $addressExtension->getAmsStatus();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
            = $addressExtension->getAmsPredictions();
        $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
            = $addressExtension->getAmsTimestamp();

        // Update the customer address in the repository
        $this->customerAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Resets the metadata of an oder address entity.
     *
     * Updates the Endereco extension fields of the address with empty values while preserving
     * the AMS status. Specifically clears the request payload, predictions, and updates the timestamp,
     * then persists these changes to the database.
     *
     * @param OrderAddressEntity $addressEntity The address entity to reset
     * @param Context $context The context for database operations
     *
     * @return void
     */
    public function resetOrderAddressMetaData(
        OrderAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        $addressExtension = new EnderecoOrderAddressExtensionEntity();
        $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $addressExtension);

        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
            = $addressExtension->getAmsRequestPayload();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsStatus']
            = $addressExtension->getAmsStatus();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
            = $addressExtension->getAmsPredictions();
        $updatePayload['extensions'][OrderAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
            = $addressExtension->getAmsTimestamp();

        // Update the customer address in the repository
        $this->orderAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Updates a customer address entity with the results of an address check operation.
     *
     * This method modifies the given customer address entity based on the outcome of an address check.
     * In the event of an automatic correction from the address check, the first prediction is applied to the customer
     * address and new virtual status codes are generated. Consequently, the 'street', 'zipcode', 'city', and possibly
     * 'countryStateId' fields of the customer address are adjusted.
     * If the address check did not result in an automatic correction, only the statuses and predictions from
     * the address check result are saved.
     *
     * Regardless of the result, the 'amsStatus', 'amsPredictions', and 'amsTimestamp' fields of the Endereco
     * extension of the customer address are updated.
     *
     * @param AddressCheckResult $addressCheckResult The outcome of the address check operation.
     * @param CustomerAddressEntity $addressEntity The customer address to be updated.
     * @param Context $context The context containing details about the event triggering this method.
     *
     * @return void
     */
    public function applyAddressCheckResult(
        AddressCheckResult $addressCheckResult,
        CustomerAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressId = $addressEntity->getId();

        $updatePayload = [
            'id' => $addressId,
        ];

        if ($addressCheckResult->isAutomaticCorrection() && $this->isAutocorrectionAllowedInSettings($context)) {
            // In case of automatic correction, apply the first prediction to the customer address and generate
            // new virtual status codes
            $newStatuses = $addressCheckResult->generateStatusesForAutomaticCorrection();

            $correction = $addressCheckResult->getPredictions()[0];

            $updatePayload['zipcode'] = $correction['postalCode'];
            $updatePayload['city'] = $correction['locality'];

            $fullStreet = $this->buildFullStreet(
                $correction['streetName'],
                $correction['buildingNumber'],
                $correction['countryCode']
            );

            $updatePayload['street'] = $fullStreet;

            $addressEntity->setZipcode($correction['postalCode']);
            $addressEntity->setCity($correction['locality']);
            $addressEntity->setStreet($fullStreet);

            // If a subdivision code exists in the correction, find the corresponding country state ID and set it
            if (array_key_exists('subdivisionCode', $correction)) {
                $countryStateId = $this->countryStateRepository
                    ->searchIds(
                        (new Criteria())->addFilter(new EqualsFilter('shortCode', $correction['subdivisionCode'])),
                        $context
                    )->firstId();
                if (!is_null($countryStateId)) {
                    $updatePayload['countryStateId'] = $countryStateId;

                    $addressEntity->setCountryStateId($countryStateId);
                }
            }

            // Update the endereco extension fields
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['street']
                = $correction['streetName'];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['houseNumber']
                = $correction['buildingNumber'];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = implode(',', $newStatuses);
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = [];
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            // We update the entity here, before it is even saved, because this function was triggered by LoadEntity
            // event. Basically the updated fields would be available only after the page reload, but by setting them
            // here, we make it possible to access the data within the first request.
            // The entity is also saved in cache right after this and reused by other entities, so we need to update
            // the data in it ASAP.
            $addressExtension->setStreet($correction['streetName']);
            $addressExtension->setHouseNumber($correction['buildingNumber']);
            $addressExtension->setAmsStatus(implode(',', $newStatuses));
            $addressExtension->setAmsPredictions([]);
            $addressExtension->setAmsTimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        } elseif ($addressCheckResult->isFullyCorrect() && !empty($addressCheckResult->getPredictions())) {
            $correction = $addressCheckResult->getPredictions()[0];
            $updatePayload['zipcode'] = $correction['postalCode'];
            $updatePayload['city'] = $correction['locality'];

            $fullStreet = $this->buildFullStreet(
                $correction['streetName'],
                $correction['buildingNumber'],
                $correction['countryCode']
            );

            $updatePayload['street'] = $fullStreet;

            $addressEntity->setZipcode($correction['postalCode']);
            $addressEntity->setCity($correction['locality']);
            $addressEntity->setStreet($fullStreet);

            // If a subdivision code exists in the correction, find the corresponding country state ID and set it
            if (array_key_exists('subdivisionCode', $correction)) {
                $countryStateId = $this->countryStateRepository
                    ->searchIds(
                        (new Criteria())->addFilter(new EqualsFilter('shortCode', $correction['subdivisionCode'])),
                        $context
                    )->firstId();
                if (!is_null($countryStateId)) {
                    $updatePayload['countryStateId'] = $countryStateId;

                    $addressEntity->setCountryStateId($countryStateId);
                }
            }

            // Ultradirty fix.
            // TODO: refactor the who session management to properly finish address check with automatic or manual flags
            $existingStatuses = $addressCheckResult->getStatusesAsString();
            $automaticStatuscodes = $existingStatuses
                ? $existingStatuses . ',address_selected_automatically'
                : 'address_selected_automatically';

            // If there was no automatic correction, save the statuses and predictions from the address check result
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = $automaticStatuscodes;
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = $addressCheckResult->getPredictions();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            $addressExtension->setAmsStatus($addressCheckResult->getStatusesAsString());
            $addressExtension->setAmsPredictions($addressCheckResult->getPredictions());
            $addressExtension->setAmstimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        } else {
            // If there was no automatic correction, save the statuses and predictions from the address check result
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsStatus']
                = $addressCheckResult->getStatusesAsString();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsPredictions']
                = $addressCheckResult->getPredictions();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsTimestamp']
                = time();
            $updatePayload['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['amsRequestPayload']
                = $addressCheckResult->getAddressSignature();

            /** @var EnderecoCustomerAddressExtensionEntity|null $addressExtension */
            $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
            if (is_null($addressExtension)) {
                $addressExtension = new EnderecoCustomerAddressExtensionEntity();
                $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
            }

            $addressExtension->setAmsStatus($addressCheckResult->getStatusesAsString());
            $addressExtension->setAmsPredictions($addressCheckResult->getPredictions());
            $addressExtension->setAmstimestamp(time());
            $addressExtension->setAmsRequestPayload($addressCheckResult->getAddressSignature());
        }

        // Update the customer address in the repository
        $this->customerAddressRepository->update([$updatePayload], $context);
    }

    /**
     * Returns true if the user allowed automatic correction in the advanced settings.
     *
     * It's allowed by default, however if it causes problems with payments or other systems, this setting can be used
     * to provide a temporary fix.
     *
     * @param Context $context
     * @return bool
     */
    public function isAutocorrectionAllowedInSettings(Context $context): bool
    {
        $salesChannelId = null;
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        return $this->systemConfigService->getBool(
            'EnderecoShopware6Client.config.enderecoAllowNativeAddressFieldsOverwrite',
            $salesChannelId
        );
    }

    /**
     * Synchronizes the street data in the given address data.
     *
     * This method checks whether the street splitting feature is enabled for the specified sales channel.
     * If it is, it constructs a full street address from the street name and building number in the address data.
     * If it is not, it splits the full street address in the address data into street name and building number.
     *
     * If the country is unknown, it uses Germany ('DE') as default.
     *
     * The method modifies the provided address data array by reference.
     *
     * @param array<string, mixed> $addressData The address data to be synchronized. Modified by reference.
     * @param Context $context The context.
     * @param string $salesChannelId The sales channel ID.
     */
    public function syncStreet(array &$addressData, Context $context, string $salesChannelId): void
    {
        $extensionName = CustomerAddressExtension::ENDERECO_EXTENSION;
        $isFullStreetEmpty = empty($addressData['street']);
        $isStreetNameEmpty = empty($addressData['extensions'][$extensionName]['street']);

        // In the following we handle three expected scenatio:
        // 1. The full street is empty, but name nad housenumber not -> fill up full street
        // 2. The full street is known and the street name is not -> fill up street name and house number
        // 3. Both are filled, then we prioritize based on whether street splitter is active or not
        // 4. If both are empty not filling is required. This should not happen normally.
        if ($isFullStreetEmpty && !$isStreetNameEmpty) {
            // Fetch important parts to build a full street.
            $streetName = $addressData['extensions'][$extensionName]['street'];
            $buildingNumber = $addressData['extensions'][$extensionName]['houseNumber'];

            // If country is unknown, use Germany as default
            $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressData['countryId'],
                $context,
                'DE'
            );

            // Construct full street.
            $fullStreet = $this->buildFullStreet(
                $streetName,
                $buildingNumber,
                $countryCode
            );

            // Add the full street to the output data
            $addressData['street'] = $fullStreet;
        } elseif (!$isFullStreetEmpty && $isStreetNameEmpty) {
            // Get the full street and split it
            $fullStreet = $addressData['street'];

            // If country is unknown, use Germany as default
            $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                $addressData['countryId'],
                $context,
                'DE'
            );

            $additionalInfo = null;
            if (array_key_exists('additionalAddressLine1', $addressData)) {
                $additionalInfo = $addressData['additionalAddressLine1'];
            } elseif (array_key_exists('additionalAddressLine2', $addressData)) {
                $additionalInfo = $addressData['additionalAddressLine2'];
            }

            // Split the full street into its constituent parts
            $streetSplitResult = $this->streetSplitter->splitStreet(
                $fullStreet,
                $additionalInfo,
                $countryCode,
                $context,
                $salesChannelId
            );

            $customerAddressDTO = new CustomerAddressDTO(
                null,
                null,
                $addressData
            );

            $addressPersistenceStrategy = $this->addressPersistenceStrategyProvider->getStrategy(
                $customerAddressDTO,
                $context
            );

            $addressPersistenceStrategy->execute(
                $streetSplitResult->getFullStreet(),
                $streetSplitResult->getAdditionalInfo(),
                $streetSplitResult->getStreetName(),
                $streetSplitResult->getBuildingNumber(),
                $customerAddressDTO
            );
        } elseif (!$isFullStreetEmpty && !$isStreetNameEmpty) {
            if ($this->isStreetSplittingFeatureEnabled($salesChannelId)) {
                // Fetch important parts to build a full street.
                $streetName = $addressData['extensions'][$extensionName]['street'];
                $buildingNumber = $addressData['extensions'][$extensionName]['houseNumber'];

                // If country is unknown, use Germany as default
                $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                    $addressData['countryId'],
                    $context,
                    'DE'
                );

                // Construct full street.
                $fullStreet = $this->buildFullStreet(
                    $streetName,
                    $buildingNumber,
                    $countryCode
                );

                // Add the full street to the output data
                $addressData['street'] = $fullStreet;
            } else {
                // Get the full street and split it
                $fullStreet = $addressData['street'];

                // If country is unknown, use Germany as default
                $countryCode = $this->countryCodeFetcher->fetchCountryCodeByCountryIdAndContext(
                    $addressData['countryId'],
                    $context,
                    'DE'
                );

                $additionalInfo = null;
                if (array_key_exists('additionalAddressLine1', $addressData)) {
                    $additionalInfo = $addressData['additionalAddressLine1'];
                } elseif (array_key_exists('additionalAddressLine2', $addressData)) {
                    $additionalInfo = $addressData['additionalAddressLine2'];
                }

                // Split the full street into its constituent parts
                $splitStreetResult = $this->streetSplitter->splitStreet(
                    $fullStreet,
                    $additionalInfo,
                    $countryCode,
                    $context,
                    $salesChannelId
                );

                $customerAddressDTO = new CustomerAddressDTO(
                    null,
                    null,
                    $addressData
                );

                $addressPersistenceStrategy = $this->addressPersistenceStrategyProvider->getStrategy(
                    $customerAddressDTO,
                    $context
                );

                $addressPersistenceStrategy->execute(
                    $splitStreetResult->getFullStreet(),
                    $splitStreetResult->getAdditionalInfo(),
                    $splitStreetResult->getStreetName(),
                    $splitStreetResult->getBuildingNumber(),
                    $customerAddressDTO
                );
            }
        }
    }

    /**
     * Determines if the Endereco plugin is active for a given sales channel.
     *
     * This method checks if the Endereco plugin is active for a specified sales channel and if the API key is set.
     * Both of these conditions must be met for the plugin to be considered active.
     *
     * @param string $salesChannelId The ID of the sales channel to check. If null, the default channel is used.
     * @return bool Returns true if the plugin is active for the given sales channel and the API key is set,
     *              false otherwise.
     */
    public function isEnderecoPluginActive(string $salesChannelId): bool
    {
        // Check if the plugin is active for this channel
        $isActiveForThisChannel = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId);

        // Check if the API key is set
        $isApiKeySet = !empty(
            $this->systemConfigService->get(
                'EnderecoShopware6Client.config.enderecoApiKey',
                $salesChannelId
            )
        );

        // Return true only if both conditions are met
        return $isActiveForThisChannel && $isApiKeySet;
    }

    /**
     * Determines if address validation is active for a given sales channel.
     *
     * @param string $salesChannelId The ID of the sales channel to check
     * @return bool Returns true if address validation is active for the given sales channel
     */
    public function isAddressCheckActive(string $salesChannelId): bool
    {
        // Check if the plugin is active for this channel
        $isAddressCheckActiveForThisChannel = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoAMSActive', $salesChannelId);

        return $isAddressCheckActiveForThisChannel;
    }

    /**
     * Determines if street splitting feature is enabled.
     *
     * This method checks whether the Endereco plugin is active and
     * the street splitting feature is enabled in the settings.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the setting.
     *
     * @return bool Returns true if street splitting is enabled, false otherwise.
     */
    public function isStreetSplittingFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks if the Import/Export Check feature is enabled for a given sales channel.
     *
     * This method determines whether the Import/Export Check feature of the Endereco plugin
     * is active and ready to use for the specified sales channel. It checks two conditions:
     * 1. If the Endereco plugin is active for the given sales channel.
     * 2. If the Import/Export Check feature is enabled in the plugin settings for the given sales channel.
     *
     * Essentially its used to block validation (e.g. AddressCheck) for when user is importing a bunch of data.
     *
     * @param string $salesChannelId The ID of the sales channel to check.
     *
     * @return bool Returns true if the Import/Export Check feature is enabled and ready to use,
     *              false otherwise.
     */
    public function isImportExportCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the addresses of imported customers should be validated.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoImportExportCheck', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the 'existing customer address check' feature is enabled.
     *
     * This method accepts a sales channel ID and checks two conditions:
     * 1. Whether the Endereco plugin is active and ready to use for the given sales channel.
     * 2. Whether the 'existing customer address check' feature is enabled in the settings for the given sales channel.
     *
     * The feature is considered active if both conditions are true. The method then returns this status.
     *
     * This feature is used to decide whether or not existing customer or order addresses should be checked for updates.
     * It can be controlled via the EnderecoShopware6Client configuration, providing flexibility to meet different
     * shop requirements.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the status of the feature.
     *
     * @return bool Returns true if the feature is enabled, false otherwise.
     */
    public function isExistingAddressCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoCheckExistingAddress', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the 'PayPal checkout address check' feature is enabled for a given sales channel.
     *
     * This method accepts a sales channel ID and checks two conditions:
     * 1. Whether the Endereco plugin is active and ready to use for the given sales channel.
     * 2. Whether the 'PayPal checkout address check' feature is enabled in the settings for the given sales channel.
     *
     * The feature is considered active if both conditions are true. The method then returns this status.
     *
     * This feature is used to decide whether or not addresses provided during a PayPal Express checkout process
     * should be checked for updates.
     * It can be controlled via the EnderecoShopware6Client configuration, providing flexibility to meet different
     * shop requirements.
     *
     * @param string $salesChannelId The ID of the sales channel for which to check the status of the feature.
     *
     * @return bool Returns true if the feature is enabled, false otherwise.
     */
    public function isPayPalCheckoutAddressCheckFeatureEnabled(string $salesChannelId): bool
    {
        // Check if the Endereco plugin is active and ready to use for the given sales channel.
        $pluginIsReadyToUse = $this->isEnderecoPluginActive($salesChannelId);

        // Check if the street splitting feature is active in the settings for the given sales channel.
        $featureIsActiveInSettings = $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoCheckPayPalExpressAddress', $salesChannelId);

        // The feature is active if both the plugin is ready to use and the feature is active in settings.
        $featureIsActive = $pluginIsReadyToUse && $featureIsActiveInSettings;

        return $featureIsActive;
    }

    /**
     * Checks whether the given address is from a remote source.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from a remote source such as PayPal, Amazon Pay (or potentially other sources
     * like Facebook etc.).
     *
     * The method returns true if the address originated from a remote source,
     * and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from a remote source, false otherwise.
     */
    public function isAddressFromRemote(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromPayPal = $addressExtension->isPayPalAddress();
        $isFromAmazonPay = $addressExtension->isAmazonPayAddress();

        // The address is considered from remote if it originated from PayPal, Amazon Pay or other remote sources.
        // Current implementation considers PayPal and Amazon Pay, but this can be extended to other sources in future.
        $isFromRemote = $isFromPayPal || $isFromAmazonPay; // || $isFromAmazon || $isFromFaceBook etc.

        return $isFromRemote;
    }

    /**
     * Checks if a given address was created within the last 30 minutes.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to check.
     *
     * @return bool Returns true if the address was created within the last 30 minutes, false otherwise.
     */
    public function isAddressRecent(CustomerAddressEntity $addressEntity): bool
    {
        // Get the creation time of the address
        $creationTime = $addressEntity->getCreatedAt();

        // Get the current time minus 30 minutes
        $fiveMinutesAgo = (new \DateTime())->modify('-30 minutes');

        // If the creation time of the address is greater than (or equal to) the time 30 minutes ago, return true
        // Otherwise, return false
        return $creationTime >= $fiveMinutesAgo;
    }

    /**
     * Checks whether the given address is originated from PayPal.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from PayPal, indicated by the 'isPayPalAddress' property of the
     * EnderecoAddressExtensionEntity.
     *
     * The method returns true if the address originated from PayPal, and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from PayPal, false otherwise.
     */
    public function isAddressFromPayPal(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromPayPal = $addressExtension->isPayPalAddress();

        return $isFromPayPal;
    }

    /**
     * Checks whether the given address is originated from Amazon Pay.
     *
     * This method accepts a CustomerAddressEntity and retrieves the corresponding
     * EnderecoAddressExtensionEntity for the address. It then checks if the address
     * originated from Amazon Pay, indicated by the 'isAmazonPayAddress' property of the
     * EnderecoAddressExtensionEntity.
     *
     * The method returns true if the address originated from Amazon Pay, and false otherwise.
     *
     * @param CustomerAddressEntity $addressEntity The address entity for which to check the source.
     *
     * @return bool Returns true if the address is from Amazon Pay, false otherwise.
     */
    public function isAddressFromAmazonPay(CustomerAddressEntity $addressEntity): bool
    {
        /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        $isFromAmazonPay = $addressExtension->isAmazonPayAddress();

        return $isFromAmazonPay;
    }

    /**
     * Delegates to SessionManagementService for finding accountable session IDs.
     *
     * @param array<string, string> $array The array from which to find accountable session IDs.
     *
     * @return array<string> Returns an array of the accountable session IDs found.
     */
    public function findAccountableSessionIds($array): array
    {
        return $this->sessionManagementService->findAccountableSessionIds($array);
    }

    /**
     * Checks the API credentials by sending a 'readinessCheck' request to the Endereco API.
     *
     * This method sends a 'readinessCheck' request to the API using the provided API key and endpoint URL.
     * The response is then checked for a 'ready' status, and the method returns true if the status is 'ready'.
     * If the status is not 'ready' or any exceptions occur during the request, the method logs a warning message
     * and returns false.
     *
     * @param string $endpointUrl The URL to send the API request to.
     * @param string $apiKey The API key to use for the request.
     * @param Context $context The context offering details of the event triggering this method.
     *
     * @return bool True if the API readiness check is successful, false otherwise.
     */
    public function checkApiCredentials(string $endpointUrl, string $apiKey, Context $context): bool
    {
        try {
            // Get the name of the plugin and its version.
            $appName = $this->agentInfoGenerator->getAgentInfo($context);

            // Generate request headers from context and sales channel settings.
            $headers = [
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $apiKey,
                'X-Transaction-Id' => 'not_required',
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? __FILE__,
                'X-Agent' => $appName,
            ];

            // Prepare the payload for the 'readinessCheck' request.
            $payload = json_encode(
                $this->payloadPreparator->preparePayload(
                    'readinessCheck'
                )
            );

            // Send the 'readinessCheck' request to the Endereco API.
            $response = $this->httpClient->post(
                $endpointUrl,
                [
                    'headers' => $headers,
                    'body' => $payload
                ]
            );

            // Decode the response from the API.
            $status = json_decode($response->getBody()->getContents(), true);

            // Check if the status from the response is 'ready'.
            if ('ready' === $status['result']['status']) {
                return true;
            } else {
                // Log a warning if the status is not 'ready'.
                $this->logger->warning("Credentials test failed", ['responseFromEndereco' => json_encode($status)]);
            }
        } catch (GuzzleException $e) {
            // Log a warning if an exception occurs during the request.
            $this->logger->warning("Credentials test failed", ['error' => $e->getMessage()]);
        }

        // Return false if the status is not 'ready' or an exception occurred.
        return false;
    }


    /**
     * Constructs a full street address string from the street name and building number.
     *
     * The order in which the street name and building number are combined is determined by the provided country code.
     * The method checks for a mapping of the country code to an order in the STREET_ORDER_MAP constant.
     * If no mapping is found, it defaults to placing the house number second.
     *
     * @param string $streetName The name of the street.
     * @param string $buildingNumber The building number.
     * @param string $countryCode The ISO code of the country the address is in.
     * @return string The full street address.
     */
    public function buildFullStreet(string $streetName, string $buildingNumber, string $countryCode): string
    {
        // Determine the order for combining the street name and building number
        $order =
            EnderecoConstants::STREET_ORDER_MAP[strtolower($countryCode)] ??
            EnderecoConstants::STREET_ORDER_HOUSE_SECOND;

        // Return the full street address in the determined order
        return $order === EnderecoConstants::STREET_ORDER_HOUSE_FIRST ?
            sprintf('%s %s', $buildingNumber, $streetName) :
            sprintf('%s %s', $streetName, $buildingNumber);
    }

    /**
     * Fetches the sales channel ID from the context source.
     *
     * This method tries to fetch the sales channel ID from the context source.
     * If the source is an instance of SalesChannelApiSource, it returns the sales channel ID.
     * If the source is not an instance of SalesChannelApiSource, it returns null.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string|null The ID of the sales channel or null if the context source is not an instance
     *                     of SalesChannelApiSource.
     */
    public function fetchSalesChannelId(Context $context): ?string
    {
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            return $source->getSalesChannelId();
        }
        return null;
    }
}
