<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set('endereco.filesystem_tag_aware_adapter', FilesystemTagAwareAdapter::class);
};
