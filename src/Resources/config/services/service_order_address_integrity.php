<?php

/**
 * Configures integrity checking services for order addresses.
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\AddressExtensionExistsInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\AmsRequestPayloadIsUpToDateInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress\IntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

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
     * Coordinates all integrity checks and maintenance.
     * Orchestrates the execution of individual integrity insurances.
     */
    $services->set(OrderAddressIntegrityInsurance::class)
        ->args([
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
