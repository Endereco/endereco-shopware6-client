<?php

/**
 * Security-related services (rate limiting / IP jail support)
 */

declare(strict_types=1);

use Endereco\Shopware6Client\Service\Security\ConfigurableRateLimiter;
use Endereco\Shopware6Client\Service\Security\ConfigurableRateLimiterInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /**
     * Same storage backend Shopware's own rate limiters use (cache.rate_limiter),
     * reused here so state stays consistent and backend-agnostic.
     */
    $services->set('endereco.rate_limiter_storage', CacheStorage::class)
        ->args([service('cache.rate_limiter')]);

    /**
     * Sliding-window rate limiting with a configurable limit (read from
     * System Config on every call), since Shopware's own YAML-based rate limiter
     * only supports runtime-configurable limits for the "time_backoff"/
     * "system_config" policies, not "sliding_window".
     */
    $services->set(ConfigurableRateLimiter::class)
        ->args([
            '$storage' => service('endereco.rate_limiter_storage'),
            '$logger' => service('monolog.logger.endereco_shopware6_client'),
        ]);
    $services->alias(ConfigurableRateLimiterInterface::class, ConfigurableRateLimiter::class);
};
