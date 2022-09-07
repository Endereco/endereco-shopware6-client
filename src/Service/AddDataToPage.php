<?php
namespace Endereco\Shopware6Client\Service;

use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class AddDataToPage implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EntityRepository
     */
    private $salesChannelDomainRepository;

    /**
     * @var EntityRepository
     */
    private $countryRepository;

    /**
     * @var EntityRepository
     */
    private $stateRepository;

    /**
     * @var EntityRepository
     */
    private $pluginRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $salesChannelDomainRepository,
        EntityRepository $countryRepository,
        EntityRepository $stateRepository,
        EntityRepository $pluginRepository
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
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
        $configContainer = new \stdClass();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));
        $version = $this->pluginRepository->search($criteria, $context)->first()->getVersion();
        $configContainer->enderecoAgentInfo = 'Endereco Shopware6 Client (Download) v' . $version;
        $configContainer->enderecoVersion = $version;
        $configContainer->defaultCountrySelect = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountry');
        $configContainer->defaultCountry = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoPreselectDefaultCountryCode');
        $configContainer->enderecoApiKey = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoApiKey');
        $configContainer->enderecoRemoteUrl = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoRemoteUrl');
        $configContainer->enderecoTriggerOnBlur = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnBlur');
        $configContainer->enderecoTriggerOnSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoTriggerOnSubmit');
        $configContainer->enderecoSmartAutocomplete = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoSmartAutocomplete');
        $configContainer->enderecoContinueSubmit = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoContinueSubmit');
        $configContainer->enderecoAllowCloseIcon = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoAllowCloseIcon');
        $configContainer->enderecoConfirmWithCheckbox = $this->systemConfigService->get('EnderecoShopware6Client.config.enderecoConfirmWithCheckbox');

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
        $salesChannelId = $context->getSource()->getSalesChannelId();

        $salesChannelDomains = $this->salesChannelDomainRepository->search(
            new Criteria(),
            $context
        );

        $firstDomain = $salesChannelDomains->first()->getUrl();

        if(!empty($_SERVER['HTTP_HOST'])) {
            $scheme = ('http' === $_SERVER['REQUEST_SCHEME']) ? 'http://' : 'https://';
            $currentHost = $_SERVER['HTTP_HOST'];
            foreach ($salesChannelDomains as $domain) {
                $salesChannelDomain = $domain->getUrl();
                if ((strpos($salesChannelDomain, $currentHost) !== false)
                    && (strpos($salesChannelDomain, $scheme) !== false)) {
                    $firstDomain = $salesChannelDomain;
                }
            }
        }

        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        if (file_exists($pathToCopyIoPhp)) {
            $configContainer->pathToIoPhp = $firstDomain.'/io.php';
        } else {
            $configContainer->pathToIoPhp = $firstDomain.'/bundles/enderecoshopware6client/io.php';
        }

        $event->getPage()->assign(['endereco_config' => $configContainer]);
    }
}
