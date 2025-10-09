<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1753461221AddAddressIdColumnToOrderAddressExtensions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753461221;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $hasOrderAddressForeignKeyConstraint = $connection->executeQuery(<<<SQL
        SELECT COUNT(*)
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = :database
            AND TABLE_NAME = 'endereco_order_address_ext_gh'
            and REFERENCED_TABLE_NAME = 'order_address'
            and CONSTRAINT_NAME = 'fk.end_order_address_id_gh'
        SQL, ['database' => $connection->getDatabase()])->fetchOne() > 0;

        $sql = <<<SQL
        ALTER TABLE `endereco_order_address_ext_gh`
            ADD COLUMN `id` BINARY(16) NOT NULL FIRST,
            ADD COLUMN `version_id` BINARY(16) NOT NULL AFTER `id`,
            ADD COLUMN `address_version_id` BINARY(16) NOT NULL AFTER `address_id`
        SQL;
        $connection->executeStatement($sql);

        // The version IDs can be set to the Shopware Default version ID for the migration.
        $sql = <<<SQL
        UPDATE `endereco_order_address_ext_gh`
            SET `version_id` = UNHEX("0fa91ce3e96a4bc2be4bd9ce752c3425"),
                `address_version_id` = UNHEX("0fa91ce3e96a4bc2be4bd9ce752c3425")
        SQL;
        $connection->executeStatement($sql);

        // UUID() is used to generate random UUIDs for existing order addresses.
        $sql = <<<SQL
        CREATE TEMPORARY TABLE `temp_uuids` 
               AS SELECT `address_id`, UNHEX(REPLACE(UUID(), '-', '')) AS `new_id` FROM `endereco_order_address_ext_gh`
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        UPDATE `endereco_order_address_ext_gh` `t1` JOIN `temp_uuids` `t2` ON `t1`.`address_id` = `t2`.`address_id` 
            SET `t1`.`id` = `t2`.`new_id`
        SQL;
        $connection->executeStatement($sql);

        // Only drop constraint when exists. Constraint may be not added yet,
        // see Migration1731623484CreateOrderAddressExtensionTable.php
        if ($hasOrderAddressForeignKeyConstraint) {
            // The constraint must be dropped before the primary key because it uses the primary key.
            $sql = <<<SQL
            ALTER TABLE `endereco_order_address_ext_gh`
                DROP CONSTRAINT `fk.end_order_address_id_gh`
            SQL;
            $connection->executeStatement($sql);
        }

        $sql = <<<SQL
        ALTER TABLE `endereco_order_address_ext_gh`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `version_id`)
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `endereco_order_address_ext_gh`
            ADD CONSTRAINT `fk.end_order_address_id_gh` 
                FOREIGN KEY (`address_id`, `address_version_id`) 
                    REFERENCES `order_address` (`id`, `version_id`) ON UPDATE CASCADE ON DELETE CASCADE
        SQL;
        $connection->executeStatement($sql);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
