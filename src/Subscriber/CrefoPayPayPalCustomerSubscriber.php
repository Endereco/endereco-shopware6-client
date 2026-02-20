<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Subscriber;

use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to set payPalExpressPayerId for CrefoPay PayPal Express guest customers.
 *
 * This subscriber listens to customer creation events and sets the payPalExpressPayerId
 * custom field for guest customers created via CrefoPay PayPal Express checkout.
 * This ensures that Endereco can properly identify these addresses as PayPal Express addresses.
 */
class CrefoPayPayPalCustomerSubscriber implements EventSubscriberInterface
{
    private const CREFO_PAY_PAYPAL_PAYMENT_METHOD_ID = '8d33bb143f554c3dabc4f604f03a6f16';
    private const CONFIG_KEY_CREFO_PAY_PAYPAL_CHECK =
        'EnderecoShopware6Client.config.enderecoCheckCrefoPayPayPalExpressAddress';

    private EntityRepository $customerRepository;
    private SystemConfigService $systemConfigService;
    private EnderecoService $enderecoService;

    public function __construct(
        EntityRepository $customerRepository,
        SystemConfigService $systemConfigService,
        EnderecoService $enderecoService
    ) {
        $this->customerRepository = $customerRepository;
        $this->systemConfigService = $systemConfigService;
        $this->enderecoService = $enderecoService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== 'customer') {
            return;
        }

        $context = $event->getContext();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        if (!$this->isCrefoPayPayPalCheckEnabled($salesChannelId)) {
            return;
        }

        foreach ($event->getIds() as $customerId) {
            $this->processCustomer($customerId, $context);
        }
    }

    /**
     * Processes a single customer to set payPalExpressPayerId if needed.
     *
     * @param string $customerId The customer ID
     * @param Context $context The Shopware context
     * @return void
     */
    private function processCustomer(string $customerId, Context $context): void
    {
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('defaultPaymentMethod');

        $customer = $this->customerRepository->search($criteria, $context)->first();

        if (!$customer instanceof CustomerEntity) {
            return;
        }

        if (!$customer->getGuest()) {
            return;
        }

        $customFields = $customer->getCustomFields();
        if (isset($customFields['payPalExpressPayerId']) && !empty($customFields['payPalExpressPayerId'])) {
            return;
        }

        $defaultPaymentMethod = $customer->getDefaultPaymentMethod();
        if (!$defaultPaymentMethod || $defaultPaymentMethod->getId() !== self::CREFO_PAY_PAYPAL_PAYMENT_METHOD_ID) {
            return;
        }

        $payerId = $this->generatePayerId();

        $this->customerRepository->update(
            [
                [
                    'id' => $customerId,
                    'customFields' => array_merge($customFields ?? [], [
                        'payPalExpressPayerId' => $payerId,
                    ]),
                ],
            ],
            $context
        );
    }

    private function generatePayerId(): string
    {
        if (isset($_SESSION['crefopay-paypal-express-transaction'])) {
            $transactionId = $_SESSION['crefopay-paypal-express-transaction'];
            if (!empty($transactionId)) {
                return 'crefopay-' . $transactionId;
            }
        }

        return 'crefopay-express';
    }

    private function isCrefoPayPayPalCheckEnabled(?string $salesChannelId): bool
    {
        if (!$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return false;
        }

        return $this->systemConfigService->getBool(
            self::CONFIG_KEY_CREFO_PAY_PAYPAL_CHECK,
            $salesChannelId
        );
    }
}
