<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCorrection;

use Endereco\Shopware6Client\DTO\SplitStreetResultDto;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * A decorator for StreetSplitterInterface that adds caching functionality to street splitting operations.
 *
 * This class wraps a StreetSplitterInterface implementation and caches its results using a TagAwareCacheInterface.
 * It generates unique cache keys based on input parameters and stores results with a configurable TTL.
 * Cache entries are tagged for easy invalidation.
 */
final class StreetSplitterWithCache implements StreetSplitterInterface
{
    public const CACHE_TAG = 'street_splitting';

    private const CACHE_KEY_TEMPLATE = 'street_splitting.%s';

    private const CACHE_TTL = 7776000; // 90 days (3 months)

    private TagAwareCacheInterface $cache;

    private StreetSplitterInterface $streetSplitter;

    /**
     * Constructs the StreetSplitterWithCache decorator.
     *
     * @param TagAwareCacheInterface $cache The cache service used to store street splitting results.
     * @param StreetSplitterInterface $streetSplitter The decorated street splitter service.
     */
    public function __construct(TagAwareCacheInterface $cache, StreetSplitterInterface $streetSplitter)
    {
        $this->cache = $cache;
        $this->streetSplitter = $streetSplitter;
    }

    /**
     * Splits a full street address into its components, caching the result to improve performance.
     *
     * This method checks the cache for a previously computed result based on the input parameters.
     * If no cached result exists, it delegates to the decorated StreetSplitterInterface, which
     * typically makes an API call to the Endereco service to split the street into components.
     * The result is cached and tagged for invalidation.
     *
     * @param string $fullStreet The full street address (e.g., "Musterstraße 42").
     * @param string|null $additionalInfo Additional address information (e.g., "Wohnung 3").
     * @param string $countryCode The ISO country code (e.g., "DE" for Germany).
     * @param Context $context Shopware context for the request.
     * @param string|null $salesChannelId The ID of the sales channel, if applicable.
     * @return SplitStreetResultDto
     */
    public function splitStreet(
        string $fullStreet,
        ?string $additionalInfo,
        string $countryCode,
        Context $context,
        ?string $salesChannelId
    ): SplitStreetResultDto {
        $dataHash = $this->generateDataHash($fullStreet, $additionalInfo, $countryCode);
        $cacheKey = sprintf(self::CACHE_KEY_TEMPLATE, $dataHash);

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use (
                $fullStreet,
                $additionalInfo,
                $countryCode,
                $context,
                $salesChannelId
            ): SplitStreetResultDto {
                $item->tag(self::CACHE_TAG);
                $item->expiresAfter(self::CACHE_TTL);

                return $this->streetSplitter->splitStreet(
                    $fullStreet,
                    $additionalInfo,
                    $countryCode,
                    $context,
                    $salesChannelId
                );
            }
        );
    }

    /**
     * Generates a unique hash for the input data to use as part of the cache key.
     *
     * The hash is based on the full street, additional info, and country code, ensuring unique cache entries
     * for different inputs.
     *
     * @param string $fullStreet The full street address.
     * @param string|null $additionalInfo Additional address information.
     * @param string $countryCode The ISO country code.
     * @return string A SHA-256 hash of the input data.
     */
    private function generateDataHash(string $fullStreet, ?string $additionalInfo, string $countryCode): string
    {
        $data = [
            'fullStreet' => $fullStreet,
            'additionalInfo' => $additionalInfo ?? 'null',
            'countryCode' => $countryCode,
        ];
        $jsonData = json_encode($data);

        // Fallback. Probably should log this in the future.
        if ($jsonData === false) {
            $jsonData = $fullStreet . '|' . ($additionalInfo ?? 'null') . '|' . $countryCode;
        }

        return hash('sha256', $jsonData);
    }
}
