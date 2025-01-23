<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1731759008AddAmsRequestPayloadColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731759008;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        ALTER TABLE `endereco_customer_address_ext_gh`
            ADD COLUMN `ams_request_payload` LONGTEXT NULL AFTER `address_id`
        SQL;
        $connection->executeStatement($sql);

        $sql = <<<SQL
        ALTER TABLE `endereco_order_address_ext_gh`
            ADD COLUMN `ams_request_payload` LONGTEXT NULL AFTER `address_id`
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
