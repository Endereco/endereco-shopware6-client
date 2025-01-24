<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Struct\OrderCustomFields;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;

final class OrdersCustomFieldsUpdater implements OrdersCustomFieldsUpdaterInterface
{
    private OrderCustomFieldsBuilderInterface $orderCustomFieldBuilder;
    private EntityRepository $orderRepository;
    private SyncServiceInterface $syncService;

    public function __construct(
        OrderCustomFieldsBuilderInterface $orderCustomFieldBuilder,
        EntityRepository $orderRepository,
        SyncServiceInterface $syncService
    ) {
        $this->orderCustomFieldBuilder = $orderCustomFieldBuilder;
        $this->orderRepository = $orderRepository;
        $this->syncService = $syncService;
    }

    public function updateOrdersCustomFields(
        OrderAddressCollection $orderAddresses,
        Context $context
    ): void {
        if (count($orderAddresses) === 0) {
            return;
        }

        $criteria = new Criteria();

        $criteria->addFilter(new OrFilter([
            new EqualsAnyFilter('addresses.id', $orderAddresses->getIds()),
            new EqualsAnyFilter('deliveries.shippingOrderAddress.id', $orderAddresses->getIds())
        ]));

        $criteria->addAssociation('addresses');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        // The order address extensions are automatically loaded.

        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderRepository->search($criteria, $context)->getEntities();

        $orderSyncPayload = [];

        /** @var OrderEntity $orderEntity */
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
