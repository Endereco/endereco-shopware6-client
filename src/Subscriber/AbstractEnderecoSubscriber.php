<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEnderecoSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    abstract public static function getSubscribedEvents(): array;

    protected function isStreetSplittingEnabled(?string $salesChannelId): bool
    {
        return
            $this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId) &&
            $this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);
    }

    protected function fetchSalesChannelId(Context $context): ?string
    {
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            return $source->getSalesChannelId();
        }
        return null;
    }
}
