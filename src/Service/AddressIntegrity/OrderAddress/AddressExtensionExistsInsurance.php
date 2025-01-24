<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class AddressExtensionExistsInsurance implements IntegrityInsurance
{
    private EntityRepository $addressExtensionRepository;

    public function __construct(
        EntityRepository $addressExtensionRepository
    ) {
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    public static function getPriority(): int
    {
        return 0;
    }

    /**
     * Ensures order address has required Endereco extension
     *
     * @param OrderAddressEntity $addressEntity Order address to check
     * @param Context $context Shopware context
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);

        if ($addressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            return;
        }

        $this->createAndPersistExtension($addressEntity, $context);
    }

    /**
     * Creates and persists new address extension with default values
     *
     * @param OrderAddressEntity $addressEntity Address to create extension for
     * @param Context $context Shopware context
     */
    protected function createAndPersistExtension(
        OrderAddressEntity $addressEntity,
        Context $context
    ): void {
        /** @var EnderecoOrderAddressExtensionEntity $addressExtension */
        $addressExtension = $this->createAddressExtensionWithDefaultValues($addressEntity);

        $this->addressExtensionRepository->upsert(
            [[
                'addressId' => $addressExtension->getAddressId(),
                'amsRequestPayload' => $addressExtension->getAmsRequestPayload(),
                'amsStatus' => $addressExtension->getAmsStatus(),
                'amsPredictions' => $addressExtension->getAmsPredictions(),
                'amsTimestamp' => $addressExtension->getAmsTimestamp(),
            ]],
            $context
        );

        $this->addExtensionToAddressEntity($addressEntity, $addressExtension);
    }

    /**
     * Creates new address extension with default values
     */
    protected function createAddressExtensionWithDefaultValues(
        OrderAddressEntity $addressEntity
    ): EnderecoOrderAddressExtensionEntity {
        $addressExtension = new EnderecoOrderAddressExtensionEntity();
        $addressExtension->setAddressId($addressEntity->getId());
        $addressExtension->setAddress($addressEntity);
        return $addressExtension;
    }

    /**
     * Adds extension to address entity
     */
    protected function addExtensionToAddressEntity(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension
    ): void {
        $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $addressExtension);
    }
}