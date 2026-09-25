<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Sliding-window rate limiting whose limit value is read from System Config on every
 * call, instead of being frozen into the compiled container via Shopware's YAML-based
 * `shopware.api.rate_limiter` mechanism (which only supports runtime-configurable
 * values for the "time_backoff"/"system_config" policies, not "sliding_window").
 *
 * Leaves Symfony's own rate limiter algorithm and storage untouched - only the
 * limit is made flexible.
 */
final class ConfigurableRateLimiter implements ConfigurableRateLimiterInterface
{
    private StorageInterface $storage;

    private LoggerInterface $logger;

    public function __construct(StorageInterface $storage, LoggerInterface $logger)
    {
        $this->storage = $storage;
        $this->logger = $logger;
    }

    public function ensureAccepted(
        string $id,
        string $key,
        int $limit,
        string $interval,
        string $logLevel,
        string $salesChannelId,
    ): void {

        if ($limit <= 0) {
            // A limit of 0 or negative is a misconfiguration.
            // Without this guard, Symfony's SlidingWindowLimiter::reserve() would throw an
            // InvalidArgumentException, that would surface as an uncaught 500.
            return;
        }

        $factory = new RateLimiterFactory(
            ['id' => $id, 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => $interval],
            $this->storage
        );

        $rateLimit = $factory->create($key)->consume();

        if ($rateLimit->getRemainingTokens() === 0 && $rateLimit->isAccepted()) {
            // Use the same logic to calculate the wait time as Shopware's own rate limiters
            $waitTime = max(0, $rateLimit->getRetryAfter()->getTimestamp() - time());

            $this->logger->$logLevel('Rate Limit reached', [
                'tier' => $id,
                'key' => $this->anonymizeKeyForLog($id, $key),
                'limit' => $limit,
                'wait_time' => $waitTime,
                'sales_channel_id' => $salesChannelId,
            ]);
        }

        if (!$rateLimit->isAccepted()) {
            throw new RateLimitExceededException($rateLimit->getRetryAfter()->getTimestamp());
        }
    }

    private function anonymizeKeyForLog(string $tier, string $key): string
    {
        return match ($tier) {
            'endereco_per_ip' => $this->anonymizeIp($key),
            'endereco_per_session' => $this->anonymizeSessionToken($key),
            'endereco_global_rate_limit' => $key,
            default => 'invalid key'
        };
    }

    private function anonymizeIp(string $ip): string
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return 'unknown'; // invalid ip, e.g. the 'unknown'-fallback
        }

        $mask = strlen($packed) === 4
            ? "\xff\xff\x00\x00"
            : "\xff\xff\xff\xff\xff\xff\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

        $masked = inet_ntop($packed & $mask);

        return $masked === false ? 'unknown' : $masked;
    }

    private function anonymizeSessionToken(string $key): string
    {
        return substr($key, 0, 8) . '…';
    }
}
