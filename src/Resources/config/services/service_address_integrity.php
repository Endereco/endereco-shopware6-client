<?php

/**
 * Address integrity checker general services configuration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateChecker;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Verifies if stored address validation data is current.
     * Checks if address components have changed since last validation.
     */
    $services->set(IsAmsRequestPayloadIsUpToDateChecker::class)
        ->args([
            '$addressCheckPayloadBuilder' => service(AddressCheckPayloadBuilderInterface::class),
        ]);
    $services->alias(IsAmsRequestPayloadIsUpToDateCheckerInterface::class, IsAmsRequestPayloadIsUpToDateChecker::class);
};
