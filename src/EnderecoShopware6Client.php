<?php

namespace Endereco\Shopware6Client;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Log\LoggerFactory;
class EnderecoShopware6Client extends Plugin
{
    /**
     * @inheritDoc
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        // There is nothing to clean up. Yet.
    }
}
