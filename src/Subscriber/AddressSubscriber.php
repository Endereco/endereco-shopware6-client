<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
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
            CustomerEvents::MAPPING_ADDRESS_CREATE => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_BILLING => 'onMappingCreate',
            CustomerEvents::MAPPING_REGISTER_ADDRESS_SHIPPING => 'onMappingCreate'
        ];
    }

    public function onFormValidation(BuildValidationEvent $event): void
    {
        $salesChannelId = $this->fetchSalesChannelId($event->getContext());
        if (!$this->isStreetSplittingEnabled($salesChannelId)) {
            return;
        }
        $data = $event->getData();
        $address = $data->get('address');
        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');

        $this->overrideStreetWithSplittedData($data);
        $this->overrideStreetWithSplittedData($address);
        $this->overrideStreetWithSplittedData($billingAddress);
        $this->overrideStreetWithSplittedData($shippingAddress);

        $definition = $event->getDefinition();
        $definition->add('enderecoStreet', new NotBlank());
        $definition->add('enderecoHousenumber', new NotBlank());
    }

    public function onMappingCreate(DataMappingEvent $event): void
    {

        $salesChannelId = $this->fetchSalesChannelId($event->getContext());
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
        $output['street'] = sprintf('%s %s', $enderecoStreet, $enderecoHousenumber);
        $output['extensions']['enderecoAddress'] = [
            'street' => $enderecoStreet,
            'houseNumber' => $enderecoHousenumber
        ];
        $event->setOutput($output);
    }

    private function overrideStreetWithSplittedData(?DataBag $address): void
    {
        if (!$address instanceof RequestDataBag) {
            return;
        }
        $enderecoStreet = $address->get('enderecoStreet');
        $enderecoHousenumber = $address->get('enderecoHousenumber');
        if (!empty($enderecoStreet) && !empty($enderecoHousenumber)) {
            $address->set('street', sprintf('%s %s', $enderecoStreet, $enderecoHousenumber));
        }
    }
}
