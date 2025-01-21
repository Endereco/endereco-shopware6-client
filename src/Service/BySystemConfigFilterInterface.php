<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Model\ExpectedSystemConfigValue;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Interface for filtering Shopware entities based on system configuration values.
 *
 * This interface defines the contract for services that filter entities based on their
 * associated sales channel's system configuration settings. It supports multi-channel
 * environments where different sales channels may have different configurations.
 *
 * Implementations of this interface are used to:
 * - Filter entities based on sales channel settings
 * - Validate configuration requirements
 * - Support multi-tenant architecture requirements
 *
 * @package Endereco\Shopware6Client\Service
 */
interface BySystemConfigFilterInterface
{
    /**
     * Filters a list of entity IDs based on their sales channel's system configuration.
     *
     * This method should process the provided entity IDs and return only those whose
     * associated sales channels match all the specified configuration requirements.
     *
     * @param EntityRepository $entityRepository The repository for the entities to be filtered
     * @param string $salesChannelIdField The field path to access the sales channel ID (e.g., 'order.salesChannelId')
     * @param string[] $entityIds List of entity IDs to be filtered
     * @param ExpectedSystemConfigValue[] $expectedSystemConfigValues List of configuration values that must be matched
     * @param Context $context The Shopware context for the operation
     *
     * @return string[] Filtered list of entity IDs that match all configuration requirements
     */
    public function filterEntityIdsBySystemConfig(
        EntityRepository $entityRepository,
        string $salesChannelIdField,
        array $entityIds,
        array $expectedSystemConfigValues,
        Context $context
    ): array;
}
