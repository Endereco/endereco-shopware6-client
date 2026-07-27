<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsuranceInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderAddressSubscriber implements EventSubscriberInterface
{
    private OrderAddressIntegrityInsuranceInterface $orderAddressIntegrityInsurance;

    public function __construct(
        OrderAddressIntegrityInsuranceInterface $orderAddressIntegrityInsurance
    ) {
        $this->orderAddressIntegrityInsurance = $orderAddressIntegrityInsurance;
    }

    public static function getSubscribedEvents(): array
    {
        // This event is triggered when the address is loaded from the database.
        // You can add logic to ensure certain data in the address are properly set.
        return [
            OrderEvents::ORDER_ADDRESS_LOADED_EVENT => ['ensureAddressesIntegrity'],
        ];
    }

    /**
     * Ensures the integrity of all addresses loaded in the event.
     *
     * The function loops through all entities loaded in the event and performs certain operations if the entity
     * is an instance of OrderAddressEntity. For each address entity, it ensures the address extension exists
     * and checks if the validation is still up-to-date. It a re-validation is required,
     * it ensures the address status is set and the request payload is up-to-date.
     * After looping through all address entities, it closes all stored sessions.
     *
     * @param EntityLoadedEvent<OrderAddressEntity> $event
     */
    public function ensureAddressesIntegrity(EntityLoadedEvent $event): void
    {
        $context = $event->getContext();

        // The DAL can hand us the same physical order_address row multiple times in one event
        // (e.g. loaded via order.addresses, order.billingAddress and delivery.shippingOrderAddress
        // simultaneously), so skip rows we already processed within this event.
        $processedAddresses = [];

        // Loop through all entities loaded in the event
        foreach ($event->getEntities() as $entity) {
            // Skip the entity if it's not a OrderAddressEntity
            if (!$entity instanceof OrderAddressEntity) {
                continue;
            }

            $addressKey = $entity->getId() . '-' . $entity->getVersionId();
            if (isset($processedAddresses[$addressKey])) {
                continue;
            }
            $processedAddresses[$addressKey] = true;

            //Skip the entity if it does not already have an EnderecoOrderAddressExtension
            $addressExtension = $entity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
            if (!$addressExtension instanceof EnderecoOrderAddressExtensionEntity) {
                continue;
            }

            $this->orderAddressIntegrityInsurance->ensure($entity, $context);
        }
    }
}
