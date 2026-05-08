<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Controller\Api\ApiTestController;
use Endereco\Shopware6Client\Controller\Storefront\EnderecoApiProxyController;
use Endereco\Shopware6Client\Controller\Storefront\AddressController;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\SessionManagementService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Registers and configures API and Storefront controllers
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ApiTestController::class)
        ->args([
            '$enderecoService' => service(EnderecoService::class),
        ])
        ->call('setContainer', [
            service('service_container')
        ])
        ->public();
    $services->set(AddressController::class)
        ->args([
            '$enderecoService' => service(EnderecoService::class),
            '$sessionManagementService' => service(SessionManagementService::class),
            '$addressRepository' => service('customer_address.repository'),
            '$addressCheckPayloadBuilder' => service(AddressCheckPayloadBuilderInterface::class),
            '$pluginStatusService' => service(\Endereco\Shopware6Client\Service\PluginStatusService::class),
        ])
        ->call('setContainer', [
            service('service_container')
        ])
        ->public();
    
    $services->set(EnderecoApiProxyController::class)
        ->args([
            '$httpClient' => service('endereco.http_client'),
            '$apiConfigurationFetcher' => service(ApiConfigurationFetcherInterface::class),
            '$logger' => service(LoggerInterface::class),
        ])
        ->tag('controller.service_arguments')
        ->public();
};
