<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Service\EnderecoService\AgentInfoGenerator;
use Endereco\Shopware6Client\Service\EnderecoService\AgentInfoGeneratorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparator;
use Endereco\Shopware6Client\Service\EnderecoService\PayloadPreparatorInterface;
use Endereco\Shopware6Client\Service\EnderecoService\PluginVersionFetcher;
use Endereco\Shopware6Client\Service\EnderecoService\PluginVersionFetcherInterface;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGenerator;
use Endereco\Shopware6Client\Service\EnderecoService\RequestHeadersGeneratorInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(AgentInfoGenerator::class)
        ->args([
            '$pluginVersionFetcher' => service(PluginVersionFetcherInterface::class)
        ]);
    $services->alias(AgentInfoGeneratorInterface::class, AgentInfoGenerator::class);

    $services->set(PayloadPreparator::class);
    $services->alias(PayloadPreparatorInterface::class, PayloadPreparator::class);

    $services->set(PluginVersionFetcher::class)
        ->args([
            '$pluginRepository' => service('plugin.repository')
        ]);
    $services->alias(PluginVersionFetcherInterface::class, PluginVersionFetcher::class);

    $services->set(RequestHeadersGenerator::class)
        ->args([
            '$agentInfoGenerator' => service(AgentInfoGeneratorInterface::class),
            '$systemConfigService' => service(SystemConfigService::class)
        ]);
    $services->alias(RequestHeadersGeneratorInterface::class, RequestHeadersGenerator::class);
};
