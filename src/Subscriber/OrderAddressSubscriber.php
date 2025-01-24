<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddressIntegrityInsuranceInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
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
     * is an instance of OrderAddressEntity. For each address entity, it ensures the address extension exists,
     * ensures the street is split, and checks if the validation is still up-to-date. It a re-validation is required,
     * it ensures the address status is set and the request payload is up-to-date.
     * After looping through all address entities, it closes all stored sessions.
     */
    public function ensureAddressesIntegrity(EntityLoadedEvent $event): void
    {
        $context = $event->getContext();

        // Loop through all entities loaded in the event
        foreach ($event->getEntities() as $entity) {
            // Skip the entity if it's not a CustomerAddressEntity
            if (!$entity instanceof OrderAddressEntity) {
                continue;
            }

            $this->orderAddressIntegrityInsurance->ensure($entity, $context);
        }
    }
}
