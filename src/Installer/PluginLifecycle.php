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
 * This class is used to manage the life-cycle of the plugin.
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

        // The path where io.php has been copied
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Delete the copied io.php file.
        if (file_exists($pathToCopyIoPhp)) {
            unlink($pathToCopyIoPhp);
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
        // The original path of io.php
        $pathToOriginIoPhp = dirname(__FILE__, 2) . '/Resources/public/io.php';

        // The path where io.php will be copied
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Copy io.php to the public directory
        copy($pathToOriginIoPhp, $pathToCopyIoPhp);
    }

    /**
     * Update the plugin.
     *
     * @return void
     */
    public function update(): void
    {
        // The original path of io.php
        $pathToOriginIoPhp = dirname(__FILE__, 2) . '/Resources/public/io.php';

        // The path where io.php will be copied
        $pathToCopyIoPhp = dirname(__FILE__, 6) . '/public/io.php';

        // Check if io.php already exists in the public directory
        if (!file_exists($pathToCopyIoPhp)) {
            // If not, copy it
            copy($pathToOriginIoPhp, $pathToCopyIoPhp);
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
