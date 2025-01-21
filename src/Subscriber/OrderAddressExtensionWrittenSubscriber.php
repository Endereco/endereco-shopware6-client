<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Model\ExpectedSystemConfigValue;
use Endereco\Shopware6Client\Service\BySystemConfigFilterInterface;
use Endereco\Shopware6Client\Service\OrdersCustomFieldsUpdaterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber that handles updates to Endereco order address extensions and synchronizes
 * the validation data to order custom fields.
 *
 * This subscriber is triggered whenever an Endereco order address extension entity is written
 * (created or updated). It updates the corresponding order's custom fields with address
 * validation data, but only for sales channels where this functionality is explicitly enabled.
 */
class OrderAddressExtensionWrittenSubscriber implements EventSubscriberInterface
{
    /**
     * Repository for accessing order address entities.
     *
     * @var EntityRepository
     */
    private EntityRepository $orderAddressRepository;

    /**
     * Service for filtering entities based on system configuration values.
     *
     * @var BySystemConfigFilterInterface
     */
    private BySystemConfigFilterInterface $bySystemConfigFilter;

    /**
     * Service for updating order custom fields with address validation data.
     *
     * @var OrdersCustomFieldsUpdaterInterface
     */
    private OrdersCustomFieldsUpdaterInterface $ordersCustomFieldsUpdater;

    /**
     * Initializes a new instance of the OrderAddressExtensionWrittenSubscriber.
     *
     * @param EntityRepository $orderAddressRepository Repository for accessing order address entities
     * @param BySystemConfigFilterInterface $bySystemConfigFilter Service for filtering entities based on system config
     * @param OrdersCustomFieldsUpdaterInterface $ordersCustomFieldsUpdater Service for updating order custom fields
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

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array<string, string> Array of event names mapped to method names
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EnderecoOrderAddressExtensionDefinition::ENTITY_NAME . '.written' => 'updateOrderCustomFields'
        ];
    }

    /**
     * Updates order custom fields when an order address extension is written.
     *
     * This method is triggered when an Endereco order address extension is created or updated.
     * It filters the affected order addresses based on sales channel configuration and updates
     * the corresponding orders' custom fields with address validation data.
     *
     * The method only processes orders from sales channels where:
     * - Endereco is active (enderecoActiveInThisChannel = true)
     * - Custom field writing is enabled (enderecoWriteOrderCustomFields = true)
     *
     * @param EntityWrittenEvent $event The event containing information about the written entities
     *
     * @return void
     */
    public function updateOrderCustomFields(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== EnderecoOrderAddressExtensionDefinition::ENTITY_NAME) {
            return;
        }

        // The primary key aka ID of the `EnderecoOrderAddressExtension` is the ID of the `OrderAddress`.
        $orderAddressIds = $event->getIds();

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

        $this->ordersCustomFieldsUpdater->updateOrdersCustomFields([], $orderAddressIds, $event->getContext());
    }
}
