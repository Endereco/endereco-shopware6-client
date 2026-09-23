<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;

interface ConfigurableRateLimiterInterface
{
    /**
     * @param string $id Logical limiter name, used to namespace the cache key (e.g. "endereco_per_ip")
     * @param string $key The value being rate limited (e.g. client IP, session token, or a fixed string for a
     *                     global limiter)
     * @param int $limit Limit, that is read from the plugin configuration
     * @param string $interval Sliding window size, e.g. "1 hour"
     * @param string $logLevel Log level for the log message when limit is reached
     * @param string $salesChannelId id of the sales channel, the limiter is used in
     *
     * @throws RateLimitExceededException
     */
    public function ensureAccepted(
        string $id,
        string $key,
        int $limit,
        string $interval,
        string $logLevel,
        string $salesChannelId,
    ): void;
}
