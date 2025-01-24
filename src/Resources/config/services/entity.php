<?php

declare(strict_types=1);

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Registers Endereco address extensions and definitions
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(CustomerAddressExtension::class)->tag('shopware.entity.extension');
    $services->set(EnderecoCustomerAddressExtensionDefinition::class)->tag('shopware.entity.definition');

    $services->set(OrderAddressExtension::class)->tag('shopware.entity.extension');
    $services->set(EnderecoOrderAddressExtensionDefinition::class)->tag('shopware.entity.definition');
};
