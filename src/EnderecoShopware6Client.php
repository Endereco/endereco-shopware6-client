<?php

namespace Endereco\Shopware6Client;

use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Endereco\Shopware6Client\Installer\PluginLifecycle;

class EnderecoShopware6Client extends Plugin
{
    /**
     * @throws Exception
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        (new PluginLifecycle($this->container))->uninstall($uninstallContext);
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        (new PluginLifecycle($this->container))->install();
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        (new PluginLifecycle($this->container))->update();
    }
}
