<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Model\ExpectedSystemConfigValue;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Filters Shopware entities based on their sales channel's system configuration settings.
 *
 * This service provides functionality to filter entities by validating their associated
 * sales channels against specific configuration requirements. It's designed to support
 * multi-channel/multi-tenant setups where different sales channels may have different
 * configuration settings.
 *
 * The filtering process occurs in two steps:
 * 1. Aggregates all relevant sales channel IDs for the given entities
 * 2. Filters out entities whose sales channels don't meet the configuration requirements
 *
 * Usage example:
 * ```php
 * $filteredIds = $filter->filterEntityIdsBySystemConfig(
 *     $orderRepository,
 *     'order.salesChannelId',
 *     $orderIds,
 *     [new ExpectedSystemConfigValue('feature.active', true)],
 *     $context
 * );
 * ```
 *
 * @final
 * @package Endereco\Shopware6Client\Service
 */
final class BySystemConfigFilter implements BySystemConfigFilterInterface
{
    /**
     * @var SystemConfigService The service used to access Shopware's system configuration
     */
    private SystemConfigService $systemConfigService;


    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Filters entity IDs based on their sales channel's system configuration.
     *
     * This method processes a list of entity IDs and returns only those whose associated
     * sales channels match all the specified configuration requirements.
     *
     * @param EntityRepository $entityRepository The repository for the entities being filtered
     * @param string $salesChannelIdField The field path to the sales channel ID (e.g., 'order.salesChannelId')
     * @param array<string> $entityIds List of entity IDs to filter
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
    ): array {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $entityIds));
        $criteria->addAggregation(new TermsAggregation('sales-channel-ids', $salesChannelIdField));
        $criteria->addFields(['id']);
        $salesChannelIdsAggregation = $entityRepository
            ->search($criteria, $context)
            ->getAggregations()
            ->get('sales-channel-ids');

        if (!$salesChannelIdsAggregation instanceof TermsResult) {
            return [];
        }

        $allowedSalesChannels = [];
        foreach ($salesChannelIdsAggregation->getKeys() as $salesChannelId) {
            $allowed = true;
            foreach ($expectedSystemConfigValues as $expectedSystemConfigValue) {
                $systemConfigValue = $this->systemConfigService->get(
                    $expectedSystemConfigValue->getFullyQualifiedConfigKey(),
                    $salesChannelId
                );
                if ($expectedSystemConfigValue->getExpectedConfigValue() !== $systemConfigValue) {
                    $allowed = false;
                    break;
                }
            }

            if ($allowed === true) {
                $allowedSalesChannels[] = $salesChannelId;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $entityIds));
        $criteria->addFilter(new EqualsAnyFilter($salesChannelIdField, $allowedSalesChannels));

        $filteredEntityIds = $entityRepository->searchIds($criteria, $context)->getIds();

        return $this->flattenIds($filteredEntityIds);
    }

    /**
     * Flattens an array of entity IDs into a simple 1-dimensional array.
     *
     * @param list<string>|list<array<string, string>> $entityIds The array of entity IDs to flatten
     * @return string[] Flattened array of entity IDs
     * @throws \RuntimeException If the input array contains nested arrays
     *
     * @internal This method is used to normalize the entity IDs returned by the repository
     */
    private function flattenIds(array $entityIds): array
    {
        $flattenedIds = [];
        foreach ($entityIds as $entityId) {
            if (is_array($entityId)) {
                throw new \RuntimeException(
                    'Only 1D arrays are supported for now. If this exception is thrown, contact the author.'
                );
            }

            $flattenedIds[] = $entityId;
        }

        return $flattenedIds;
    }
}
