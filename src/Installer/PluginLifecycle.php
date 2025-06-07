<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Struct\OrderCustomFields;
use RuntimeException;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PluginLifecycle
 *
 * This class manages the lifecycle of the plugin including installation,
 * updates, and uninstallation. It handles database schema changes and
 * cleanup of legacy files from previous versions.
 *
 * @package Endereco\Shopware6Client\Installer
 */
class PluginLifecycle
{
    /**
     * @var ContainerInterface|null $container The container interface object
     */
    private $container;

    /**
     * PluginLifecycle constructor.
     *
     * @param ContainerInterface|null $container The container interface object
     */
    public function __construct(?ContainerInterface $container)
    {
        if ($container !== null) {
            $this->container = $container;
        }
    }

    /**
     * Uninstall the plugin.
     *
     * @param UninstallContext $context The context of the uninstall process.
     * @throws Exception If there is any error during the uninstall process.
     * @return void
     */
    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            return;
        }

        // Clean up any legacy io.php file from previous versions
        $pathToLegacyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        if (file_exists($pathToLegacyIoPhp)) {
            unlink($pathToLegacyIoPhp);
        }

        // The tables to be dropped during uninstallation
        $dropTables = [
            EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME,
            EnderecoOrderAddressExtensionDefinition::ENTITY_NAME
        ];

        $conn = $this->getConnection();

        // Drop each of the specified tables
        foreach ($dropTables as $dropTable) {
            $conn->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $dropTable));
        }

        // The custom fields to be dropped from the `Order` entity during uninstallation
        $dropOrderCustomFields = OrderCustomFields::FIELDS;

        // Drop each of the specified custom fields in the order table
        foreach ($dropOrderCustomFields as $dropOrderCustomField) {
            $jsonPathExpression = sprintf('$."%s"', $dropOrderCustomField);
            $conn->executeStatement(
                sprintf(
                    'UPDATE `order` SET `custom_fields` = JSON_REMOVE(`custom_fields`, \'%s\')',
                    $jsonPathExpression
                )
            );
        }
    }

    /**
     * Install the plugin.
     *
     * @return void
     */
    public function install(): void
    {
    }

    /**
     * Update the plugin.
     *
     * @return void
     */
    public function update(): void
    {
        // Clean up any legacy io.php file that might exist from previous versions
        $pathToLegacyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        if (file_exists($pathToLegacyIoPhp)) {
            unlink($pathToLegacyIoPhp);
        }
    }

    /**
     * Get the database connection.
     *
     * @throws RuntimeException If the connection service is not initialized or there is no container.
     * @return Connection
     */
    private function getConnection(): Connection
    {
        if ($this->container === null) {
            throw new RuntimeException('There is no container');
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        if (!$connection instanceof Connection) {
            throw new RuntimeException('Connection service is not initialized');
        }

        return $connection;
    }
}
