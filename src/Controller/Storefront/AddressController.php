<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Controller\Storefront;

use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Exception;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

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
    protected AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;
    protected EnderecoService $enderecoService;

    public function __construct(
        EnderecoService $enderecoService,
        EntityRepository $addressRepository,
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder
    ) {
        $this->enderecoService = $enderecoService;
        $this->addressRepository = $addressRepository;
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
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

        /** @var \Symfony\Component\HttpFoundation\InputBag $requestInputBag */
        $requestInputBag = $request->request;

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

        if (empty($address['amsPredictions'])) {
            $predictions = [];
        } else {
            $predictions = json_decode($address['amsPredictions'], true);
        }

        $updatePayload = [
            'id' => $addressId,
            'countryId' => $address['countryId'],
            'countryStateId' => $address['countryStateId'] ?? null,
            'city' => $address['city'],
            'zipcode' => $address['zipcode'],
            'street' => $address['street'] ?? '',
            'extensions' => [
                'enderecoAddress' => [
                    'street' => $address['enderecoStreet'] ?? '',
                    'houseNumber' => $address['enderecoHousenumber'] ?? '',
                    'amsStatus' => $address['amsStatus'],
                    'amsPredictions' => $predictions,
                    'amsTimestamp' => time()
                ]
            ]
        ];

        // Quickfix for missing country state id.
        if (empty($updatePayload['countryStateId'])) {
            unset($updatePayload['countryStateId']);
        }

        // Make sure that custom "street name" and "house number" are filled or the default "street" is filled.
        $this->enderecoService->syncStreet($updatePayload, $mainContext, $salesChannelId);

        // Calculate payload
        $payloadBody = $this->addressCheckPayloadBuilder->buildFromArray(
            [
                'countryId' => $updatePayload['countryId'],
                'countryStateId' => $updatePayload['countryStateId'],
                'zipcode' => $updatePayload['zipcode'],
                'city' => $updatePayload['city'],
                'street' => $updatePayload['street']
            ],
            $mainContext
        );
        $updatePayload['extensions']['enderecoAddress']['amsRequestPayload'] = $payloadBody->toJSON();


        // Update the data in the database.
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
