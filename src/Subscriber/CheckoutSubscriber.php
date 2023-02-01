<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutSubscriber extends AbstractEnderecoSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [CheckoutConfirmPageLoadedEvent::class => 'ensureAddressesAreSplit'];
    }

    public function ensureAddressesAreSplit(CheckoutConfirmPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        if (!$this->isStreetSplittingEnabled($salesChannelContext->getSalesChannelId())) {
            return;
        }

        $customer = $salesChannelContext->getCustomer();
        if (is_null($customer)) {
            return;
        }
        $this->ensureAddressIsSplit($salesChannelContext->getContext(), $customer->getActiveShippingAddress());
        $this->ensureAddressIsSplit($salesChannelContext->getContext(), $customer->getActiveBillingAddress());
    }
}
