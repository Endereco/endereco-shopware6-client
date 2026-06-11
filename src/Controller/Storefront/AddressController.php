<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Controller\Storefront;

use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\SessionManagementService;
use Exception;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;

/**
 * This controller is responsible for receiving an address from the frontend and saving it in the database.
 *
 * @author Michal Daniel
 * @author Ilja Weber
 *
 * @Route(defaults={"_routeScope"={"storefront"}}) // For SW Version >= 6.4.11.0
 */
class AddressController extends StorefrontController
{
    protected EntityRepository $addressRepository;

    protected EnderecoService $enderecoService;
    protected SessionManagementService $sessionManagementService;

    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     */
    public function __construct(
        EnderecoService $enderecoService,
        SessionManagementService $sessionManagementService,
        EntityRepository $addressRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->enderecoService = $enderecoService;
        $this->sessionManagementService = $sessionManagementService;
        $this->addressRepository = $addressRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Saves the address data from the request to the database.
     *
     * This method retrieves the address information (both billing and shipping)
     * from the request, verifies the existence of the address in the database
     * for the logged in customer, and then updates the address details in the database.
     * The method expects certain data to be present in the request and context
     * and throws exceptions if the data is not as expected.
     *
     * @param Request $request The request object containing address data.
     * @param SalesChannelContext $context The sales channel context.
     *
     * @throws CustomerNotLoggedInException If no customer is logged in.
     * @throws Exception If the sales channel data is incorrect or address data is missing.
     * @throws AddressNotFoundException If the address does not exist in the database.
     *
     * @return JsonResponse Returns a JSON response indicating the success of the operation.
     */
    public function saveAddress(Request $request, SalesChannelContext $context): JsonResponse
    {

        /** @var CustomerEntity|null $customer */
        $customer = $context->getCustomer();

        if (is_null($customer)) {
            throw CustomerNotLoggedInException::customerNotLoggedIn();
        }

        /** @var Context $mainContext */
        $mainContext = $context->getContext();

        /** @var string|null $salesChannelId */
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($mainContext);
        if (is_null($salesChannelId)) {
            throw new Exception('Something is wrong with the sales channel');
        }

        /** @var \Symfony\Component\HttpFoundation\InputBag<string> $requestInputBag */
        $requestInputBag = $request->request;

        // Recognize and store accountable session IDs at the beginning of the address save process
        $accountableSessionIds = $this->sessionManagementService->findAccountableSessionIds($requestInputBag->all());
        if (!empty($accountableSessionIds)) {
            $this->sessionManagementService->addAccountableSessionIdsToStorage($accountableSessionIds);
        }

        /** @var array<string, string> $billingAddress */
        $billingAddress = $requestInputBag->has('billingAddress')
            ? $requestInputBag->all('billingAddress')
            : [];

        /** @var array<string, string> $shippingAddressAddress */
        $shippingAddressAddress = $requestInputBag->has('shippingAddress')
            ? $requestInputBag->all('shippingAddress')
            : [];

        if (!empty($billingAddress)) {
            $address = $billingAddress;
        } elseif (!empty($shippingAddressAddress)) {
            $address = $shippingAddressAddress;
        } else {
            throw new Exception('Address is missing in the request data.');
        }

        /** @var string $addressId */
        $addressId = $address['id'];
        if (!$this->isAddressInTheDatabase($addressId, $context, $customer)) {
            throw new AddressNotFoundException($addressId);
        }

        // Address-shape input mirrors what UpsertAddressRoute would build before dispatching
        // MAPPING_ADDRESS_CREATE. We deliberately do not include identity fields (firstName,
        // lastName, salutationId, …) here because this endpoint is a partial update.
        $addressData = [
            'street' => $address['street'] ?? '',
            'city' => $address['city'] ?? '',
            'zipcode' => $address['zipcode'] ?? '',
            'countryId' => $address['countryId'] ?? null,
            'countryStateId' => !empty($address['countryStateId']) ? $address['countryStateId'] : null,
            'additionalAddressLine1' => $address['additionalAddressLine1'] ?? null,
            'additionalAddressLine2' => $address['additionalAddressLine2'] ?? null,
        ];

        // Same extension point Shopware uses in UpsertAddressRoute. Third-party plugins like
        // ACRIS hook in here to split street/houseNumber. Endereco's own CustomerAddressSubscriber
        // runs at priority -1000 and assembles the enderecoAddress extension + AMS payload.
        $mappingEvent = new DataMappingEvent(
            new RequestDataBag($address),
            $addressData,
            $mainContext
        );
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);
        $addressData = $mappingEvent->getOutput();

        $payload = (new CustomerAddressUpdatePayload($addressId, $customer->getId()))
            ->setStreet($addressData['street'] ?? '')
            ->setZipcode($addressData['zipcode'] ?? '')
            ->setCity($addressData['city'] ?? '')
            ->setCountryId($addressData['countryId'] ?? null)
            ->setCountryStateId($addressData['countryStateId'] ?? null)
            ->setAdditionalAddressLine1($addressData['additionalAddressLine1'] ?? null)
            ->setAdditionalAddressLine2($addressData['additionalAddressLine2'] ?? null);

        $updatePayload = $payload->toArray();

        // Carry over what subscribers contributed: customFields (e.g. ACRIS) and
        // extensions (enderecoAddress, plus anything else added by third parties).
        if (isset($addressData['customFields']) && is_array($addressData['customFields'])) {
            $updatePayload['customFields'] = $addressData['customFields'];
        }
        if (isset($addressData['extensions']) && is_array($addressData['extensions'])) {
            $updatePayload['extensions'] = array_merge(
                $updatePayload['extensions'] ?? [],   // should be empty in practice
                $addressData['extensions']            // {enderecoAddress: …, anyOtherPluginExt: …}
            );
        }

        $this->addressRepository->update([$updatePayload], $mainContext);

        return new JsonResponse(['addressSaved' => true]);
    }

    /**
     * Checks if the given address exists in the database for the specified customer.
     *
     * @param string $addressId The ID of the address to check.
     * @param SalesChannelContext $context The sales channel context.
     * @param CustomerEntity $customer The customer to check for.
     *
     * @return bool Returns true if the address exists in the database for the given customer, false otherwise.
     */
    private function isAddressInTheDatabase(
        string $addressId,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): bool {
        $criteria = new Criteria([$addressId]);
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        if (count($this->addressRepository->searchIds($criteria, $context->getContext())->getIds())) {
            return true;
        }

        return false;
    }
}
