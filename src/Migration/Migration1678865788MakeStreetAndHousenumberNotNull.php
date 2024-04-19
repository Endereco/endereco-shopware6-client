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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function update(Connection $connection): void
    {
        // This section was removed because setting the house number and street fields to "NOT NULL" was causing errors
        // when attempting to update the plugin. Additionally, when a street split was not possible and an empty
        // house number needed to be saved, Doctrine would convert an empty string to "NULL", attempting to save a null
        // value, and consequently causing a MySQL exception.
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
