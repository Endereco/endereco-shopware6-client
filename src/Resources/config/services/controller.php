<?php

/**
 * Registers and configures API and Storefront controllers
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Controller\Api\ApiTestController;
use Endereco\Shopware6Client\Controller\Storefront\EnderecoApiProxyController;
use Endereco\Shopware6Client\Controller\Storefront\AddressController;
use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\Security\ConfigurableRateLimiterInterface;
use Endereco\Shopware6Client\Service\Security\RequestSignatureFailureLoggerInterface;
use Endereco\Shopware6Client\Service\Security\RequestSignatureValidatorInterface;
use Endereco\Shopware6Client\Service\SessionManagementService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
            '$eventDispatcher' => service('event_dispatcher'),
        ])
        ->call('setContainer', [
            service('service_container')
        ])
        ->public();

    $services->set(EnderecoApiProxyController::class)
        ->args([
            '$httpClient' => service('endereco.http_client'),
            '$apiConfigurationFetcher' => service(ApiConfigurationFetcherInterface::class),
            '$logger' => service('monolog.logger.endereco_shopware6_client'),
            '$configurableRateLimiter' => service(ConfigurableRateLimiterInterface::class),
            '$systemConfigService' => service(SystemConfigService::class),
            '$requestSignatureValidator' => service(RequestSignatureValidatorInterface::class),
            '$requestSignatureFailureLogger' => service(RequestSignatureFailureLoggerInterface::class),
        ])
        ->tag('controller.service_arguments')
        ->public();
};
