<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Service\ApiConfiguration\ApiConfigurationFetcherWithCache;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Event subscriber that invalidates API configuration cache when plugin settings change.
 *
 * This subscriber listens for system configuration changes and automatically
 * invalidates cached API configuration when Endereco plugin settings are modified,
 * ensuring that configuration changes take effect immediately without manual cache clearing.
 *
 * CACHE COHERENCE: Maintains consistency between system configuration and cached
 * API configuration by invalidating cache entries tagged with
 * ApiConfigurationFetcherWithCache::CACHE_TAG whenever any EnderecoShopware6Client
 * configuration value changes.
 */
class ConfigurationCacheInvalidationSubscriber implements EventSubscriberInterface
{
    private TagAwareCacheInterface $cache;

    public function __construct(TagAwareCacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    /**
     * Handles system configuration change events.
     *
     * When any Endereco plugin configuration value changes, this method
     * invalidates all cached API configuration entries to ensure immediate
     * consistency between system config and cached values.
     *
     * @param SystemConfigChangedEvent $event The system config change event
     */
    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        // Only invalidate cache for Endereco plugin configuration changes
        if (str_starts_with($event->getKey(), 'EnderecoShopware6Client.config.')) {
            $this->cache->invalidateTags([ApiConfigurationFetcherWithCache::CACHE_TAG]);
        }
    }
}
