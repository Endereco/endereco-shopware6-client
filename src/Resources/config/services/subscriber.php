<?php

/**
 * Event subscriber configuration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\BySystemConfigFilterInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\EnderecoService\AgentInfoGeneratorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\PluginVersionFetcherInterface;
use Endereco\Shopware6Client\Service\OrderAddressToCustomerAddressDataMatcherInterface;
use Endereco\Shopware6Client\Service\OrdersCustomFieldsUpdaterInterface;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Endereco\Shopware6Client\Subscriber\AddDataToPageSubscriber;
use Endereco\Shopware6Client\Subscriber\ConfigurationCacheInvalidationSubscriber;
use Endereco\Shopware6Client\Subscriber\ConvertCartToOrderSubscriber;
use Endereco\Shopware6Client\Subscriber\CustomerAddressSubscriber;
use Endereco\Shopware6Client\Subscriber\OrderAddressSubscriber;
use Endereco\Shopware6Client\Subscriber\OrderSubscriber;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(AddDataToPageSubscriber::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class),
            '$enderecoService' => service(EnderecoService::class),
            '$agentInfoGenerator' => service(AgentInfoGeneratorInterface::class),
            '$pluginVersionFetcher' => service(PluginVersionFetcherInterface::class),
            '$countryRepository' => service('country.repository'),
            '$stateRepository' => service('country_state.repository'),
            '$salutationRepository' => service('salutation.repository'),
            '$pluginRepository' => service('plugin.repository'),
            '$additionalAddressFieldChecker' => service(AdditionalAddressFieldCheckerInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ConvertCartToOrderSubscriber::class)
        ->args([
            '$orderAddressToCustomerAddressDataMatcher'
                => service(OrderAddressToCustomerAddressDataMatcherInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerAddressSubscriber::class)
        ->args([
            '$processContext' => service(ProcessContextService::class),
            '$addressCheckPayloadBuilder' => service(AddressCheckPayloadBuilderInterface::class),
            '$systemConfigService' => service(SystemConfigService::class),
            '$enderecoService' => service(EnderecoService::class),
            '$customerRepository' => service('customer.repository'),
            '$customerAddressRepository' => service('customer_address.repository'),
            '$enderecoAddressExtensionRepository' => service('endereco_customer_address_ext_gh.repository'),
            '$countryRepository' => service('country.repository'),
            '$countryStateRepository' => service('country_state.repository'),
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$customerAddressIntegrityInsurance' => service(CustomerAddressIntegrityInsuranceInterface::class),
            '$requestStack' => service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderAddressSubscriber::class)
        ->args([
            '$orderAddressIntegrityInsurance' => service(OrderAddressIntegrityInsuranceInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderSubscriber::class)
        ->args([
            '$orderAddressRepository' => service('order_address.repository'),
            '$bySystemConfigFilter' => service(BySystemConfigFilterInterface::class),
            '$ordersCustomFieldsUpdater' => service(OrdersCustomFieldsUpdaterInterface::class),
        ])
        ->tag('kernel.event_subscriber');
    
    $services->set(ConfigurationCacheInvalidationSubscriber::class)
        ->args([
            '$cache' => service('endereco.filesystem_tag_aware_adapter'),
        ])
        ->tag('kernel.event_subscriber');
};
