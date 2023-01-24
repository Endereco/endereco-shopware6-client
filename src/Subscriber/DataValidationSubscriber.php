<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class DataValidationSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    )
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.create' => 'onFormValidation',
            'framework.validation.address.update' => 'onFormValidation'
        ];
    }

    public function onFormValidation(BuildValidationEvent $event): void
    {
        $salesChannelId = $this->fetchSalesChannelId($event);
        if (
            !$this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId) ||
            !$this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId)
        ) {
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

    private function fetchSalesChannelId(BuildValidationEvent $event): ?string
    {
        $source = $event->getContext()->getSource();
        if ($source instanceof SalesChannelApiSource) {
            return $source->getSalesChannelId();
        }
        return null;
    }
}
