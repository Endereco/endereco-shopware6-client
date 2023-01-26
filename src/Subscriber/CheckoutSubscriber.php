<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutSubscriber extends AbstractEnderecoSubscriber
{
    private EnderecoService $enderecoService;
    private EntityRepository $customerAddressRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService     $enderecoService,
        EntityRepository    $customerAddressRepository
    )
    {
        $this->enderecoService = $enderecoService;
        $this->customerAddressRepository = $customerAddressRepository;
        parent::__construct($systemConfigService);
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckoutConfirmPageLoadedEvent::class => 'ensureAddressesAreSplit'];
    }

    public function ensureAddressesAreSplit(CheckoutConfirmPageLoadedEvent $event): void
    {
        if (!$this->isStreetSplittingEnabled($event->getSalesChannelContext()->getSalesChannelId())) {
            return;
        }

        $salesChannelContext = $event->getSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();
        if (is_null($customer)) {
            return;
        }
        $this->checkAddress($salesChannelContext, $customer->getActiveShippingAddress());
        $this->checkAddress($salesChannelContext, $customer->getActiveBillingAddress());
    }


    /**
     * Checking if current customer address is split.
     * If not or currently saved Shopware address is different - split it automatically
     */
    private function checkAddress(SalesChannelContext $salesChannelContext, ?CustomerAddressEntity $address = null): void
    {
        if (is_null($address)) {
            return;
        }
        /* @var $enderecoAddress EnderecoAddressExtensionEntity */
        $enderecoAddress = $address->getExtension('enderecoAddress');

        if (!$enderecoAddress && !is_null($address->getCountry())) {
            $this->updateAddress($address, $salesChannelContext);
        } elseif (
            $enderecoAddress &&
            sprintf(
                '%s %s',
                $enderecoAddress->getStreet(),
                $enderecoAddress->getHouseNumber()) !== $address->getStreet()
            && !is_null($address->getCountry())
        ) {
            $this->updateAddress($address, $salesChannelContext);
        }
    }

    private function updateAddress(CustomerAddressEntity $address, SalesChannelContext $salesChannelContext): void
    {
        $countryIso = $address->getCountry()->getIso();
        list($street, $houseNumber) = $this->enderecoService->splitStreet($address->getStreet(), $countryIso, $salesChannelContext->getContext());

        $this->customerAddressRepository->update(
            [[
                'id' => $address->getId(),
                'extensions' => [
                    'enderecoAddress' => [
                        'street' => $street,
                        'houseNumber' => $houseNumber
                    ]
                ]
            ]],
            $salesChannelContext->getContext());
    }
}
