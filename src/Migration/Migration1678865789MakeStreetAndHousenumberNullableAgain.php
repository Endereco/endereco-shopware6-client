<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1678865789MakeStreetAndHousenumberNullableAgain extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678865789;
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
            MODIFY `street` VARCHAR(255) NULL DEFAULT '', 
            MODIFY `house_number` VARCHAR(255) NULL DEFAULT '';
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
