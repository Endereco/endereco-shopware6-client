<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\Compatibility\CrefoPay\CrefoPaySessionKeys;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * When a modal-level AMS correction is required, removes the CrefoPay PayPal Express
 * session markers so the user can go through the standard correction flow instead of
 * being held in PayPal Express mode.
 */
final class CrefoPayExpressAbortedOnCorrectionInsurance implements IntegrityInsurance
{
    public function __construct(
        private readonly ProcessContextService $processContext,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getPriority(): int
    {
        return -30;
    }

    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        if (!$this->processContext->isStorefront()) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return;
        }

        $session = $request->getSession();
        if (!$session->has(CrefoPaySessionKeys::PAYPAL_EXPRESS_TRANSACTION)) {
            return;
        }

        if (!$addressExtension->needsCorrectionInFrontend()) {
            return;
        }

        // When native-field overwriting is disabled, minor corrections are applied without a
        // modal by the frontend JS instead of server-side. The PayPal Express session must stay intact.
        if ($addressExtension->hasMinorCorrection()) {
            return;
        }

        $session->remove(CrefoPaySessionKeys::PAYPAL_EXPRESS_TRANSACTION);
        unset($_SESSION[CrefoPaySessionKeys::PAYPAL_EXPRESS_ACTIVE]);
    }
}
