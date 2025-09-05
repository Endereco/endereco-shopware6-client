<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Operation class to handle Amazon Pay address flags
 */
final class SetAmazonFlag implements Operation
{
    private  EntityRepository $customerRepository;
    private  EntityRepository $addressExtensionRepository;

    /**
     * @param EntityRepository $customerRepository Repository for customer entities
     * @param EntityRepository $addressExtensionRepository Repository for address extension entities
     */
    public function __construct(
        EntityRepository $customerRepository,
        EntityRepository $addressExtensionRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    /**
     * Get the priority for this operation
     *
     * @return int Priority value
     */
    public static function getPriority(): int
    {
        return -40;
    }

    /**
     * Determines whether this operation applies to the given address
     *
     * @param Workspace $workspace Customer address workspace
     * @return bool Always returns true to maintain current behavior
     */
    public function applies(Workspace $workspace): bool
    {
        // Skip if workspace is marked to skip
        if ($workspace->shouldSkip()) {
            return false;
        }

        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();
        
        if ($addressEntity === null || $context === null) {
            return false;
        }
        
        return true;
    }

    /**
     * Ensures the Amazon Pay flag is properly set for the given address
     *
     * @param Workspace $workspace The address workspace to process
     * @throws \RuntimeException When address extension is not set
     */
    public function process(Workspace $workspace): void
    {
        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();
        
        if ($addressEntity === null || $context === null) {
            return;
        }
        
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $customer = $this->getCustomer($addressEntity->getCustomerId(), $context);
        $flagValue = $this->checkIfFromAmazon($customer);
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
     * Checks if customer has been created with amazon pay plugin.
     *
     * @param CustomerEntity $customer The customer entity to check
     *
     * @return bool True if Amazon Pay account ID exists which means it was created by the plugin
     */
    private function checkIfFromAmazon(CustomerEntity $customer): bool
    {
        /** @var array<string, mixed>|null $customerCustomFields */
        $customerCustomFields = $customer->getCustomFields();
        return isset($customerCustomFields['swag_amazon_pay_account_id']);
    }

    /**
     * Persists the Amazon Pay flag value to the database
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
                    'isAmazonPayAddress' => $flagValue
                ]
            ],
            $context
        );
    }

    /**
     * Sets the Amazon Pay flag in the extension entity
     *
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension The address extension entity
     * @param bool $value The flag value to set
     */
    private function setFlagInExtension(EnderecoCustomerAddressExtensionEntity $addressExtension, bool $value): void
    {
        $addressExtension->setIsAmazonPayAddress($value);
    }
}