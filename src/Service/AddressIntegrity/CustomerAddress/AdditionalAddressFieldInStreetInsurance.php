<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\ProcessContextService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Ensures that street numbers that are wrongfully stored in additionalAddressLine1
 * are moved into the street and additionalAddressLine1 is emptied.
*/
final class AdditionalAddressFieldInStreetInsurance implements IntegrityInsurance
{
    /**
     * @var ProcessContextService
     */
    private ProcessContextService $processContext;

    /**
     * @var AdditionalAddressFieldCheckerInterface
     */
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;

    /**
     * @var EnderecoService
     */
    private EnderecoService $enderecoService;

    /**
     * @var EntityRepository<CustomerAddressCollection>
     */
    private EntityRepository $customerAddressRepository;

    /**
     * @param ProcessContextService $processContext
     * @param AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker
     * @param EnderecoService $enderecoService
     * @param EntityRepository<CustomerAddressCollection> $customerAddressRepository
     */
    public function __construct(
        ProcessContextService $processContext,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        EnderecoService $enderecoService,
        EntityRepository $customerAddressRepository,
    ) {
        $this->processContext = $processContext;
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->enderecoService = $enderecoService;
        $this->customerAddressRepository = $customerAddressRepository;
    }

    /**
     * Get the priority for this insurance
     * This has to run after PayPalExpressFlagIsSetInsurance
     * @return int Priority value
     */
    public static function getPriority(): int
    {
        return -8;
    }

    /**
     * Ensures that a street number, that is wrongfully stored in additionalAddressLine1,
     * is merged into the street field and additionalAddressLine1 then cleared.
     * This is needed when a customer address is created by Paypal Express Checkout
     * and the additional address lines are set to not show in the Shopware configuration.
     *
     * @param CustomerAddressEntity $addressEntity The address entity to process
     * @param Context $context The Shopware context
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        $additionalAddressLine1 = $addressEntity->getAdditionalAddressLine1();
        $salesChannelId = $this->enderecoService->fetchSalesChannelId($context);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        if (is_null($salesChannelId) || !$this->enderecoService->isEnderecoPluginActive($salesChannelId)) {
            return;
        }

        if (
            empty($additionalAddressLine1) ||
            !$this->enderecoService->isPayPalCheckoutAddressCheckFeatureEnabled($salesChannelId) ||
            !$addressExtension->isPayPalAddress() ||
            !$this->processContext->isStorefront() ||
            $this->additionalAddressFieldChecker->hasAdditionalAddressField($context)
        ) {
            return;
        }

        $street = $addressEntity->getStreet();
        $mergedStreet = $street . ' ' . $additionalAddressLine1;

        $addressEntity->setStreet($mergedStreet);
        $addressEntity->setAdditionalAddressLine1('');

        $addressId = $addressEntity->getId();
        $customerId = $addressEntity->getCustomerId();

        $payload = new CustomerAddressUpdatePayload($addressId, $customerId);

        $payload->setStreet($mergedStreet);
        $payload->setAdditionalAddressLine1('');

        $this->customerAddressRepository->update([$payload->toArray()], $context);
    }
}
