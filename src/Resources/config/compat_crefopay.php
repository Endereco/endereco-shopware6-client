<?php

declare(strict_types=1);

use CrefoPay\Payment\Storefront\EventListener\Cart\CartEventListener;
use Endereco\Shopware6Client\Compatibility\CrefoPay\CartEventListenerDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    /**
     * COUPLING WARNING: ->decorate() targets a concrete CrefoPay class name.
     * EnderecoShopware6Client::isCrefoPayActive() guards against a missing class (boot crash),
     * but silent failures remain: a renamed class with the old name still present, or a same-named
     * class that behaves differently, will both cause Endereco's correction AJAX to clear the
     * CrefoPay session again. Re-verify after every CrefoPay version update.
     */
    $services->set(CartEventListenerDecorator::class)
        // @phpstan-ignore-next-line
        ->decorate(CartEventListener::class)
        ->args([
            service(CartEventListenerDecorator::class . '.inner'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');
};
