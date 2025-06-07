<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/services/cache.php');
    $containerConfigurator->import(__DIR__ . '/services/console.php');
    $containerConfigurator->import(__DIR__ . '/services/controller.php');
    $containerConfigurator->import(__DIR__ . '/services/entity.php');
    $containerConfigurator->import(__DIR__ . '/services/http_client.php');
    $containerConfigurator->import(__DIR__ . '/services/run.php');
    $containerConfigurator->import(__DIR__ . '/services/service.php');
    $containerConfigurator->import(__DIR__ . '/services/service_address_check.php');
    $containerConfigurator->import(__DIR__ . '/services/service_address_correction.php');
    $containerConfigurator->import(__DIR__ . '/services/service_address_integrity.php');
    $containerConfigurator->import(__DIR__ . '/services/service_api_configuration.php');
    $containerConfigurator->import(__DIR__ . '/services/service_customer_address_integrity.php');
    $containerConfigurator->import(__DIR__ . '/services/service_endereco_service.php');
    $containerConfigurator->import(__DIR__ . '/services/service_order_address_integrity.php');
    $containerConfigurator->import(__DIR__ . '/services/subscriber.php');
};
