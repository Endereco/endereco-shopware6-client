<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Installer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginLifecycle
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            return;
        }

        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Delete copied io.php file.
        if (file_exists($pathToCopyIoPhp)) {
            unlink($pathToCopyIoPhp);
        }

        $conn = $this->getConnection();
        $conn->executeStatement('DROP TABLE IF EXISTS endereco_address_ext');
    }

    /**
     * @inheritDoc
     */
    public function install(InstallContext $context): void
    {
        $pathToOriginIoPhp = dirname(__FILE__, 2) . '/Resources/public/io.php';
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Copy io.php to public directory
        copy($pathToOriginIoPhp, $pathToCopyIoPhp);
    }

    /**
     * @inheritDoc
     */
    public function update(UpdateContext $context): void
    {
        $pathToOriginIoPhp = dirname(__FILE__, 2) . '/Resources/public/io.php';
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        if (!file_exists($pathToCopyIoPhp)) {
            // Copy io.php to public directory
            copy($pathToOriginIoPhp, $pathToCopyIoPhp);
        }
    }

    private function getConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        if (!$connection instanceof Connection) {
            throw new RuntimeException('Connection service is not initialized');
        }

        return $connection;
    }
}
