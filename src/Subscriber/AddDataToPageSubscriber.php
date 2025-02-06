<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddDataToPageSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $countryRepository;
    protected EntityRepository $stateRepository;
    protected EntityRepository $salutationRepository;
    protected EntityRepository $pluginRepository;
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService,
        EntityRepository $countryRepository,
        EntityRepository $stateRepository,
        EntityRepository $salutationRepository,
        EntityRepository $pluginRepository,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->countryRepository = $countryRepository;
        $this->stateRepository = $stateRepository;
        $this->salutationRepository = $salutationRepository;
        $this->pluginRepository = $pluginRepository;
        $this->enderecoService = $enderecoService;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'addEnderecoConfigToPage'
        ];
    }

    /**
     * Adds Endereco configuration to the page.
     *
     * This method retrieves various settings and data required for the Endereco service
     * and assigns them to the page. It ensures that only the necessary data is loaded
     * based on the active settings and the current controller.
     *
     * @param GenericPageLoadedEvent $event The event instance containing page and context information.
     * @return void
     */
    public function addEnderecoConfigToPage(GenericPageLoadedEvent $event): void
    {
        // Retrieve the context and sales channel ID, which are required to access the correct data or settings.
        $context = $event->getContext();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        // Perform a sanity check. If there is no sales channel ID, there is no need to add data to the frontend.
        if (is_null($salesChannelId)) {
            return;
        }

        // Initialize a structure that will be enriched with various data required in the frontend.
        $configContainer = new stdClass();

        // Enrich the structure with advanced settings.
        $this->addAdvancedSettingsData($configContainer, $salesChannelId);

        // Enrich the structure with API settings.
        $this->addApiData($configContainer, $salesChannelId);

        // Determine whether the rest of the settings should be loaded. If the plugin is inactive or inactive for the
        // current controller, there is no need to load all the settings from the database.
        $currentController = (string) $event->getRequest()->attributes->get('_controller');

        $loadEnderecoSettings = true;
        $isCurrentControllerKnown = !empty($currentController);
        $isControllerWhitelistActive = $configContainer->controllerOnlyWhitelist;
        $isPluginActive = $configContainer->pluginActive;

        if ($isCurrentControllerKnown && $isControllerWhitelistActive) {
            $loadEnderecoSettings = false;
            foreach ($configContainer->controllerWhitelist as $whitelist) {
                if (\str_contains($currentController, "Controller\\{$whitelist}Controller::")) {
                    $loadEnderecoSettings = true;
                    break;
                }
            }
        }

        $loadEnderecoSettings = $loadEnderecoSettings && $isPluginActive;

        if ($loadEnderecoSettings) {
            // Retrieve and assign agent information and plugin version.
            $configContainer->enderecoAgentInfo = $this->enderecoService->getAgentInfo($context);
            $configContainer->enderecoVersion = $this->enderecoService->getPluginVersion($context);

            // Enrich the structure with email check settings.
            $this->addEmailCheckData($configContainer, $salesChannelId);

            // Enrich the structure with name check settings.
            $this->addNameCheckData($configContainer, $context, $salesChannelId);

            // Add address check settings to the structure.
            $this->addAddressCheckData($configContainer, $context, $salesChannelId);

            // Enrich the structure with phone check settings.
            $this->addPhoneCheckData($configContainer, $salesChannelId);
        }

        // Assign the configuration to the page.
        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }


    /**
     * Enriches the given configContainer object with API settings fetched from the system config.
     * These settings are used to dictate frontend behaviour.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoPreselectDefaultCountryCode': Sets the default country preselection.
     * - 'EnderecoShopware6Client.config.enderecoApiKey': Sets the API key for the Endereco service.
     * - 'EnderecoShopware6Client.config.enderecoRemoteUrl': Sets the remote URL for the Endereco service.
     *
     * These settings are stored in the passed configContainer object.
     *
     * @return void
     */
    private function addApiData(stdClass &$configContainer, string $salesChannelId): void
    {
        $configContainer->defaultCountry =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountryCode', $salesChannelId);

        $configContainer->enderecoApiKey =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoApiKey', $salesChannelId);

        $configContainer->enderecoRemoteUrl =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoRemoteUrl', $salesChannelId);

        $configContainer->pluginActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId)
            && !empty($configContainer->enderecoApiKey);
    }

    /**
     * Enriches the given configContainer object with email check settings fetched from the system config.
     * These settings are used for email validation and related frontend behaviour.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoEmailCheckActive': Determines if email checking is active.
     * - 'EnderecoShopware6Client.config.enderecoShowEmailStatus': Determines if the email validation
     *                                                             status should be displayed.
     *
     * These settings are stored in the passed configContainer object.
     *
     * @return void
     */
    private function addEmailCheckData(stdClass &$configContainer, string $salesChannelId): void
    {
        $configContainer->enderecoEmailCheckActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoEmailCheckActive', $salesChannelId);
        $configContainer->enderecoShowEmailStatus =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoShowEmailStatus', $salesChannelId);
    }

    /**
     * Enriches the given configContainer object with name check settings fetched from the system config.
     * These settings are used for name validation and related frontend behaviour.
     *
     * Additionally, this method creates a mapping for salutations based on the Endereco API requirements.
     * The mapping is added to the configContainer object.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param Context $context The context of the sales channel in the current lifecycle.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoNameCheckActive': Determines if name checking is active.
     * - 'EnderecoShopware6Client.config.enderecoExchangeNamesAutomatically': Determines if mispositioned name parts
     *                                                                        should be exchanged automatically.
     *
     * The method creates a mapping for salutations:
     * - Maps Shopware 6 salutation keys ('mr', 'mrs', 'not_specified', 'diverse') to the codes
     *   expected by Endereco API ('m', 'f', 'x', 'd').
     * - The mapping and its reverse are added to the configContainer object.
     *
     * @return void
     */
    private function addNameCheckData(stdClass &$configContainer, Context $context, string $salesChannelId): void
    {
        $configContainer->enderecoNameCheckActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoNameCheckActive', $salesChannelId);
        $configContainer->enderecoExchangeNamesAutomatically =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoExchangeNamesAutomatically', $salesChannelId);

        /**
         * Create a salutation mapping. Endereco API expects these codes:
         * @see https://github.com/Endereco/enderecoservice_api/blob/master/fields.md#tabelle-der-anrede-codes
         * 'm' - male, in Shopware 6 it would be 'mr'
         * 'f' - female, in Shopware 6 it would be 'mrs'
         * 'd' - diverse, in Shopware 6 it would be 'diverse', that needs to be created separately
         * 'x' - unknown, in Shopware 6 it would be 'not_specified'. It's also set in endereco.js
         * @var EntitySearchResult $salutations
         */
        $salutations = $this->salutationRepository->search(new Criteria(), $context);
        $relevanceMapping = [
            'mr' => 'm',
            'mrs' => 'f',
            'not_specified' => 'x',
            'diverse' => 'd'
        ];
        $salutationMapping = [];
        foreach ($salutations as $salutation) {

            /** @var SalutationEntity $salutation */
            $salutationKey = (string) $salutation->getSalutationKey();

            if (array_key_exists($salutationKey, $relevanceMapping)) {
                $salutationMapping[$salutation->getId()] = $relevanceMapping[$salutationKey];
            }
        }

        $configContainer->salutationMapping = $this->createSafeJsonString($salutationMapping);
        $configContainer->salutationMappingReverse = $this->createSafeJsonString(array_flip($salutationMapping));
    }

    /**
     * Transform the array to JSON for safe display in the frontend.
     *
     * @param array<string, string|null> $array Array to encode.
     *
     * @return string Encoded array
     */
    private function createSafeJsonString(array $array): string
    {
        /** @var string|false $encodedString */
        $encodedString = json_encode($array);

        if (!$encodedString) {
            $encodedString = '';
        }

        $escapedString = str_replace(
            "'",
            "\'",
            $encodedString
        );

        return $escapedString;
    }

    /**
     * Enriches the given configContainer object with phone check settings fetched from the system config.
     * These settings are used for phone validation and related frontend behaviour.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoPhsActive': Determines if phone checking is active.
     * - 'EnderecoShopware6Client.config.enderecoPhsUseFormat': Determines the format used for phone checking.
     * - 'EnderecoShopware6Client.config.enderecoPhsDefaultFieldType': Determines the default phone type used
     *                                                                 for phone checking.
     * - 'EnderecoShopware6Client.config.enderecoShowPhoneErrors': Determines if phone validation errors
     *                                                             should be displayed.
     *
     * These settings are stored in the passed configContainer object.
     *
     * @return void
     */
    public function addPhoneCheckData(stdClass &$configContainer, string $salesChannelId): void
    {
        $configContainer->enderecoPhsActive =
            $this->systemConfigService
                ->getBool('EnderecoShopware6Client.config.enderecoPhsActive', $salesChannelId);
        $configContainer->enderecoPhsUseFormat =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoPhsUseFormat', $salesChannelId);
        $configContainer->enderecoPhsDefaultFieldType =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoPhsDefaultFieldType', $salesChannelId);
        $configContainer->enderecoShowPhoneErrors =
            $this->systemConfigService
                ->getBool('EnderecoShopware6Client.config.enderecoShowPhoneErrors', $salesChannelId);
    }

    /**
     * Enriches the given configContainer object with advanced settings fetched from the system config.
     * These settings include loading CSS, defining controller whitelists, and the path to an IO PHP file.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoLoadCss': Determines whether and how to load CSS.
     * - 'EnderecoShopware6Client.config.enderecoWhitelistControllerList': Additional controllers to be whitelisted.
     * - 'EnderecoShopware6Client.config.enderecoWhitelistController': Determines whether to whitelist controllers.
     * - 'EnderecoShopware6Client.config.enderecoPathToIOPhp': Path to IO PHP file.
     *
     * Additionally, a default list of controllers to be whitelisted is defined and may be extended by the
     * fetched whitelist.
     *
     * These settings are stored in the passed configContainer object.
     *
     * @return void
     */
    public function addAdvancedSettingsData(stdClass &$configContainer, string $salesChannelId): void
    {
        $configContainer->enderecoLoadCss =
            $this->systemConfigService
                ->getString('EnderecoShopware6Client.config.enderecoLoadCss', $salesChannelId);

        // Make controllerwhitelist
        $controllerWhitelist = ['Auth', 'AccountProfile', 'Address', 'Checkout', 'Register'];

        $enderecoWhitelistControllerList = $this->systemConfigService
            ->getString('EnderecoShopware6Client.config.enderecoWhitelistControllerList', $salesChannelId);

        /** @var string[] $controllerWhitelistAddition */
        $controllerWhitelistAddition = explode(
            ',',
            str_replace(
                ' ',
                '',
                $enderecoWhitelistControllerList
            )
        );
        if ($controllerWhitelistAddition) {
            $controllerWhitelist = array_merge($controllerWhitelist, $controllerWhitelistAddition);
        }
        $controllerWhitelist = array_filter($controllerWhitelist); // Filter out empty elements.
        $configContainer->controllerWhitelist = $controllerWhitelist;
        $configContainer->controllerOnlyWhitelist =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoWhitelistController', $salesChannelId);

        $ioPathFile = $this->systemConfigService->get(
            'EnderecoShopware6Client.config.enderecoPathToIOPhp',
            $salesChannelId
        );

        // Calculate path to file.
        $configContainer->pathToIoPhp = !empty($ioPathFile) ? $ioPathFile : '';
    }

    /**
     * Enriches the given configContainer object with address check settings fetched from the system config.
     * These settings are used for address validation and related frontend behaviour.
     *
     * Additionally, this method creates mappings for countries and states/subdivisions based on Shopware 6 data and
     * adds them to the configContainer.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param Context $context The context of the sales channel in the current lifecycle.
     * @param string $salesChannelId The ID of the sales channel from which the settings are fetched.
     *
     * The method fetches the following settings:
     * - 'EnderecoShopware6Client.config.enderecoTriggerOnBlur',
     *   'EnderecoShopware6Client.config.enderecoTriggerOnSubmit', etc.
     *
     * The method creates mappings for:
     * - Country codes to country IDs, and vice versa, using Shopware 6 country data.
     * - State short codes to state IDs, and vice versa, using Shopware 6 state data.
     *
     * These settings and mappings are stored in the passed configContainer object.
     *
     * @return void
     */
    private function addAddressCheckData(stdClass &$configContainer, Context $context, string $salesChannelId): void
    {
        $configContainer->enderecoAMSActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoAMSActive', $salesChannelId);
        $configContainer->enderecoTriggerOnBlur =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoTriggerOnBlur', $salesChannelId);
        $configContainer->enderecoTriggerOnSubmit =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoTriggerOnSubmit', $salesChannelId);
        $configContainer->enderecoSmartAutocomplete =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoSmartAutocomplete', $salesChannelId);
        $configContainer->enderecoContinueSubmit =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoContinueSubmit', $salesChannelId);
        $configContainer->enderecoAllowCloseIcon =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoAllowCloseIcon', $salesChannelId);
        $configContainer->enderecoConfirmWithCheckbox =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoConfirmWithCheckbox', $salesChannelId);
        $configContainer->enderecoSplitStreet =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);
        $configContainer->enderecoCheckAddressEnabled =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoCheckExistingAddress', $salesChannelId);
        $configContainer->enderecoCheckPayPalExpressAddress =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoCheckPayPalExpressAddress', $salesChannelId);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', 1));

        /** @var EntitySearchResult $countries */
        $countries = $this->countryRepository->search($criteria, $context);
        $mapping = [];
        $mappingReverse = [];
        $codeToNameMapping = [];
        foreach ($countries as $country) {
            /** @var CountryEntity $country */

            $countryCode = $country->getIso() ?? '';

            if (!empty($countryCode)) {
                $countryCode = strtoupper($countryCode);
                $mapping[$countryCode] = $country->getId();
                $mappingReverse[$country->getId()] = $countryCode;
                $codeToNameMapping[$countryCode] = $country->getName();
            }
        }

        $configContainer->countryCodeToNameMapping = $this->createSafeJsonString($codeToNameMapping);
        $configContainer->countryMapping = $this->createSafeJsonString($mapping);
        $configContainer->countryMappingReverse = $this->createSafeJsonString($mappingReverse);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', 1));

        /** @var EntitySearchResult $states */
        $states = $this->stateRepository->search($criteria, $context);
        $statesMapping = [];
        $statesMappingReverse = [];
        $statesCodeToNameMapping = [];
        foreach ($states as $state) {
            /** @var CountryStateEntity $state */

            $shortCode = $state->getShortCode();

            if (!empty($shortCode)) {
                $shortCode = strtoupper($shortCode);
                $statesMapping[$shortCode] = $state->getId();
                $statesMappingReverse[$state->getId()] = $shortCode;
                $statesCodeToNameMapping[$shortCode] = $state->getName();
            }
        }

        $configContainer->subdivisionCodeToNameMapping = $this->createSafeJsonString($statesCodeToNameMapping);
        $configContainer->subdivisionMapping = $this->createSafeJsonString($statesMapping);
        $configContainer->subdivisionMappingReverse = $this->createSafeJsonString($statesMappingReverse);

        // Additional info data.
        $configContainer->hasAnyAdditionalFields =
            $this->additionalAddressFieldChecker->hasAdditionalAddressField($context);
        $configContainer->additionalInfoFieldName =
            $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($context);
    }
}
