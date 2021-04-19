<?php

namespace Endereco\Shopware6Client;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class EnderecoShopware6Client extends Plugin
{
    /**
     * @inheritDoc
     */
    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            parent::uninstall($context);

            return;
        }

        // Remove all traces of your plugin
    }
}
