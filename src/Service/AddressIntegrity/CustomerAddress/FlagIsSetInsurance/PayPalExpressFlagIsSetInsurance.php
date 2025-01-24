<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\FlagIsSetInsurance;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress\IntegrityInsurance;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Insurance class to handle PayPal Express address flag setting
 */
final class PayPalExpressFlagIsSetInsurance implements IntegrityInsurance
{
    private EntityRepository $customerRepository;
    private EntityRepository $addressExtensionRepository;

    /**
     * @param EntityRepository $customerRepository Repository to fetch customer data
     * @param EntityRepository $addressExtensionRepository Repository to manage address extension data
     */
    public function __construct(
        EntityRepository $customerRepository,
        EntityRepository $addressExtensionRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    /**
     * Get the priority for this insurance
     *
     * @return int Priority value
     */
    public static function getPriority(): int
    {
        return -10;
    }

    /**
     * Ensures the PayPal Express flag is properly set for the given address
     *
     * @param CustomerAddressEntity $addressEntity The address entity to process
     * @param Context $context The Shopware context
     * @throws \RuntimeException When address extension is not set
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $customer = $this->getCustomer($addressEntity->getCustomerId(), $context);
        $flagValue = $this->checkIfFromPayPal($customer);
        $this->persistFlagValue($addressExtension, $flagValue, $context);
        $this->setFlagInExtension($addressExtension, $flagValue);
    }

    /**
     * Retrieves customer entity by ID
     *
     * @param string $customerId The customer ID
     * @param Context $context The Shopware context
     * @return CustomerEntity
     * @throws \RuntimeException When customer not found
     */
    private function getCustomer(string $customerId, Context $context): CustomerEntity
    {
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->first();
        if (!$customer instanceof CustomerEntity) {
            throw new \RuntimeException('Customer not found');
        }
        return $customer;
    }

    /**
     * Checks if customer has been created with PayPal Express plugin.
     *
     * @param CustomerEntity $customer The customer entity to check
     * @return bool True if PayPal Express payer ID exists
     */
    private function checkIfFromPayPal(CustomerEntity $customer): bool
    {
        /** @var array<string, mixed>|null $customerCustomFields */
        $customerCustomFields = $customer->getCustomFields();
        return isset($customerCustomFields['payPalExpressPayerId']);
    }

    /**
     * Persists the PayPal Express flag value to the database
     *
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension The address extension entity
     * @param bool $flagValue The flag value to persist
     * @param Context $context The Shopware context
     */
    private function persistFlagValue(
        EnderecoCustomerAddressExtensionEntity $addressExtension,
        bool $flagValue,
        Context $context
    ): void {
        $this->addressExtensionRepository->upsert(
            [
                [
                    'addressId' => $addressExtension->getAddressId(),
                    'isPayPalAddress' => $flagValue
                ]
            ],
            $context
        );
    }

    /**
     * Sets the PayPal Express Checkout flag in the extension entity
     *
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension The address extension entity
     * @param bool $value The flag value to set
     */
    private function setFlagInExtension(EnderecoCustomerAddressExtensionEntity $addressExtension, bool $value): void
    {
        $addressExtension->setIsPayPalAddress($value);
    }
}
