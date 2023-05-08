<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Controller\Storefront;

use Exception;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;

/**
 * @RouteScope(scopes={"storefront"})
 */
class AddressController extends StorefrontController
{
    private EntityRepository $addressRepository;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepository $addressRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->addressRepository = $addressRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Since("6.0.0.0")
     * @LoginRequired()
     * @Route(
     *     "/account/endereco/address",
     *     name="frontend.endereco.account.address.edit.save",
     *     options={"seo"="false"},
     *     methods={"POST"},
     *     defaults={"XmlHttpRequest"=true})
     *
     * @throws CustomerNotLoggedInException
     * @throws Exception
     */
    public function saveAddress(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        /** @var RequestDataBag $address */
        $address = $data->get('billingAddress') ?? $data->get('shippingAddress');

        if (is_null($address) || !($addressId = $address->get('id'))) {
            throw new Exception('Missing address id');
        }

        $this->validateAddress($addressId, $context, $customer);

        $addressData = [
            'id' => $addressId,
            'street' => $address->get('street'),
            'city' => $address->get('city'),
            'zipcode' => $address->get('zipcode'),
            'phoneNumber' => $address->get('phoneNumber')
        ];

        $mappingEvent = new DataMappingEvent($address, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['customerId'] = $customer->getId();

        $this->addressRepository->upsert([$addressData], $context->getContext());

        return new JsonResponse(['addressSaved' => true]);
    }


    private function validateAddress(string $id, SalesChannelContext $context, CustomerEntity $customer): void
    {
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        if (count($this->addressRepository->searchIds($criteria, $context->getContext())->getIds())) {
            return;
        }

        throw new AddressNotFoundException($id);
    }
}
