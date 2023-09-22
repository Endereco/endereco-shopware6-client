<?php

namespace Endereco\Shopware6Client;

use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Endereco\Shopware6Client\Installer\PluginLifecycle;

/**
 * Class EnderecoShopware6Client
 *
 * Main plugin class for the Endereco Shopware 6 client.
 *
 * @package Endereco\Shopware6Client
 */
class EnderecoShopware6Client extends Plugin
{
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
