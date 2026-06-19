<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Compatibility\CrefoPay;

use CrefoPay\Payment\Storefront\EventListener\Cart\CartEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Suppresses CrefoPay's CartEventListener on Endereco's own correction routes to
 * prevent the listener from interfering with address-save requests.
 *
 * COUPLING WARNING: This decorator is bound to a concrete CrefoPay class name.
 * EnderecoShopware6Client::isCrefoPayActive() guards against a missing class (boot crash),
 * but two silent failure modes remain unguarded:
 *   - The class is renamed and the old name still exists: decorator wraps the wrong class.
 *   - The class exists under the same name but behaves differently: decorator has no effect.
 * In both cases Endereco's correction AJAX will clear the CrefoPay session again.
 * Re-verify after every CrefoPay version update.
 */
final class CartEventListenerDecorator implements EventSubscriberInterface
{
    private const ENDERECO_CORRECTION_ROUTES = [
        'frontend.endereco.account.address.edit.save',
        'frontend.endereco.service_proxy'
    ];

    public function __construct(
        // @phpstan-ignore-next-line
        private readonly CartEventListener $inner,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // @phpstan-ignore-next-line
        return CartEventListener::getSubscribedEvents();
    }

    /**
     * Catches every event-handler call that the dispatcher dispatches to this subscriber.
     * Skips the inner subscriber when the current request is an Endereco correction route.
     *
     * @param mixed[] $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($this->isEnderecoRequest()) {
            return null;
        }

        return $this->inner->$name(...$arguments);
    }

    private function isEnderecoRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        return \in_array(
            $request->attributes->get('_route'),
            self::ENDERECO_CORRECTION_ROUTES,
            true
        );
    }
}
