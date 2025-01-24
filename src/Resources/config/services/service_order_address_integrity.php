<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\AddressExtensionExistsInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\AmsRequestPayloadIsUpToDateInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\IntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\StreetIsSplitInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\OrderAddressSyncer;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\OrderAddressSyncerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\OrderAddressCache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Configures integrity checking services for order addresses.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Tags services that implement integrity checks.
     * Allows for automatic collection and execution of all integrity insurances.
     */
    $services
        ->instanceof(IntegrityInsurance::class)
        ->tag('endereco.shopware6_client.order_address_integrity_insurance');

    /**
     * Ensures existence of address extension records.
     * Creates missing extension entities as needed.
     */
    $services->set(AddressExtensionExistsInsurance::class)
        ->args([
            '$addressExtensionRepository' => service(
                EnderecoOrderAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Validates and updates AMS request payloads.
     * Ensures stored validation data remains current or is removed.
     */
    $services->set(AmsRequestPayloadIsUpToDateInsurance::class)
        ->args([
            '$isAmsRequestPayloadIsUpToDateChecker' =>
                service(IsAmsRequestPayloadIsUpToDateCheckerInterface::class),
            '$enderecoService' => service(EnderecoService::class),
        ]);

    /**
     * Ensures that there are always splitted street name and building number in the extension.
     * Handles splitting of street addresses when required.
     */
    $services->set(StreetIsSplitInsurance::class)
        ->args([
            '$isStreetSplitRequiredChecker' => service(IsStreetSplitRequiredCheckerInterface::class),
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$enderecoService' => service(EnderecoService::class),
            '$addressExtensionRepository' => service(
                EnderecoOrderAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Synchronizes address data between cache and entities.
     * Optimizes performance by reducing redundant API calls.
     */
    $services->set(OrderAddressSyncer::class)
        ->args([
            '$addressCache' => service(OrderAddressCache::class),
        ]);
    $services->alias(OrderAddressSyncerInterface::class, OrderAddressSyncer::class);

    /**
     * Coordinates all integrity checks and maintenance.
     * Orchestrates the execution of individual integrity insurances.
     */
    $services->set(OrderAddressIntegrityInsurance::class)
        ->args([
            '$addressCache' => service(OrderAddressCache::class),
            '$addressSyncer' => service(OrderAddressSyncerInterface::class),
            '$insurances' => tagged_iterator(
                'endereco.shopware6_client.order_address_integrity_insurance',
                null,
                null,
                'getPriority'
            )
        ]);
    $services->alias(
        OrderAddressIntegrityInsuranceInterface::class,
        OrderAddressIntegrityInsurance::class
    );
};
