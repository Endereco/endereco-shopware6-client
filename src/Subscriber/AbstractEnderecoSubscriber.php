<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractEnderecoSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $customerAddressRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService     $enderecoService,
        EntityRepository    $customerAddressRepository
    )
    {
        $this->enderecoService = $enderecoService;
        $this->systemConfigService = $systemConfigService;
        $this->customerAddressRepository = $customerAddressRepository;
    }

    abstract public static function getSubscribedEvents(): array;

    /**
     * Checking if current customer address is split.
     * If not or currently saved Shopware address is different - split it automatically
     */
    protected function ensureAddressIsSplit(Context $context, ?CustomerAddressEntity $address = null): void
    {
        if (is_null($address)) {
            return;
        }
        /* @var $enderecoAddress EnderecoAddressExtensionEntity */
        $enderecoAddress = $address->getExtension('enderecoAddress');

        if (!$enderecoAddress && !is_null($address->getCountry())) {
            $this->updateAddress($address, $context);
        } elseif (
            $enderecoAddress &&
            sprintf(
                '%s %s',
                $enderecoAddress->getStreet(),
                $enderecoAddress->getHouseNumber()) !== $address->getStreet()
            && !is_null($address->getCountry())
        ) {
            $this->updateAddress($address, $context);
        }
    }

    private function updateAddress(CustomerAddressEntity $address, Context $context): void
    {
        $countryIso = $address->getCountry()->getIso();
        list($street, $houseNumber) = $this->enderecoService->splitStreet($address->getStreet(), $countryIso, $context);

        if ($street && $houseNumber) {
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
                $context);
        }
    }

    protected function isStreetSplittingEnabled(?string $salesChannelId): bool
    {
        return
            $this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId) &&
            $this->systemConfigService->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);
    }

    protected function fetchSalesChannelId(Context $context): ?string
    {
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            return $source->getSalesChannelId();
        }
        return null;
    }
}
