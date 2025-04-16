<?php

/**
 * Address correction support service configuration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilder;
use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitter;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterWithCache;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGeneratorInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Build a scope object that determines if native address fields should be overwritten or not
     * based on the current system configuration and data from the address extension.
     */
    $services->set(AddressCorrectionScopeBuilder::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class),
        ]);
    $services->alias(AddressCorrectionScopeBuilderInterface::class, AddressCorrectionScopeBuilder::class);

    $services->set(StreetSplitter::class)
        ->args([
            '$systemConfigService' => service(SystemConfigService::class),
            '$requestHeadersGenerator' => service(RequestHeadersGeneratorInterface::class),
            '$payloadPreparator' => service(PayloadPreparatorInterface::class),
            '$logger' => service('Endereco\Shopware6Client\Run\Logger'),
        ]);
    $services->set(StreetSplitterWithCache::class)
        ->args([
            '$cache' => service('endereco_service_cache'),
            '$streetSplitter' => service(StreetSplitter::class),
        ]);
    $services->alias(StreetSplitterInterface::class, StreetSplitterWithCache::class);
};
