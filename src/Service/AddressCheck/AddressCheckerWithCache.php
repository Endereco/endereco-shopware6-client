<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckResult;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AddressCheckerWithCache implements AddressCheckerInterface
{
    public const CACHE_TAG = 'address_check';

    private const CACHE_KEY_TEMPLATE = 'address_check.%s';

    private const CACHE_TTL = 3600;

    private TagAwareCacheInterface $cache;

    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;

    private AddressCheckerInterface $addressChecker;

    public function __construct(
        TagAwareCacheInterface $cache,
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder,
        AddressCheckerInterface $addressChecker
    ) {
        $this->cache = $cache;
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
        $this->addressChecker = $addressChecker;
    }

    /**
     * Performs address validation with caching support.
     *
     * For cache misses: validates address, caches result without session ID, returns original with session ID
     * For cache hits: returns cached result (which already has no session ID)
     *
     * @param CustomerAddressEntity $addressEntity The address entity to validate
     * @param Context $context The Shopware context
     * @param string $salesChannelId The sales channel ID
     * @param string $sessionId Session ID for fresh validations
     * @return AddressCheckResult Validation result
     */
    public function checkAddress(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId,
        string $sessionId = ''
    ): AddressCheckResult {
        $dataHash = $this->generateDataHash($addressEntity, $context);
        $cacheKey = sprintf(self::CACHE_KEY_TEMPLATE, $dataHash);

        $freshResult = null;

        $cachedResult = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $addressEntity,
            $context,
            $salesChannelId,
            $sessionId,
            &$freshResult
        ): AddressCheckResult {
            // Cache miss - perform fresh validation
            $freshResult = $this->addressChecker->checkAddress(
                $addressEntity,
                $context,
                $salesChannelId,
                $sessionId
            );

            // Cache copy without session ID
            $cachedCopy = clone $freshResult;
            $cachedCopy->setUsedSessionId('');

            $item->tag(self::CACHE_TAG);
            $item->expiresAfter(self::CACHE_TTL);

            return $cachedCopy;
        });

        // If freshResult is set, callback was executed (cache miss) - return fresh result with session ID
        // If freshResult is null, cache hit - return cached result (without session ID)
        return $freshResult ?? $cachedResult;
    }

    /**
     * Generates a hash for cache key that includes both address content and entity ID.
     *
     * This ensures that different address entities with identical content (e.g., during QA testing)
     * generate separate cache entries, preventing cache collisions and ensuring proper billing.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to generate hash for
     * @param Context $context The Shopware context
     * @return string MD5 hash combining address content and entity ID
     */
    private function generateDataHash(CustomerAddressEntity $addressEntity, Context $context): string
    {
        $addressCheckPayload = $this->addressCheckPayloadBuilder->buildFromCustomerAddress($addressEntity, $context);

        // Include address entity ID to ensure different records with same content get separate cache entries
        $cacheData = [
            'addressContent' => $addressCheckPayload->toJSON(),
            'entityId' => $addressEntity->getId()
        ];

        $jsonData = json_encode($cacheData);
        if ($jsonData === false) {
            throw new \RuntimeException('Failed to encode cache data to JSON');
        }

        return md5($jsonData);
    }
}
