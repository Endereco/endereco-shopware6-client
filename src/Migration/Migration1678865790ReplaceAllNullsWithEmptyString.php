<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1678865790ReplaceAllNullsWithEmptyString extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1678865790;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        // Update rows where 'street' is NULL
        $connection->executeStatement("
            UPDATE `endereco_address_ext` 
            SET `street` = '' 
            WHERE `street` IS NULL
        ");

        // Update rows where 'house_number' is NULL
        $connection->executeStatement("
            UPDATE `endereco_address_ext` 
            SET `house_number` = '' 
            WHERE `house_number` IS NULL
        ");
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
