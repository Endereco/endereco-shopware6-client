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
    public function checkAddress(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId,
        string $sessionId = ''
    ): AddressCheckResult {
        $dataHash = $this->generateDataHash($addressEntity, $context);
        $cacheKey = sprintf(self::CACHE_KEY_TEMPLATE, $dataHash);

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use (
                $addressEntity,
                $context,
                $salesChannelId,
                $sessionId
            ): AddressCheckResult {
                $item->tag(self::CACHE_TAG);
                $item->expiresAfter(self::CACHE_TTL);

                return $this->addressChecker->checkAddress(
                    $addressEntity,
                    $context,
                    $salesChannelId,
                    $sessionId
                );
            }
        );
    }

    private function generateDataHash(CustomerAddressEntity $addressEntity, Context $context): string
    {
        $addressCheckPayload = $this->addressCheckPayloadBuilder->buildFromCustomerAddress($addressEntity, $context);

        return md5($addressCheckPayload->toJSON());
    }
}
