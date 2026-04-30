<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Service\PluginStatusService;
use Endereco\Shopware6Client\Twig\EnderecoPluginStatusExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(PluginStatusService::class)
        ->arg('$activePlugins', '%kernel.active_plugins%');

    $services->set(EnderecoPluginStatusExtension::class)
        ->tag('twig.extension');
};
