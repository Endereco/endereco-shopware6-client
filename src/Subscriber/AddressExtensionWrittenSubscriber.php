<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Service\AddressesExtensionAmsRequestPayloadUpdaterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddressExtensionWrittenSubscriber implements EventSubscriberInterface
{
    private AddressesExtensionAmsRequestPayloadUpdaterInterface $addressesExtensionAmsRequestPayloadUpdater;

    public function __construct(
        AddressesExtensionAmsRequestPayloadUpdaterInterface $addressesExtensionAmsRequestPayloadUpdater
    ) {
        $this->addressesExtensionAmsRequestPayloadUpdater = $addressesExtensionAmsRequestPayloadUpdater;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME . '.written'
                => 'updateCustomerAddressAmsRequestPayload',
            EnderecoOrderAddressExtensionDefinition::ENTITY_NAME . '.written'
                => 'updateOrderAddressAmsRequestPayload',
        ];
    }

    /**
     * Updates the AMS request payload for the customer addresses extensions that were updated.
     * Entity writes that don't update the `amsTimestamp` field are ignored
     * as this field update indicates that a validation was performed.
     * Address extensions with the AMS status "not-checked" are ignored.
     *
     * @param EntityWrittenEvent $event
     */
    public function updateCustomerAddressAmsRequestPayload(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== EnderecoCustomerAddressExtensionDefinition::ENTITY_NAME) {
            return;
        }

        $customerAddressIdsToBeUpdated = $this->getAddressIdsForUpdateFromWriteResults($event->getWriteResults());
        if (count($customerAddressIdsToBeUpdated) === 0) {
            return;
        }

        $this->addressesExtensionAmsRequestPayloadUpdater->updateCustomerAddressesAmsRequestPayload(
            $customerAddressIdsToBeUpdated,
            $event->getContext()
        );
    }

    /**
     * Updates the AMS request payload for the order addresses extensions that were updated.
     * Entity writes that don't update the `amsTimestamp` field are ignored
     * as this field update indicates that a validation was performed.
     * Address extensions with the AMS status "not-checked" are ignored.
     *
     * @param EntityWrittenEvent $event
     */
    public function updateOrderAddressAmsRequestPayload(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== EnderecoOrderAddressExtensionDefinition::ENTITY_NAME) {
            return;
        }

        $orderAddressIdsToBeUpdated = $this->getAddressIdsForUpdateFromWriteResults($event->getWriteResults());
        if (count($orderAddressIdsToBeUpdated) === 0) {
            return;
        }

        $this->addressesExtensionAmsRequestPayloadUpdater->updateOrderAddressesAmsRequestPayload(
            $orderAddressIdsToBeUpdated,
            $event->getContext()
        );
    }

    /**
     * @param EntityWriteResult[] $writeResults
     * @return string[]
     */
    private function getAddressIdsForUpdateFromWriteResults(array $writeResults): array
    {
        $addressIdsForUpdate = [];
        foreach ($writeResults as $writeResult) {
            if (!array_key_exists('amsTimestamp', $writeResult->getPayload())) {
                continue;
            }

            // The primary key aka ID of the `EnderecoCustomerAddressExtension` / `EnderecoOrderAddressExtension`
            // is the ID of the `CustomerAddress` / `OrderAddress`.
            /** @var string $addressId */
            $addressId = $writeResult->getPrimaryKey();
            $addressIdsForUpdate[] = $addressId;
        }

        return $addressIdsForUpdate;
    }
}
