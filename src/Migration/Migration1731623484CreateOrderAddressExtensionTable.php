<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731623484CreateOrderAddressExtensionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731623484;
    }

    /**
     * @throws Exception
     */
    private function isGtMysql84(Connection $connection): bool
    {
        $isMariadb = (bool) $connection->executeQuery(<<<SQL
        SELECT 
            CASE
                WHEN VERSION() LIKE '%MariaDB%' 
                    OR @@version_comment LIKE '%MariaDB%' THEN 1
                ELSE 0
            END AS is_mariadb;
        SQL)->fetchOne();

        if ($isMariadb) {
            return false;
        }

        $version = $connection->executeQuery(<<<SQL
            SELECT VERSION()
        SQL)->fetchOne();

        return version_compare($version, '8.4', '>=');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        if ($this->isGtMysql84($connection)) {
            // Don't add order_address foreign key,
            // added by Migration1753461221AddAddressIdColumnToOrderAddressExtensions.php
            // because the migration won't run because of breaking changes in MySQL 8.4 in the default
            // configuration
            $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `endereco_order_address_ext_gh` (
                `address_id` BINARY(16) NOT NULL,
                `ams_status` LONGTEXT NULL,
                `ams_timestamp` INT NULL,
                `ams_predictions` LONGTEXT NULL,
                `is_amazon_pay_address` BOOLEAN NULL DEFAULT false,
                `is_paypal_address` BOOLEAN NULL DEFAULT false,
                `street` VARCHAR(255) NULL DEFAULT '',
                `house_number` VARCHAR(255) NULL DEFAULT '',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`address_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL;
        } else {
            // in all cases where the migration already was executed nothing changes
            // datamodels are adjusted in subsequent Migration1753461221AddAddressIDColumnToAdressExtensions.php
            $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS `endereco_order_address_ext_gh` (
                `address_id` BINARY(16) NOT NULL,
                `ams_status` LONGTEXT NULL,
                `ams_timestamp` INT NULL,
                `ams_predictions` LONGTEXT NULL,
                `is_amazon_pay_address` BOOLEAN NULL DEFAULT false,
                `is_paypal_address` BOOLEAN NULL DEFAULT false,
                `street` VARCHAR(255) NULL DEFAULT '',
                `house_number` VARCHAR(255) NULL DEFAULT '',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`address_id`),
                CONSTRAINT `fk.end_order_address_id_gh`
                    FOREIGN KEY (`address_id`) REFERENCES `order_address` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL;
        }
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
