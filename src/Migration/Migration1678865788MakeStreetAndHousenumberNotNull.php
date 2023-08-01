<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1678865788MakeStreetAndHousenumberNotNull extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678865788;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            ALTER TABLE `endereco_address_ext` 
            MODIFY `street` VARCHAR(255) NOT NULL DEFAULT '', 
            MODIFY `house_number` VARCHAR(255) NOT NULL DEFAULT '';
        SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
