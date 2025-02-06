<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\CustomerAddressCacheInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsStreetSplitRequiredCheckerInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AddressExtensionExistsInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AmsRequestPayloadIsUpToDateInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\AmsStatusIsSetInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\FlagIsSetInsurance\AmazonFlagIsSetInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\FlagIsSetInsurance\PayPalExpressFlagIsSetInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\IntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\StreetIsSplitInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddressIntegrityInsurance;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\CustomerAddressSyncer;
use Endereco\Shopware6Client\Service\AddressIntegrity\Sync\CustomerAddressSyncerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Configures services related to address integrity checks for customer addresses.
 *
 * It registers various "insurances" that ensure address data is complete and valid.
 * These insurances handle tasks such as verifying the existence of an address extension in the
 * database, splitting street data properly, invalidating outdated address validation data,
 * and synchronizing address flags (e.g., for Amazon Pay or PayPal Express).
 *
 * @param ContainerConfigurator $containerConfigurator The container configurator used to define services
 *
 * @return void
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    /**
     * Configure default service definitions to automatically wire and configure them.
     */
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Tag any services implementing IntegrityInsurance so they can be collected and run together.
     */
    $services
        ->instanceof(IntegrityInsurance::class)
        ->tag('endereco.shopware6_client.customer_address_integrity_insurance');

    /**
     * Ensures the address extension entity exists both in the entity and the database.
     * This insurance creates or verifies the EnderecoCustomerAddressExtension record as necessary.
     */
    $services->set(AddressExtensionExistsInsurance::class)
        ->args([
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Ensures that the Shopware "street" field is properly split into "street" and "housenumber" in the extension.
     * This is crucial for customers that require explicit separate street and house number fields.
     */
    $services->set(StreetIsSplitInsurance::class)
        ->args([
            '$isStreetSplitRequiredChecker' => service(IsStreetSplitRequiredCheckerInterface::class),
            '$countryCodeFetcher' => service(CountryCodeFetcherInterface::class),
            '$enderecoService' => service(EnderecoService::class),
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
            '$customerAddressRepository' => service('customer_address.repository'),
            '$additionalAddressFieldChecker' => service(AdditionalAddressFieldCheckerInterface::class),
        ]);

    /**
     * Invalidates address validation data if the current payload is outdated or no longer valid.
     * Also removes any stale validation payload to force a re-validation.
     */
    $services->set(AmsRequestPayloadIsUpToDateInsurance::class)
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
    $services->set(AmsStatusIsSetInsurance::class)
        ->args([
            '$isAmsRequestPayloadIsUpToDateChecker' =>
                service(IsAmsRequestPayloadIsUpToDateCheckerInterface::class),
            '$enderecoService' => service(EnderecoService::class),
        ]);

    /**
     * Sets an Amazon Pay flag in the extension if the address originates from an Amazon Pay checkout process.
     */
    $services->set(AmazonFlagIsSetInsurance::class)
        ->args([
            '$customerRepository' => service('customer.repository'),
            '$addressExtensionRepository' => service(
                EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.repository'
            ),
        ]);

    /**
     * Sets a PayPal Express flag in the extension if the address originates from a PayPal Express checkout process.
     */
    $services->set(PayPalExpressFlagIsSetInsurance::class)
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
    $services->set(CustomerAddressIntegrityInsurance::class)
        ->args([
            '$addressCache' => service(CustomerAddressCacheInterface::class),
            '$addressSyncer' => service(CustomerAddressSyncerInterface::class),
            '$insurances' => tagged_iterator(
                'endereco.shopware6_client.customer_address_integrity_insurance',
                null,
                null,
                'getPriority'
            )
        ]);

    /**
     * Define an alias so that wherever CustomerAddressIntegrityInsuranceInterface is required,
     * we use the concrete CustomerAddressIntegrityInsurance class.
     */
    $services->alias(
        CustomerAddressIntegrityInsuranceInterface::class,
        CustomerAddressIntegrityInsurance::class
    );

    /**
     * A service that copies and synchronizes address data from cache to entities.
     * This is useful to ensure to save up on API requests.
     */
    $services->set(CustomerAddressSyncer::class)
        ->args([
            '$addressCache' => service(CustomerAddressCacheInterface::class),
        ]);

    /**
     * Define an alias so that wherever CustomerAddressSyncerInterface is required,
     * we use the concrete CustomerAddressSyncer class.
     */
    $services->alias(CustomerAddressSyncerInterface::class, CustomerAddressSyncer::class);
};
