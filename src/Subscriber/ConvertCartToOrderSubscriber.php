<?php

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Service\OrderAddressToCustomerAddressDataMatcherInterface;
use Endereco\Shopware6Client\Struct\OrderAddressDataForComparison;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that handles copying Endereco address extension data during cart to order conversion.
 *
 * This subscriber listens for the CartConvertedEvent and ensures that Endereco-specific address
 * validation and enhancement data is preserved when a cart is converted to an order. It processes
 * both regular addresses and shipping addresses in deliveries.
 */
class ConvertCartToOrderSubscriber implements EventSubscriberInterface
{
    /**
     * Service for matching order addresses to customer addresses.
     *
     * @var OrderAddressToCustomerAddressDataMatcherInterface
     */
    private OrderAddressToCustomerAddressDataMatcherInterface $addressDataMatcher;

    /**
     * Initializes the subscriber with required dependencies.
     *
     * @param OrderAddressToCustomerAddressDataMatcherInterface $orderAddressToCustomerAddressDataMatcher
     *                                                                                  Service for matching addresses
     */
    public function __construct(
        OrderAddressToCustomerAddressDataMatcherInterface $orderAddressToCustomerAddressDataMatcher
    ) {
        $this->addressDataMatcher = $orderAddressToCustomerAddressDataMatcher;
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array<string, string> Array of event names mapped to handler methods
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'copyEnderecoAddressExtension'
        ];
    }

    /**
     * Copies Endereco address extension data from customer addresses to order addresses.
     *
     * Processes both regular addresses and shipping addresses in deliveries from the converted cart.
     * For each address, attempts to find a matching customer address and copy its Endereco extension data.
     *
     * @param CartConvertedEvent $event The cart converted event containing cart and conversion data
     * @return void
     */
    public function copyEnderecoAddressExtension(CartConvertedEvent $event): void
    {
        $convertedCart = $event->getConvertedCart();

        if (isset($convertedCart['addresses']) && is_array($convertedCart['addresses'])) {
            foreach ($convertedCart['addresses'] as $key => $address) {
                $this->amendOrderAddressData($address, $event->getCart(), $event->getSalesChannelContext());
                $convertedCart['addresses'][$key] = $address;
            }
        }

        if (isset($convertedCart['deliveries']) && is_array($convertedCart['deliveries'])) {
            foreach ($convertedCart['deliveries'] as $key => $delivery) {
                if (isset($delivery['shippingOrderAddress']) && is_array($delivery['shippingOrderAddress'])) {
                    $shippingOrderAddress = $delivery['shippingOrderAddress'];
                    $this->amendOrderAddressData(
                        $shippingOrderAddress,
                        $event->getCart(),
                        $event->getSalesChannelContext()
                    );
                    $convertedCart['deliveries'][$key]['shippingOrderAddress'] = $shippingOrderAddress;
                }
            }
        }

        $event->setConvertedCart($convertedCart);
    }

    /**
     * Amends order address data with Endereco extension data from matching customer addresses.
     *
     * Searches for matching customer addresses, filters for those with Endereco extensions,
     * and copies the extension data to the order address if a match is found.
     *
     * @param array<string, mixed> $address The order address data to amend
     * @param Cart $cart The current cart
     * @param SalesChannelContext $context The sales channel context
     * @return void
     */
    private function amendOrderAddressData(array &$address, Cart $cart, SalesChannelContext $context): void
    {
        $matchingCustomerAddresses = $this->addressDataMatcher->findCustomerAddressesForOrderAddressDataInCart(
            OrderAddressDataForComparison::fromCartToOrderConversionData($address),
            $cart,
            $context
        );

        // Filter out only those addresses that have the extension (which means highly likely the validation data too)
        $matchingCustomerAddresses = $matchingCustomerAddresses->filter(
            static function (CustomerAddressEntity $customerAddressEntity) {
                $customerAddressExtension = $customerAddressEntity->getExtension(
                    CustomerAddressExtension::ENDERECO_EXTENSION
                );

                return $customerAddressExtension instanceof EnderecoCustomerAddressExtensionEntity;
            }
        );

        $matchingCustomerAddress = $matchingCustomerAddresses->first();
        if ($matchingCustomerAddress instanceof CustomerAddressEntity) {
            $customerAddressExtension = $matchingCustomerAddress->getExtension(
                CustomerAddressExtension::ENDERECO_EXTENSION
            );

            // This check is logically not necessary because customer address entities without extension were
            // already filtered out. However, type safety demands it.
            if ($customerAddressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
                /** @var string $orderAddressId */
                $orderAddressId = $address['id'];

                $orderAddressExtensionEntity = $customerAddressExtension->createOrderAddressExtension($orderAddressId);
                $orderAddressExtensionData = $orderAddressExtensionEntity->buildCartToOrderConversionData();
                $address[OrderAddressExtension::ENDERECO_EXTENSION] = $orderAddressExtensionData;
            }
        }
    }
}
