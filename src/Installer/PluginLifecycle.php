<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionDefinition;
use RuntimeException;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PluginLifecycle
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws Exception
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

        $dropTables = [
            EnderecoAddressExtensionDefinition::ENTITY_NAME
        ];

        $conn = $this->getConnection();

        foreach ($dropTables as $dropTable) {
            $conn->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $dropTable));
        }
    }

    public function install(): void
    {
        $pathToOriginIoPhp = dirname(__FILE__, 2) . '/Resources/public/io.php';
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Copy io.php to public directory
        copy($pathToOriginIoPhp, $pathToCopyIoPhp);
    }

    public function update(): void
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
