<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Struct\OrderCustomFields;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

/**
 * Service responsible for updating custom fields on orders with address validation data.
 *
 * This service handles the synchronization of address validation data to order custom fields.
 * It can update orders based on either order IDs or order address IDs, loading the necessary
 * associations and building the custom fields data using the OrderCustomFieldsBuilder.
 */
final class OrdersCustomFieldsUpdater implements OrdersCustomFieldsUpdaterInterface
{
    /**
     * Repository for accessing order entities.
     *
     * @var EntityRepository
     */
    private EntityRepository $orderRepository;

    /**
     * Service for building custom fields data from order entities.
     *
     * @var OrderCustomFieldsBuilderInterface
     */
    private OrderCustomFieldsBuilderInterface $orderCustomFieldBuilder;

    /**
     * Service for synchronizing data updates to the database.
     *
     * @var SyncServiceInterface
     */
    private SyncServiceInterface $syncService;

    /**
     * Initializes a new instance of the OrdersCustomFieldsUpdater.
     *
     * @param EntityRepository $orderRepository Repository for accessing order entities
     * @param OrderCustomFieldsBuilderInterface $orderCustomFieldBuilder Service for building custom fields data
     * @param SyncServiceInterface $syncService Service for synchronizing data updates
     */
    public function __construct(
        EntityRepository $orderRepository,
        OrderCustomFieldsBuilderInterface $orderCustomFieldBuilder,
        SyncServiceInterface $syncService
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderCustomFieldBuilder = $orderCustomFieldBuilder;
        $this->syncService = $syncService;
    }

    /**
     * Updates custom fields on orders with address validation data.
     *
     * This method loads orders either by their IDs directly or by associated address IDs.
     * It then builds validation data for both billing and shipping addresses and updates
     * the orders' custom fields using the sync service.
     *
     * The method will:
     * 1. Build criteria to load orders with all necessary associations
     * 2. Load the order entities with their addresses and delivery information
     * 3. Build custom fields data for each order's addresses
     * 4. Sync the updates to the database using the sync service
     *
     * @param string[] $orderIds IDs of orders to update
     * @param string[] $orderAddressIds IDs of order addresses whose orders should be updated
     * @param Context $context The Shopware context for the operation
     *
     * @return void
     */
    public function updateOrdersCustomFields(array $orderIds, array $orderAddressIds, Context $context): void
    {
        $criteria = new Criteria();

        if (count($orderIds) !== 0) {
            $criteria->addFilter(new EqualsAnyFilter('id', $orderIds));
        }

        if (count($orderAddressIds) !== 0) {
            $criteria->addFilter(new OrFilter([
                new EqualsAnyFilter('addresses.id', $orderAddressIds),
                new EqualsAnyFilter('deliveries.shippingOrderAddress.id', $orderAddressIds)
            ]));
        }

        $criteria->addAssociation('addresses');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        // The order address extensions are automatically loaded.

        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderRepository->search($criteria, $context);

        $orderSyncPayload = [];
        foreach ($orderCollection as $orderEntity) {
            $orderCustomFields = new OrderCustomFields(
                $this->orderCustomFieldBuilder->buildOrderBillingAddressValidationData($orderEntity),
                $this->orderCustomFieldBuilder->buildOrderShippingAddressValidationData($orderEntity)
            );

            $orderSyncPayload[] = [
                'id' => $orderEntity->getId(),
                'customFields' => $orderCustomFields->data()
            ];
        }

        if (count($orderSyncPayload) === 0) {
            return;
        }

        $syncOperator = new SyncOperation(
            'write_order_custom_fields_from_endereco_order_address_ext_written',
            OrderDefinition::ENTITY_NAME,
            SyncOperation::ACTION_UPSERT,
            $orderSyncPayload
        );

        $this->syncService->sync([$syncOperator], $context, new SyncBehavior(false, false));
    }
}
