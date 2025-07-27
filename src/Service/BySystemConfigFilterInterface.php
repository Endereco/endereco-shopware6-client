<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Model\ExpectedSystemConfigValue;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
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
     * Filters entity IDs based on their sales channel's system configuration.
     *
     * This method processes a list of entity IDs and returns only those whose associated
     * sales channels match all the specified configuration requirements.
     *
     * @param EntityRepository<CustomerAddressCollection>|EntityRepository<OrderAddressCollection> $entityRepository
     *        The repository for the entities being filtered
     * @param string $salesChannelIdField The field path to the sales channel ID (e.g., 'order.salesChannelId')
     * @param non-empty-array<string> $entityIds List of entity IDs to filter
     * @param array<ExpectedSystemConfigValue> $expectedSystemConfigValues List of required configuration values
     * @param Context $context The Shopware context
     *
     * @return array<string> Filtered list of entity IDs that meet all configuration requirements
     */
    public function filterEntityIdsBySystemConfig(
        EntityRepository $entityRepository,
        string $salesChannelIdField,
        array $entityIds,
        array $expectedSystemConfigValues,
        Context $context
    ): array;
}
