<?php declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1674133739CreateEnderecoAddressExtensionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1674133739;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `endereco_address_ext` (
            `address_id` BINARY(16) NOT NULL,
            `street` VARCHAR(255) NULL,
            `house_number` VARCHAR(255) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`address_id`),
            CONSTRAINT `fk.end_address_id`
                FOREIGN KEY (`address_id`) REFERENCES `customer_address` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
