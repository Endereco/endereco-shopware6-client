<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressSubscriber extends AbstractEnderecoSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.create' => 'onFormValidation',
            'framework.validation.address.update' => 'onFormValidation',
            'customer_address.loaded' => 'onAddressLoaded',
            CustomerEvents::MAPPING_ADDRESS_CREATE => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING => 'onMappingCreate',
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'extractAndAccountSessions'
        ];
    }

    public function onAddressLoaded(EntityLoadedEvent $event)
    {
        $salesChannelId = $this->fetchSalesChannelId($event->getContext());
        if (!$this->isStreetSplittingEnabled($salesChannelId)) {
            return;
        }

        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof CustomerAddressEntity) {
                continue;
            }

            $this->ensureAddressIsSplit($event->getContext(), $entity);
        }
    }

    public function onFormValidation(BuildValidationEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelId = $this->fetchSalesChannelId($context);
        if (!$this->isStreetSplittingEnabled($salesChannelId)) {
            return;
        }
        $data = $event->getData();
        $address = $data->get('address');
        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');

        $this->overrideStreetWithSplittedData($data, $context);
        $this->overrideStreetWithSplittedData($address, $context);
        $this->overrideStreetWithSplittedData($billingAddress, $context);
        $this->overrideStreetWithSplittedData($shippingAddress, $context);

        $definition = $event->getDefinition();
        $definition->add('enderecoStreet', new NotBlank());
        $definition->add('enderecoHousenumber', new NotBlank());
    }

    public function onMappingCreate(DataMappingEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelId = $this->fetchSalesChannelId($context);
        if (!$this->isStreetSplittingEnabled($salesChannelId)) {
            return;
        }

        $data = $event->getInput();
        $output = $event->getOutput();

        $enderecoStreet = $data->get('enderecoStreet');
        $enderecoHousenumber = $data->get('enderecoHousenumber');
        if (!$enderecoStreet || !$enderecoHousenumber) {
            return;
        }
        $country = $this->fetchCountry($data->get('countryId', ''), $context);
        $output['street'] = $this->enderecoService->buildFullStreet(
            $enderecoStreet,
            $enderecoHousenumber,
            $country ? $country->getIso() : ''
        );
        $output['extensions']['enderecoAddress'] = [
            'street' => $enderecoStreet,
            'houseNumber' => $enderecoHousenumber
        ];
        $event->setOutput($output);
    }

    public function extractAndAccountSessions(EntityWrittenEvent $event): void
    {
        $accountableSessionIds = [];

        if (isset($_SERVER)
            && is_array($_SERVER)
            && array_key_exists('REQUEST_METHOD', $_SERVER)
            && 'POST' === $_SERVER['REQUEST_METHOD']
        ) {
            foreach ($_POST as $sVarName => $sVarValue) {
                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $accountableSessionIds[$_POST[$sSessionIdName]] = true;
                }
            }

            $accountableSessionIds = array_map('strval', array_keys($accountableSessionIds));

            if (!empty($accountableSessionIds)) {
                $this->enderecoService->closeSessions($accountableSessionIds, $event->getContext());
            }
        }
    }

    private function overrideStreetWithSplittedData(?DataBag $address, Context $context): void
    {
        if (!$address instanceof RequestDataBag) {
            return;
        }
        $enderecoStreet = $address->get('enderecoStreet');
        $enderecoHousenumber = $address->get('enderecoHousenumber');
        $countryId = $address->get('countryId');
        if (!empty($enderecoStreet) &&
            !empty($enderecoHousenumber) &&
            !empty($countryId)
        ) {
            $country = $this->fetchCountry($countryId, $context);
            $address->set(
                'street',
                $this->enderecoService->buildFullStreet(
                    $enderecoStreet,
                    $enderecoHousenumber,
                    $country ? $country->getIso() : ''
                )
            );
        }
    }
}
