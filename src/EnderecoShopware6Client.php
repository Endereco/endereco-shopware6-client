<?php

namespace Endereco\Shopware6Client;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Endereco\Shopware6Client\Installer\PluginLifecycle;

class EnderecoShopware6Client extends Plugin
{

    /**
     * @inheritDoc
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        (new PluginLifecycle($this->container))->uninstall($context);
    }

    /**
     * @inheritDoc
     */
    public function install(InstallContext $context): void
    {
        parent::install($context);

        (new PluginLifecycle($this->container))->install($context);
    }

    /**
     * @inheritDoc
     */
    public function update(UpdateContext $context): void
    {
        parent::update($context);

        (new PluginLifecycle($this->container))->update($context);
    }
}
