<?php

declare(strict_types=1);

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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Address checking support service configuration
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Builds API payloads for address validation requests and for address "hashing"
     * Combines country codes, subdivision codes, and other address data.
     */
    $services->set(AddressCheckPayloadBuilder::class)
        ->args([
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$subdivisionCodeFetcher' => service(SubdivisionCodeFetcherInterface::class),
            '$countryHasStatesChecker' => service(CountryHasStatesCheckerInterface::class)
        ]);
    $services->alias(AddressCheckPayloadBuilderInterface::class, AddressCheckPayloadBuilder::class);

    /**
     * Retrieves ISO country codes from Shopware country entities.
     * Essential for standardized country identification in API calls.
     */
    $services->set(CountryCodeFetcher::class)
        ->args([
            '$countryRepository' => service('country.repository')
        ]);
    $services->alias(CountryCodeFetcherInterface::class, CountryCodeFetcher::class);

    /**
     * Verifies if a country uses state/province subdivisions.
     * Helps determine if state/province data should be included in validation.
     */
    $services->set(CountryHasStatesChecker::class)
        ->args([
            '$countryRepository' => service('country.repository')
        ]);
    $services->alias(CountryHasStatesCheckerInterface::class, CountryHasStatesChecker::class);

    /**
     * Fetches locale information from sales channel domains. Currently not really needed.
     */
    $services->set(LocaleFetcher::class)
        ->args([
            '$salesChannelDomainRepository' => service('sales_channel_domain.repository')
        ]);
    $services->alias(LocaleFetcherInterface::class, LocaleFetcher::class);

    /**
     * Retrieves standardized codes for states/provinces.
     * Ensures consistent subdivision identification in API requests.
     */
    $services->set(SubdivisionCodeFetcher::class)
        ->args([
            '$countryStateRepository' => service('country_state.repository')
        ]);
    $services->alias(SubdivisionCodeFetcherInterface::class, SubdivisionCodeFetcher::class);
};
