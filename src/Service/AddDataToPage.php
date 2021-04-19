<?php
namespace Endereco\Shopware6Client\Service;

use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AddDataToPage implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'addEnderecoConfigToPage'
        ];
    }

    public function addEnderecoConfigToPage(GenericPageLoadedEvent $event)
    {
        $configContainer = new \stdClass();
        $configContainer->defaultCountrySelect = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountry');
        $configContainer->defaultCountry = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountryCode');
        $configContainer->enderecoApiKey = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoApiKey');
        $configContainer->enderecoRemoteUrl = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoRemoteUrl');
        $configContainer->enderecoTriggerOnBlur = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnBlur');
        $configContainer->enderecoTriggerOnSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnSubmit');
        $configContainer->enderecoSmartAutocomplete = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoSmartAutocomplete');
        $configContainer->enderecoContinueSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoContinueSubmit');

        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }
}
