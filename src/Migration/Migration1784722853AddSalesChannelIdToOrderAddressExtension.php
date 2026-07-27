<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds a sales_channel_id column to endereco_order_address_ext_gh so the sales channel an order
 * address belongs to can be cached once at creation time, instead of having to be re-resolved via
 * an extra query on every later load (order_address's own "order" association is not autoloaded).
 */
class Migration1784722853AddSalesChannelIdToOrderAddressExtension extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784722853;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $columnExists = (int) $connection->fetchOne(
            <<<SQL
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :database
                AND TABLE_NAME = 'endereco_order_address_ext_gh'
                AND COLUMN_NAME = 'sales_channel_id'
            SQL,
            ['database' => $connection->getDatabase()]
        ) > 0;

        if (!$columnExists) {
            $connection->executeStatement(
                <<<SQL
                ALTER TABLE `endereco_order_address_ext_gh`
                    ADD COLUMN `sales_channel_id` BINARY(16) NULL AFTER `address_version_id`
                SQL
            );
        }

        $constraintExists = (int) $connection->fetchOne(
            <<<SQL
            SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = :database
                AND TABLE_NAME = 'endereco_order_address_ext_gh'
                AND CONSTRAINT_NAME = 'fk.endereco_order_address_ext_gh.sales_channel_id'
            SQL,
            ['database' => $connection->getDatabase()]
        ) > 0;

        if (!$constraintExists) {
            // ON DELETE SET NULL, not CASCADE: this column is only a cache to avoid re-resolving the
            // sales channel on every load - unlike address_id, it is not essential to this row's
            // identity, so a deleted sales channel should just clear the reference, not the whole row.
            $connection->executeStatement(
                <<<SQL
                ALTER TABLE `endereco_order_address_ext_gh`
                    ADD CONSTRAINT `fk.endereco_order_address_ext_gh.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE SET NULL
                SQL
            );
        }
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
