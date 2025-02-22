<?php

/**
 * Address correction support service configuration
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldChecker;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilder;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcher;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryHasStatesChecker;
use Endereco\Shopware6Client\Service\AddressCheck\CountryHasStatesCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\LocaleFetcher;
use Endereco\Shopware6Client\Service\AddressCheck\LocaleFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCheck\SubdivisionCodeFetcher;
use Endereco\Shopware6Client\Service\AddressCheck\SubdivisionCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilder;
use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilderInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
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
};
