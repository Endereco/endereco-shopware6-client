<?php

namespace Endereco\Shopware6Client;

use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Endereco\Shopware6Client\Installer\PluginLifecycle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class EnderecoShopware6Client
 *
 * Main plugin class for the Endereco Shopware 6 client.
 *
 * @package Endereco\Shopware6Client
 */
class EnderecoShopware6Client extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new PhpFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = \rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');

        if ($this->isCrefoPayActive($container)) {
            $configLoader->load($confDir . '/compat_crefopay.php');
        }
    }

    private function isCrefoPayActive(ContainerBuilder $container): bool
    {
        /** @var array<class-string, array{name: string, path: string, class: class-string}> $activePlugins */
        $activePlugins = $container->getParameter('kernel.active_plugins');
        $search = 'CrefoPay';
        $isActive = false;
        foreach (array_keys($activePlugins) as $key) {
            if (is_string($key) && str_contains($key, $search)) {
                $isActive = true;
                break;
            }
        }

        if (!$isActive) {
            return false;
        }

        // Guard against CrefoPay renaming the class: if the decoration target no longer
        // exists, skip loading rather than crashing the container.
        if (!class_exists(\CrefoPay\Payment\Storefront\EventListener\Cart\CartEventListener::class)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall the plugin.
     *
     * This function is used to handle the uninstallation process of the plugin.
     * It calls the parent's uninstall function and the PluginLifecycle's uninstall function.
     *
     * @param UninstallContext $uninstallContext The context of the uninstall process.
     * @throws Exception If there is any error during the uninstall process.
     * @return void
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        (new PluginLifecycle($this->container))->uninstall($uninstallContext);
    }

    /**
     * Install the plugin.
     *
     * This function is used to handle the installation process of the plugin.
     * It calls the parent's install function and the PluginLifecycle's install function.
     *
     * @param InstallContext $installContext The context of the install process.
     *
     * @return void
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        (new PluginLifecycle($this->container))->install();
    }

    /**
     * Update the plugin.
     *
     * This function is used to handle the update process of the plugin.
     * It calls the parent's update function and the PluginLifecycle's update function.
     *
     * @param UpdateContext $updateContext The context of the update process.
     *
     * @return void
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        (new PluginLifecycle($this->container))->update();
    }
}
