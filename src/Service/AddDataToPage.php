<?php
namespace Endereco\Shopware6Client\Service;

use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AddDataToPage implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    private $countryRepository;
    private $pluginRepository;

    public function __construct(SystemConfigService $systemConfigService, $countryRepository, $pluginRepository)
    {
        $this->systemConfigService = $systemConfigService;
        $this->countryRepository = $countryRepository;
        $this->pluginRepository = $pluginRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'addEnderecoConfigToPage'
        ];
    }

    public function addEnderecoConfigToPage(GenericPageLoadedEvent $event)
    {
        $context = $event->getContext();
        $configContainer = new \stdClass();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));
        $version = $this->pluginRepository->search($criteria, $context)->first()->getVersion();
        $configContainer->enderecoAgentInfo = 'Endereco Shopware6 Client v' . $version;
        $configContainer->enderecoVersion = $version;
        $configContainer->defaultCountrySelect = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountry');
        $configContainer->defaultCountrySelect = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountry');
        $configContainer->defaultCountry = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountryCode');
        $configContainer->enderecoApiKey = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoApiKey');
        $configContainer->enderecoRemoteUrl = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoRemoteUrl');
        $configContainer->enderecoTriggerOnBlur = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnBlur');
        $configContainer->enderecoTriggerOnSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnSubmit');
        $configContainer->enderecoSmartAutocomplete = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoSmartAutocomplete');
        $configContainer->enderecoContinueSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoContinueSubmit');


        $countries = $this->countryRepository->search(new Criteria(), $context);

        $mapping = [];
        $mappingReverse = [];
        foreach ($countries as $country) {
            $mapping[strtolower($country->getIso())] = $country->getId();
            $mappingReverse[$country->getId()] = strtolower($country->getIso());
        }

        $configContainer->countryMapping = json_encode($mapping);
        $configContainer->countryMappingReverse = json_encode($mappingReverse);

        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }
}
