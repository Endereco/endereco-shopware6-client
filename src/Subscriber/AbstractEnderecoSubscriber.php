<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractEnderecoSubscriber implements EventSubscriberInterface
{
    protected SystemConfigService $systemConfigService;
    protected EnderecoService $enderecoService;
    protected EntityRepository $customerAddressRepository;
    protected EntityRepository $enderecoAddressExtensionRepository;
    protected EntityRepository $countryRepository;
    private RequestStack $requestStack;
    private array $countryMemCache = [];

    public function __construct(
        SystemConfigService $systemConfigService,
        EnderecoService     $enderecoService,
        EntityRepository    $customerAddressRepository,
        EntityRepository    $enderecoAddressExtensionRepository,
        EntityRepository    $countryRepository,
        RequestStack        $requestStack
    ) {
        $this->enderecoService = $enderecoService;
        $this->systemConfigService = $systemConfigService;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->enderecoAddressExtensionRepository = $enderecoAddressExtensionRepository;
        $this->countryRepository = $countryRepository;
        $this->requestStack = $requestStack;
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

        if (is_null($address->getCountry())) {
            $this->fetchAddressCountry($address, $context);
        }
        /* @var $enderecoAddress EnderecoAddressExtensionEntity */
        $enderecoAddress = $address->getExtension('enderecoAddress');

        if (!$enderecoAddress || !$this->isEnderecoAddressValid($enderecoAddress, $address)) {
            $this->updateAddress($address, $context);
        }
    }

    private function isEnderecoAddressValid(
        EnderecoAddressExtensionEntity $enderecoAddress,
        CustomerAddressEntity          $address
    ): bool {
        $enderecoFullStreet = $this->enderecoService->buildFullStreet(
            $enderecoAddress->getStreet(),
            $enderecoAddress->getHouseNumber(),
            $address->getCountry()->getIso()
        );
        return $enderecoFullStreet === $address->getStreet();
    }

    private function updateAddress(CustomerAddressEntity $address, Context $context): void
    {
        $countryIso = $address->getCountry()->getIso();
        list($street, $houseNumber) = $this->enderecoService->splitStreet($address->getStreet(), $countryIso, $context);

        if ($street) {
            $this->enderecoAddressExtensionRepository->upsert(
                [[
                    'addressId' => $address->getId(),
                    'street' => $street,
                    'houseNumber' => $houseNumber
                ]],
                $context
            );
        }
    }

    protected function fetchAddressCountry(CustomerAddressEntity $address, Context $context): void
    {
        $country = $this->fetchCountry($address->getCountryId(), $context);
        if ($country instanceof CountryEntity) {
            $address->setCountry($country);
        }
    }

    protected function fetchCountry(string $countryId, Context $context): ?CountryEntity
    {
        if (isset($this->countryMemCache[$countryId])) {
            return $this->countryMemCache[$countryId];
        }
        return $this->countryMemCache[$countryId] =
            $this->countryRepository->search(new Criteria([$countryId]), $context)->first();
    }

    protected function isEnderecoActive(?string $salesChannelId): bool
    {
        return $this->systemConfigService
            ->getBool('EnderecoShopware6Client.config.enderecoActiveInThisChannel', $salesChannelId);
    }

    protected function isStreetSplittingEnabled(?string $salesChannelId): bool
    {
        return
            $this->isEnderecoActive($salesChannelId) &&
            $this->systemConfigService
                ->getBool('EnderecoShopware6Client.config.enderecoSplitStreetAndHouseNumber', $salesChannelId);
    }

    protected function isCheckAddressEnabled(?string $salesChannelId): bool
    {
        return
            $this->isEnderecoActive($salesChannelId) &&
            $this->systemConfigService
                ->getBool('EnderecoShopware6Client.config.enderecoCheckExistingAddress', $salesChannelId);
    }

    protected function isCheckPayPalExpressAddressEnabled(?string $salesChannelId): bool
    {
        return
            $this->isEnderecoActive($salesChannelId) &&
            $this->systemConfigService
                ->getBool('EnderecoShopware6Client.config.enderecoCheckPayPalExpressAddress', $salesChannelId);
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
