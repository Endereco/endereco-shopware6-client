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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
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
