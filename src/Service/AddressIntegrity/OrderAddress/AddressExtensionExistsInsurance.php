<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

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

        $persistedAddressExtension = $this->addressExtensionRepository->search(new Criteria([$addressEntity->getId()]), $context)->first();
        if ($persistedAddressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $persistedAddressExtension);

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
        $addressExtension = EnderecoOrderAddressExtensionEntity::createWithDefaultValues($addressEntity->getId(), $addressEntity->getVersionId());

        $this->addressExtensionRepository->upsert(
            [[
                'id' => $addressExtension->getId(),
                'versionId' => $addressEntity->getVersionId(),
                'addressId' => $addressExtension->getAddressId(),
                'addressVersionId' => $addressExtension->getAddressVersionId(),
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
     * Adds extension to address entity
     */
    protected function addExtensionToAddressEntity(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension
    ): void {
        $addressEntity->addExtension(OrderAddressExtension::ENDERECO_EXTENSION, $addressExtension);
    }
}