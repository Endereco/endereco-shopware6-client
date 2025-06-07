<?php

/**
 * API configuration services configuration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcher;
use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherInterface;
use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherWithCache;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();


    $services->set(ApiConfigurationFetcher::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class),
        ]);
    $services->set(ApiConfigurationFetcherWithCache::class)
        ->args([
            '$cache' => service('endereco.filesystem_tag_aware_adapter'),
            '$apiConfigurationFetcher' => service(ApiConfigurationFetcher::class),
        ]);
    $services->alias(ApiConfigurationFetcherInterface::class, ApiConfigurationFetcher::class);
};
