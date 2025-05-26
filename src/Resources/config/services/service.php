<?php

/**
 * Core service configuration for Endereco integration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressPersistenceStrategyProviderInterface;
use Endereco\Shopware6Client\Service\BySystemConfigFilter;
use Endereco\Shopware6Client\Service\BySystemConfigFilterInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\OrderAddressToCustomerAddressDataMatcher;
use Endereco\Shopware6Client\Service\OrderAddressToCustomerAddressDataMatcherInterface;
use Endereco\Shopware6Client\Service\OrderCustomFieldsBuilder;
use Endereco\Shopware6Client\Service\OrderCustomFieldsBuilderInterface;
use Endereco\Shopware6Client\Service\OrdersCustomFieldsUpdater;
use Endereco\Shopware6Client\Service\OrdersCustomFieldsUpdaterInterface;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ProcessContextService::class);

    $services->set(BySystemConfigFilter::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class)
        ]);
    $services->alias(BySystemConfigFilterInterface::class, BySystemConfigFilter::class);

    $services->set(EnderecoService::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class),
            '$pluginRepository' => service('plugin.repository'),
            '$countryStateRepository' => service('country_state.repository'),
            '$customerAddressRepository' => service('customer_address.repository'),
            '$orderAddressRepository' => service('order_address.repository'),
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$addressCheckPayloadBuilder' => service(AddressCheckPayloadBuilderInterface::class),
            '$addressPersistenceStrategyProvider' => service(AddressPersistenceStrategyProviderInterface::class),
            '$requestStack' => service('request_stack'),
            '$logger' => service('Endereco\Shopware6Client\Run\Logger'),
        ])
        ->public();

    $services->set(OrderAddressToCustomerAddressDataMatcher::class);
    $services->alias(
        OrderAddressToCustomerAddressDataMatcherInterface::class,
        OrderAddressToCustomerAddressDataMatcher::class
    );

    $services->set(OrderCustomFieldsBuilder::class);
    $services->alias(OrderCustomFieldsBuilderInterface::class, OrderCustomFieldsBuilder::class);

    $services->set(OrdersCustomFieldsUpdater::class)
        ->args([
            '$orderCustomFieldBuilder' => service(OrderCustomFieldsBuilderInterface::class),
            '$orderRepository' => service('order.repository'),
            '$syncService' => service(SyncService::class),
        ]);
    $services->alias(OrdersCustomFieldsUpdaterInterface::class, OrdersCustomFieldsUpdater::class);
};
