<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\DependencyInjection;

use Endereco\Shopware6Client\Subscriber\ContextResolverListener as EnderecoContextResolverListener;
use Shopware\Core\Framework\Routing\ContextResolverListener as ShopwareContextResolverListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Compiler pass that replaces Shopware's native ContextResolverListener with our custom implementation.
 *
 * This compiler pass is executed during container compilation and swaps out Shopware's
 * ContextResolverListener with our custom version that can selectively bypass context
 * resolution for performance-critical controllers.
 *
 * ARCHITECTURE NOTE: This approach allows us to maintain the same service ID and event
 * listener priority while customizing the behavior for specific controllers without
 * affecting the rest of the Shopware application.
 */
final class ReplaceContextResolverListenerPass implements CompilerPassInterface
{
    /**
     * Replaces the native Shopware ContextResolverListener with our custom implementation.
     *
     * This method preserves the original service arguments and configuration while
     * substituting our performance-optimized listener that can bypass context resolution
     * for specific controllers.
     *
     * @param ContainerBuilder $container The service container being compiled
     */
    public function process(ContainerBuilder $container): void
    {
        $nativeContextResolverDefinition = $container->getDefinition(ShopwareContextResolverListener::class);
        $container->removeDefinition(ShopwareContextResolverListener::class);

        $contextResolverDefinition = new Definition(
            EnderecoContextResolverListener::class,
            $nativeContextResolverDefinition->getArguments()
        );
        $contextResolverDefinition->addTag('kernel.event_subscriber');
        $container->setDefinition(EnderecoContextResolverListener::class, $contextResolverDefinition);
    }
}
