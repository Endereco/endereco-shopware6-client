<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('endereco.http_client', HttpClientInterface::class)
        ->factory([service('http_client'), 'withOptions'])
        ->args([
            [
                'timeout' => 5.0,
                'max_duration' => 10.0,
                'http_version' => '2.0',
            ]
        ]);
};