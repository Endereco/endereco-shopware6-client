<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Storefront\Page\GenericPageLoadedEvent;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\Framework\Context;

class AddDataToPage implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $countryRepository;
    protected EntityRepository $stateRepository;
    protected EntityRepository $salutationRepository;
    protected EntityRepository $pluginRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService,
        EntityRepository $countryRepository,
        EntityRepository $stateRepository,
        EntityRepository $salutationRepository,
        EntityRepository $pluginRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->countryRepository = $countryRepository;
        $this->stateRepository = $stateRepository;
        $this->salutationRepository = $salutationRepository;
        $this->pluginRepository = $pluginRepository;
        $this->enderecoService = $enderecoService;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'addEnderecoConfigToPage'
        ];
    }

    public function addEnderecoConfigToPage(GenericPageLoadedEvent $event): void
    {
        // Get context and sales channel id, that are needed to access the right data or settings.
        $context = $event->getContext();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        // Sanity check. If there is no sales channel id, we don't need to add data to frontend.
        if (is_null($salesChannelId)) {
            return;
        }

        // This struct will be enriched with diverse data that are needed in the frontend.
        $configContainer = new stdClass();

        // Get agent name
        $configContainer->enderecoAgentInfo = $this->enderecoService->getAgentInfo($context);
        $configContainer->enderecoVersion = $this->enderecoService->getPluginVersion($context);

        // Enrich with api settings.
        $this->addApiData($configContainer, $context, $salesChannelId);

        // Set whether it should be active in this channel.
        $configContainer->pluginActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId)
            && !empty($configContainer->enderecoApiKey);

        // Enrich with advanced settings.
        $this->addAdvancedSettingsData($configContainer, $context, $salesChannelId);

        $currentController = (string) $event->getRequest()->attributes->get('_controller');

        if ($currentController !== '' && $configContainer->controllerOnlyWhitelist) {
            $found = false;

            foreach ($configContainer->controllerWhitelist as $whitelist) {
                if (\str_contains($currentController, "Controller\\${whitelist}Controller::")) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return;
            }
        }

        // Enrich with email check settings.
        $this->addEmailCheckData($configContainer, $context, $salesChannelId);

        // Enrich with name check setting.
        $this->addNameCheckData($configContainer, $context, $salesChannelId);

        // Add address check settings.
        $this->addAddressCheckData($configContainer, $context, $salesChannelId);

        // Enrich phone check settings.
        $this->addPhoneCheckData($configContainer, $context, $salesChannelId);

        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }

    /**
     * Enriches the given configContainer object with API settings fetched from the system config.
     * These settings are used to dictate frontend behaviour.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param Context $context The context of the sales channel in the current lifecycle.
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
    private function addApiData(stdClass &$configContainer, Context $context, string $salesChannelId): void
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
    }

    /**
     * Enriches the given configContainer object with email check settings fetched from the system config.
     * These settings are used for email validation and related frontend behaviour.
     *
     * @param stdClass $configContainer The object that holds the configuration settings. Passed by reference.
     * @param Context $context The context of the sales channel in the current lifecycle.
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
    private function addEmailCheckData(stdClass &$configContainer, Context $context, string $salesChannelId): void
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

            $salutationKey = $salutation->getSalutationKey() ?? '';

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
     * @param Context $context The context of the sales channel in the current lifecycle.
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
    public function addPhoneCheckData(stdClass &$configContainer, Context $context, string $salesChannelId): void
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
     * @param Context $context The context of the sales channel in the current lifecycle.
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
    public function addAdvancedSettingsData(stdClass &$configContainer, Context $context, string $salesChannelId): void
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

        /** @var EntitySearchResult $countries */
        $countries = $this->countryRepository->search(new Criteria(), $context);
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

        /** @var EntitySearchResult $states */
        $states = $this->stateRepository->search(new Criteria(), $context);
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
    }
}
