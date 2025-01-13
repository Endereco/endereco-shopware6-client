<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731623483RenameAddressExtensionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731623483;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `endereco_address_ext`
            RENAME TO `endereco_customer_address_ext_gh`
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `endereco_customer_address_ext_gh`
            DROP FOREIGN KEY`fk.end_address_id`
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `endereco_customer_address_ext_gh`
            ADD CONSTRAINT `fk.end_customer_address_id_gh` 
                FOREIGN KEY (`address_id`) REFERENCES `customer_address` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
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
