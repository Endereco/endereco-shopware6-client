<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class AddDataToPage implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    private EntityRepository $countryRepository;

    private EntityRepository $stateRepository;

    private EntityRepository $pluginRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository    $countryRepository,
        EntityRepository    $stateRepository,
        EntityRepository    $pluginRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->countryRepository = $countryRepository;
        $this->stateRepository = $stateRepository;
        $this->pluginRepository = $pluginRepository;
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
        $context = $event->getContext();
        $configContainer = new stdClass();
        $salesChannelId = null;
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));
        $version = $this->pluginRepository->search($criteria, $context)->first()->getVersion();
        $configContainer->enderecoAgentInfo = 'Endereco Shopware6 Client (Download) v' . $version;
        $configContainer->enderecoVersion = $version;
        $configContainer->enderecoApiKey =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoApiKey', $salesChannelId);
        $configContainer->enderecoRemoteUrl =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoRemoteUrl', $salesChannelId);
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
        $configContainer->enderecoNameCheckActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoNameCheckActive', $salesChannelId);
        $configContainer->enderecoExchangeNamesAutomatically =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoExchangeNamesAutomatically', $salesChannelId);
        $configContainer->enderecoEmailCheckActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoEmailCheckActive', $salesChannelId);
        $configContainer->enderecoShowEmailStatus =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoShowEmailStatus', $salesChannelId);

        $configContainer->pluginActive =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId)
            && !empty($configContainer->enderecoApiKey);
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
        // Make controllerwhitelist
        $controllerWhitelist = ['Auth', 'AccountProfile', 'Address', 'Checkout', 'Register'];
        $controllerWhitelistAddition = explode(
            ',',
            str_replace(
                ' ',
                '',
                $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoWhitelistControllerList', $salesChannelId)
            )
        );
        if (count($controllerWhitelistAddition) > 0) {
            $controllerWhitelist = array_merge($controllerWhitelist, $controllerWhitelistAddition);
        }
        $configContainer->controllerWhitelist = $controllerWhitelist;
        $configContainer->controllerOnlyWhitelist =
            $this->systemConfigService
                ->get('EnderecoShopware6Client.config.enderecoWhitelistController', $salesChannelId);

        $countries = $this->countryRepository->search(new Criteria(), $context);

        $mapping = [];
        $mappingReverse = [];
        $codeToNameMapping = [];
        foreach ($countries as $country) {
            $mapping[strtoupper($country->getIso())] = $country->getId();
            $mappingReverse[$country->getId()] = strtoupper($country->getIso());
            $codeToNameMapping[strtoupper($country->getIso())] = $country->getName();
        }

        $configContainer->countryCodeToNameMapping = str_replace("'", "\'", json_encode($codeToNameMapping));
        $configContainer->countryMapping = str_replace("'", "\'", json_encode($mapping));
        $configContainer->countryMappingReverse = str_replace("'", "\'", json_encode($mappingReverse));

        $states = $this->stateRepository->search(new Criteria(), $context);
        $statesMapping = [];
        $statesMappingReverse = [];
        $statesCodeToNameMapping = [];
        foreach ($states as $state) {
            $statesMapping[strtoupper($state->getShortCode())] = $state->getId();
            $statesMappingReverse[$state->getId()] = strtoupper($state->getShortCode());
            $statesCodeToNameMapping[strtoupper($state->getShortCode())] = $state->getName();
        }

        $configContainer->subdivisionCodeToNameMapping = str_replace("'", "\'", json_encode($statesCodeToNameMapping));
        $configContainer->subdivisionMapping = str_replace("'", "\'", json_encode($statesMapping));
        $configContainer->subdivisionMappingReverse = str_replace("'", "\'", json_encode($statesMappingReverse));

        // Calculate path to file.
        if (!empty(
            $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPathToIOPhp', $salesChannelId)
        )
        ) {
            $configContainer->pathToIoPhp =
                $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPathToIOPhp', $salesChannelId);
        } else {
            $configContainer->pathToIoPhp = '';
        }

        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }
}
