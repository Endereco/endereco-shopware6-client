<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Model\ExpectedSystemConfigValue;
use Endereco\Shopware6Client\Service\BySystemConfigFilterInterface;
use Endereco\Shopware6Client\Service\OrdersCustomFieldsUpdaterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

/**
 * Updates order custom fields when Endereco address extensions change
 */
class OrderSubscriber implements EventSubscriberInterface
{
    /** @var OrdersCustomFieldsUpdaterInterface */
    private OrdersCustomFieldsUpdaterInterface $ordersCustomFieldsUpdater;

    /** @var EntityRepository<OrderAddressCollection> */
    private EntityRepository $orderAddressRepository;

    /** @var BySystemConfigFilterInterface */
    private BySystemConfigFilterInterface $bySystemConfigFilter;

    /**
     * @param EntityRepository<OrderAddressCollection> $orderAddressRepository
     */
    public function __construct(
        EntityRepository $orderAddressRepository,
        BySystemConfigFilterInterface $bySystemConfigFilter,
        OrdersCustomFieldsUpdaterInterface $ordersCustomFieldsUpdater
    ) {
        $this->orderAddressRepository = $orderAddressRepository;
        $this->bySystemConfigFilter = $bySystemConfigFilter;
        $this->ordersCustomFieldsUpdater = $ordersCustomFieldsUpdater;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            EnderecoOrderAddressExtensionDefinition::ENTITY_NAME . '.written' => 'updateOrderCustomFields'
        ];
    }

    /**
     * Updates custom fields when address extensions are written. Customer fields always mirror the content of
     * order address extension, hence .written event that triggers the rewrite.
     *
     * @param EntityWrittenEvent $event
     */
    public function updateOrderCustomFields(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== EnderecoOrderAddressExtensionDefinition::ENTITY_NAME) {
            return;
        }

        // The primary key aka ID of the `EnderecoOrderAddressExtension` is the ID of the `OrderAddress`.
        $orderAddressIds = $event->getIds();

        // If the list of order entity IDs is empty, nothing has to be updated.
        // Furthermore, the criteria object requires a non-empty list of IDs.
        if (count($orderAddressIds) === 0) {
            return;
        }

        $orderAddressIds = $this->bySystemConfigFilter->filterEntityIdsBySystemConfig(
            $this->orderAddressRepository,
            'order.salesChannelId',
            $orderAddressIds,
            [
                new ExpectedSystemConfigValue('enderecoActiveInThisChannel', true),
                new ExpectedSystemConfigValue('enderecoWriteOrderCustomFields', true)
            ],
            $event->getContext()
        );

        // If the list of order entity IDs is empty after it was filtered nothing has to be updated as well.
        if (count($orderAddressIds) === 0) {
            return;
        }

        $criteria = new Criteria($orderAddressIds);

        /** @var OrderAddressCollection $orderAddresses */
        $orderAddresses = $this->orderAddressRepository->search($criteria, $event->getContext())->getEntities();

        $this->ordersCustomFieldsUpdater->updateOrdersCustomFields($orderAddresses, $event->getContext());
    }
}
