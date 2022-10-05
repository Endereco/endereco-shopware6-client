<?php

namespace Endereco\Shopware6Client;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

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

        $pathToCopyIoPhp = dirname(__FILE__, 5) . '/public/io.php';

        // Delete copied io.php file.
        if (file_exists($pathToCopyIoPhp)) {
            unlink($pathToCopyIoPhp);
        }
    }

    /**
     * @inheritDoc
     */
    public function install(InstallContext $context): void
    {
        parent::install($context);

        $pathToOriginIoPhp = dirname(__FILE__) . '/Resources/public/io.php';
        $pathToCopyIoPhp = dirname(__FILE__, 5) . '/public/io.php';

        // Copy io.php to public directory
        copy($pathToOriginIoPhp, $pathToCopyIoPhp);
    }

    /**
     * @inheritDoc
     */
    public function update(UpdateContext $context): void
    {
        parent::update($context);

        $pathToOriginIoPhp = dirname(__FILE__) . '/Resources/public/io.php';
        $pathToCopyIoPhp = dirname(__FILE__, 5) . '/public/io.php';

        if (!file_exists($pathToCopyIoPhp)) {
            // Copy io.php to public directory
            copy($pathToOriginIoPhp, $pathToCopyIoPhp);
        }
    }
}
