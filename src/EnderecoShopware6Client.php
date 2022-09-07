<?php

namespace Endereco\Shopware6Client;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
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
}
