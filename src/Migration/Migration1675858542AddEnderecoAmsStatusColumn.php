<?php declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1675858542AddEnderecoAmsStatusColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675858542;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `endereco_address_ext`
            ADD COLUMN `ams_status` LONGTEXT NOT NULL DEFAULT 'not-checked' AFTER `address_id`,
            ADD COLUMN `ams_timestamp` INT NULL AFTER `ams_status`,
            ADD COLUMN `ams_predictions` LONGTEXT NOT NULL DEFAULT '[]' AFTER `ams_timestamp`;
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
