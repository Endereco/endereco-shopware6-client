<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateChecker;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredChecker;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Address integrity checker general services configuration
 */
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

    /**
     * Determines if an address requires street/house number splitting.
     * Uses country-specific rules to validate address format requirements.
     */
    $services->set(IsStreetSplitRequiredChecker::class)
        ->args([
            '$enderecoService' => service(EnderecoService::class),
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
        ]);
    $services->alias(IsStreetSplitRequiredCheckerInterface::class, IsStreetSplitRequiredChecker::class);
};
