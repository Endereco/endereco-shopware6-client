<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs failed request signature validations throttled, so an attack with many
 * unsigned/forged requests doesn't flood the log.
 *
 * At most one warning per sales channel within LOG_INTERVAL_SECONDS. Failures in
 * between are counted and reported as "suppressed_since_last_log" with the next entry.
 *
 * State lives in the same backend-agnostic cache pool Shopware's own rate limiters
 * use (cache.rate_limiter).
 */
final class RequestSignatureFailureLogger implements RequestSignatureFailureLoggerInterface
{
    private const LOG_INTERVAL_SECONDS = 600;

    private CacheItemPoolInterface $cache;

    private LoggerInterface $logger;

    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function logFailure(?string $salesChannelId): void
    {
        $keySuffix = md5($salesChannelId ?? '');

        $throttleKey = 'endereco_signature_log_throttle_' . $keySuffix;
        $suppressedKey = 'endereco_signature_log_suppressed_' . $keySuffix;

        // Fetched together so cache backends like Redis need a single round trip.
        // PSR-6 allows getItems() to return a Traversable (Symfony returns a Generator).
        $items = $this->cache->getItems([$throttleKey, $suppressedKey]);
        $items = $items instanceof \Traversable ? iterator_to_array($items) : $items;

        $throttleItem = $items[$throttleKey];
        $suppressedItem = $items[$suppressedKey];

        $suppressed = $suppressedItem->isHit() ? (int) $suppressedItem->get() : 0;

        if ($throttleItem->isHit()) {
            $suppressedItem->set($suppressed + 1);
            $this->cache->save($suppressedItem);

            return;
        }

        $this->logger->warning('Endereco proxy request failed signature validation', [
            'suppressed_since_last_log' => $suppressed,
            'sales_channel_id' => $salesChannelId,
        ]);

        $suppressedItem->set(0);
        $this->cache->save($suppressedItem);

        $throttleItem->set(true);
        $throttleItem->expiresAfter(self::LOG_INTERVAL_SECONDS);
        $this->cache->save($throttleItem);
    }
}
