<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;

/**
 * Strategy for persisting both native Shopware address fields and Endereco extension fields
 */
final class PersistNativeAndExtensionFields implements CustomerAddressPersistenceStrategy
{
    use CustomerAddressExtensionPersistenceStrategyTrait;

    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private EntityRepository $extensionRepository;
    private EntityRepository $addressRepository;
    private Context $context;

    public function __construct(
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        EntityRepository $customerAddressRepository,
        EntityRepository $customerAddressExtensionRepository,
        Context $context
    )
    {
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->addressRepository = $customerAddressRepository;
        $this->extensionRepository = $customerAddressExtensionRepository;
        $this->context = $context;
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
        $updatePayload = $this->buildNativeUpdatePayload($streetFull, $additionalInfo, $addressEntity);
        $this->addressRepository->update([$updatePayload], $this->context);

        // Update in memory
        $this->updateAddressEntityFields($streetFull, $additionalInfo, $addressEntity);
    }



    /**
     * Updates the customer address entity fields
     *
     * @param string $streetFull Complete street address
     * @param string|null $additionalInfo Additional address information
     * @param CustomerAddressEntity $addressEntity Address entity to update
     *
     * @return void
     */
    private function updateAddressEntityFields(
        string $streetFull,
        ?string $additionalInfo,
        CustomerAddressEntity $addressEntity
    ): void {
        $addressEntity->setStreet($streetFull);

        $setter = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldSetter($this->context);
        if ($setter && method_exists($addressEntity, $setter)) {
            $addressEntity->$setter($additionalInfo);
        }
    }

    /**
     * Builds the payload for updating native Shopware address fields
     *
     * @param string $streetFull Complete street address
     * @param string|null $additionalInfo Additional address information
     * @param CustomerAddressEntity $addressEntity Address entity being updated
     *
     * @return array<string, string|null> Update payload for the address repository
     */
    private function buildNativeUpdatePayload(
        string $streetFull,
        ?string $additionalInfo,
        CustomerAddressEntity $addressEntity
    ): array {
        $updateData = [
            'id' => $addressEntity->getId(),
            'street' => $streetFull,
        ];

        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($this->context)) {
            $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($this->context);
            $updateData[$fieldName] = $additionalInfo;
        }

        return $updateData;
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
}
