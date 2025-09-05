<?php

/**
 * Configures services related to address integrity checks for customer addresses.
 *
 * It registers various "insurances" that ensure address data is complete and valid.
 * These insurances handle tasks such as verifying the existence of an address extension in the
 * database, splitting street data properly, invalidating outdated address validation data,
 * and synchronizing address flags (e.g., for Amazon Pay or PayPal Express).
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\SetExtension;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\SetSkipFlag;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressPersistenceStrategyProvider;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressPersistenceStrategyProviderInterface;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\AssessValidationStatus;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Validate;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\SetAmazonFlag;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\SetPayPalExpressFlag;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\ParseStreet;
use Endereco\Shopware6Client\CustomerAddressPipeline\Pipeline;
use Endereco\Shopware6Client\CustomerAddressPipeline\PipelineInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * Configure default service definitions to automatically wire and configure them.
     */
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Tag any services implementing OperationInterface so they can be collected and run together.
     */
    $services
        ->instanceof(Operation::class)
        ->tag('endereco.shopware6_client.customer_address_integrity_insurance');

    /**
     * Checks prerequisites for running the customer address pipeline.
     * This operation runs first (priority 0) and can skip remaining operations.
     */
    $services->set(SetSkipFlag::class)
        ->args([
            '$processContext' => service(ProcessContextService::class),
            '$enderecoService' => service(EnderecoService::class),
        ]);

    /**
     * Ensures the address extension entity exists both in the entity and the database.
     * This insurance creates or verifies the EnderecoCustomerAddressExtension record as necessary.
     */
    $services->set(SetExtension::class)
        ->args([
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Ensures that the Shopware "street" field is properly split into "street" and "housenumber" in the extension.
     * This is crucial for customers that require explicit separate street and house number fields.
     */
    $services->set(ParseStreet::class)
        ->args([
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$streetSplitter' => service(StreetSplitterInterface::class),
            '$enderecoService' => service(EnderecoService::class),
            '$addressPersistenceStrategyProvider' => service(AddressPersistenceStrategyProviderInterface::class),
            '$additionalAddressFieldChecker' => service(AdditionalAddressFieldCheckerInterface::class),
        ]);


    /**
     * Provides an address persistence strategy that decides which how address data from a street split
     * should be persisted and fetched.
     */
    $services->set(AddressPersistenceStrategyProvider::class)
        ->args([
            '$addressCorrectionScopeBuilder' => service(AddressCorrectionScopeBuilderInterface::class),
            '$additionalAddressFieldChecker' => service(AdditionalAddressFieldCheckerInterface::class),
            '$customerAddressRepository' => service('customer_address.repository'),
            '$customerAddressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            )
        ]);
    $services->alias(AddressPersistenceStrategyProviderInterface::class, AddressPersistenceStrategyProvider::class);

    /**
     * Assesses validation status by checking if current address data matches stored validation payload.
     * If validation is current and complete, skips remaining operations. Otherwise, clears outdated metadata.
     */
    $services->set(AssessValidationStatus::class)
        ->args([
            '$isAmsRequestPayloadIsUpToDateChecker' =>
                service(IsAmsRequestPayloadIsUpToDateCheckerInterface::class),
            '$enderecoService' => service(EnderecoService::class),
        ]);

    /**
     * If needed, triggers an address check using Endereco, saves the validation data, and
     * generates an up-to-date payload for future reference. Only invoked when there's no
     * existing or valid AMS status.
     */
    $services->set(Validate::class)
        ->args([
            '$isAmsRequestPayloadIsUpToDateChecker' =>
                service(IsAmsRequestPayloadIsUpToDateCheckerInterface::class),
            '$addressChecker' => service(AddressCheckerInterface::class),
            '$enderecoService' => service(EnderecoService::class),
            '$processContext' => service(ProcessContextService::class),
        ]);

    /**
     * Sets an Amazon Pay flag in the extension if the address originates from an Amazon Pay checkout process.
     */
    $services->set(SetAmazonFlag::class)
        ->args([
            '$customerRepository' => service('customer.repository'),
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Sets a PayPal Express flag in the extension if the address originates from a PayPal Express checkout process.
     */
    $services->set(SetPayPalExpressFlag::class)
        ->args([
            '$customerRepository' => service('customer.repository'),
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Coordinates all other insurances to ensure that the address data is in the correct form
     * and contains all necessary information (e.g., extension data, valid street data).
     *
     * The tagged_iterator collects all services tagged with
     * 'endereco.shopware6_client.customer_address_integrity_insurance' and runs them in order.
     */
    $services->set(Pipeline::class)
        ->args([
            '$operations' => tagged_iterator(
                'endereco.shopware6_client.customer_address_integrity_insurance',
                null,
                null,
                'getPriority'
            )
        ]);

    /**
     * Define an alias so that wherever PipelineInterface is required,
     * we use the concrete Pipeline class.
     */
    $services->alias(
        PipelineInterface::class,
        Pipeline::class
    );
};
