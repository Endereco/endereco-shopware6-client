<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\ApiConfiguration;

use Endereco\Shopware6Client\DTO\ApiConfiguration;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Cached decorator for API configuration fetching to improve performance.
 * 
 * This decorator wraps the base ApiConfigurationFetcher and adds caching to avoid
 * repeated system configuration lookups, which can be expensive during high-frequency
 * address validation requests.
 * 
 * CACHE STRATEGY: Uses tagged cache entries with fixed TTL, allowing targeted
 * cache invalidation when plugin configuration changes without affecting other cache entries.
 */
final class ApiConfigurationFetcherWithCache implements ApiConfigurationFetcherInterface
{
    public const CACHE_TAG = 'endereco_api_config';
    private const CACHE_KEY_TEMPLATE = 'endereco_api_config.%s';
    private const CACHE_TTL = 86400;

    private TagAwareCacheInterface $cache;
    private ApiConfigurationFetcherInterface $apiConfigurationFetcher;

    public function __construct(
        TagAwareCacheInterface $cache,
        ApiConfigurationFetcherInterface $apiConfigurationFetcher
    ) {
        $this->cache = $cache;
        $this->apiConfigurationFetcher = $apiConfigurationFetcher;
    }

    /**
     * Fetches API configuration with caching support.
     * 
     * This method first checks the cache for existing configuration data.
     * If not found, it delegates to the wrapped fetcher and caches the result
     * with appropriate tags for targeted invalidation.
     * 
     * @param string|null $salesChannelId The sales channel ID for channel-specific config
     * @return ApiConfiguration The cached or freshly fetched API configuration
     */
    public function fetchConfiguration(?string $salesChannelId): ApiConfiguration
    {
        $cacheKey = sprintf(self::CACHE_KEY_TEMPLATE, $salesChannelId ?? 'default');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($salesChannelId): ApiConfiguration {
            $apiConfiguration = $this->apiConfigurationFetcher->fetchConfiguration($salesChannelId);

            // Tag the cache entry for targeted invalidation when configuration changes
            $item->tag(self::CACHE_TAG);
            $item->expiresAfter(self::CACHE_TTL);

            return $apiConfiguration;
        });
    }
}
