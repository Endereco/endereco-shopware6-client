<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Service\CustomerAddressEntityUpdater;
use Endereco\Shopware6Client\Service\EnderecoExtensionEntityUpdater;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;

/**
 * Strategy for persisting both native Shopware address fields and Endereco extension fields
 */
final class PersistNativeAndExtensionFields implements CustomerAddressPersistenceStrategy
{

    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private EntityRepository $extensionRepository;
    private EntityRepository $addressRepository;
    private Context $context;
    private CustomerAddressEntityUpdater $entityUpdater;
    private EnderecoExtensionEntityUpdater $extensionEntityUpdater;

    public function __construct(
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        EntityRepository $customerAddressRepository,
        EntityRepository $customerAddressExtensionRepository,
        Context $context,
        CustomerAddressEntityUpdater $entityUpdater,
        EnderecoExtensionEntityUpdater $extensionEntityUpdater
    )
    {
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->addressRepository = $customerAddressRepository;
        $this->extensionRepository = $customerAddressExtensionRepository;
        $this->context = $context;
        $this->entityUpdater = $entityUpdater;
        $this->extensionEntityUpdater = $extensionEntityUpdater;
    }

    public function execute(
        string $normalizedStreetFull,
        ?string $normalizedAdditionalInfo,
        string $streetName,
        string $buildingNumber,
        CustomerAddressDTO $customerAddressDTO
    ): void {
        $addressEntity = $customerAddressDTO->getCustomerAddress();
        $addressExtension = $customerAddressDTO->getEnderecoCustomerAddressExtension();

        if ($addressExtension === null) {
            throw new \RuntimeException('Address extension cannot be null');
        }

        if ($addressEntity === null) {
            throw new \RuntimeException('Address entity cannot be null');
        }

        $this->maybeUpdateNative(
            $normalizedStreetFull,  $normalizedAdditionalInfo, $addressEntity
        );

        $this->maybeUpdateExtension(
            $streetName,
            $buildingNumber,
            $addressExtension
        );
    }

    /**
     * Updates the native address fields if values have changed
     *
     * @param string $streetFull Complete street address
     * @param string|null $additionalInfo Additional address information
     * @param CustomerAddressEntity $addressEntity Address entity to update
     *
     * @return void
     */
    private function maybeUpdateNative(string $streetFull, ?string $additionalInfo, CustomerAddressEntity $addressEntity): void
    {
        if (!$this->areValuesChanged($streetFull, $additionalInfo, $addressEntity)) {
            return;
        }

        // Update in DB
        $payload = $this->buildNativeUpdatePayload($streetFull, $additionalInfo, $addressEntity);
        $this->addressRepository->update([$payload->toArray()], $this->context);

        // Update in memory using normalized payload data
        $this->entityUpdater->updateFromPayload($payload, $addressEntity);
    }




    /**
     * Builds the payload for updating native Shopware address fields
     *
     * @param string $streetFull Complete street address
     * @param string|null $additionalInfo Additional address information
     * @param CustomerAddressEntity $addressEntity Address entity being updated
     *
     * @return CustomerAddressUpdatePayload Update payload for the address repository
     */
    private function buildNativeUpdatePayload(
        string $streetFull,
        ?string $additionalInfo,
        CustomerAddressEntity $addressEntity
    ): CustomerAddressUpdatePayload {
        $payload = new CustomerAddressUpdatePayload($addressEntity->getId());
        $payload->setStreet($streetFull);

        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($this->context)) {
            $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($this->context);
            if ($fieldName === CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1) {
                $payload->setAdditionalAddressLine1($additionalInfo);
            } elseif ($fieldName === CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2) {
                $payload->setAdditionalAddressLine2($additionalInfo);
            }
        }

        return $payload;
    }

    /**
     * Checks if any native address values have changed
     *
     * @param string $street New street value
     * @param string|null $additionalInfo New additional info value
     * @param CustomerAddressEntity $addressEntity The address entity to check against
     *
     * @return bool True if any values have changed, false otherwise
     */
    private function areValuesChanged(
        string $street,
        ?string $additionalInfo,
        CustomerAddressEntity $addressEntity
    ): bool {
        if ($addressEntity->getStreet() !== $street) {
            return true;
        }

        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($this->context)) {
            $getter = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldGetter($this->context);

            if ($getter !== null &&
                method_exists($addressEntity, $getter) &&
                $addressEntity->$getter() !== $additionalInfo
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Updates the address extension fields if values have changed
     *
     * @param string $streetName Street name
     * @param string $buildingNumber Building number
     * @param EnderecoCustomerAddressExtensionEntity $addressExtension Address extension entity
     *
     * @return void
     */
    private function maybeUpdateExtension(
        string $streetName,
        string $buildingNumber,
        EnderecoCustomerAddressExtensionEntity $addressExtension
    ): void {
        if (!$this->areExtensionValuesChanged($streetName, $buildingNumber, $addressExtension)) {
            return;
        }

        $extensionData = (new EnderecoExtensionData())
            ->setAddressId($addressExtension->getAddressId())
            ->setStreet($streetName)
            ->setHouseNumber($buildingNumber);
        
        $this->extensionRepository->update([$extensionData->toArray()], $this->context);

        // Update in memory using normalized payload data
        $this->extensionEntityUpdater->updateFromPayload($extensionData, $addressExtension);
    }

    /**
     * Checks if the extension entity values differ from the provided values
     *
     * @param string $streetName The street name to compare
     * @param string $houseNumber The house number to compare
     * @param EnderecoCustomerAddressExtensionEntity $extension The extension entity to check against
     *
     * @return bool True if any values have changed, false otherwise
     */
    private function areExtensionValuesChanged(
        string $streetName,
        string $houseNumber,
        EnderecoCustomerAddressExtensionEntity $extension
    ): bool {
        if ($extension->getStreet() !== $streetName) {
            return true;
        }

        if ($extension->getHouseNumber() !== $houseNumber) {
            return true;
        }

        return false;
    }
}
