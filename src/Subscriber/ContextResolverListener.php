<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Controller\Storefront\AddressCheckProxyController;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Custom context resolver that bypasses Shopware's context resolution for performance-critical controllers.
 *
 * This listener replaces Shopware's native ContextResolverListener to selectively skip
 * the heavy context resolution process for ultra-lightweight controllers like the
 * AddressCheckProxyController, which need minimal latency for real-time API proxying.
 *
 * PERFORMANCE IMPACT: Shopware's standard context resolution involves loading sales channel,
 * currency, customer context, and other heavy operations that can add latency.
 */
class ContextResolverListener implements EventSubscriberInterface
{
    private RequestContextResolverInterface $requestContextResolver;

    public function __construct(RequestContextResolverInterface $requestContextResolver)
    {
        $this->requestContextResolver = $requestContextResolver;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['resolveContext', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE],
            ],
        ];
    }

    /**
     * Conditionally resolves Shopware context based on the controller type.
     *
     * For performance-critical controllers (like AddressCheckProxyController),
     * this method skips context resolution entirely to minimize response latency.
     * For all other controllers, standard Shopware context resolution proceeds normally.
     *
     * @param ControllerEvent $event The controller event containing request and controller info
     */
    public function resolveContext(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if ($controller instanceof AddressCheckProxyController) {
            return;
        }

        // Proceed with standard Shopware context resolution for all other controllers
        $this->requestContextResolver->resolve($event->getRequest());
    }
}
