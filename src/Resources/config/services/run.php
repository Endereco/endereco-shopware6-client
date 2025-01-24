<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Run\LoggerFactory;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(LoggerFactory::class);
    $services->set('Endereco\Shopware6Client\Run\Logger', Logger::class)
        ->factory([service(LoggerFactory::class), 'createRotating'])
        ->args([
            '$filePrefix' => 'endereco_shopware6_client'
        ]);
};
