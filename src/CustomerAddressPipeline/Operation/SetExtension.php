<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Ensures customer addresses have required Endereco extension entity
 */
final class SetExtension implements Operation
{
    /** @var EntityRepository */
    private EntityRepository $addressExtensionRepository;

    public function __construct(
        EntityRepository $addressExtensionRepository
    ) {
        $this->addressExtensionRepository = $addressExtensionRepository;
    }

    /** @return int Priority for execution order */
    public static function getPriority(): int
    {
        return -10;
    }

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
     * Creates extension if missing from customer address
     *
     * @param Workspace $workspace
     */
    public function process(Workspace $workspace): void
    {
        $addressEntity = $workspace->getAddressEntity();
        $context = $workspace->getContext();
        
        if ($addressEntity === null || $context === null) {
            return;
        }
        
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);

        if ($addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            return;
        }

        $this->createAndPersistExtension($addressEntity, $context);
    }

    /**
     * Creates and persists new address extension with default values
     *
     * @param CustomerAddressEntity $addressEntity Address to create extension for
     * @param Context $context Shopware context
     */
    protected function createAndPersistExtension(
        CustomerAddressEntity $addressEntity,
        Context $context
    ): void {
        $addressExtension = $this->createAddressExtensionWithDefaultValues($addressEntity);

        $this->addressExtensionRepository->upsert(
            [[
                'addressId' => $addressExtension->getAddressId(),
                'amsStatus' => $addressExtension->getAmsStatus(),
                'amsPredictions' => $addressExtension->getAmsPredictions()
            ]],
            $context
        );

        $this->addExtensionToAddressEntity($addressEntity, $addressExtension);
    }

    /**
     * Initializes extension with default values
     *
     * @param CustomerAddressEntity $addressEntity
     * @return EnderecoCustomerAddressExtensionEntity
     */
    protected function createAddressExtensionWithDefaultValues(
        CustomerAddressEntity $addressEntity
    ): EnderecoCustomerAddressExtensionEntity {
        return EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($addressEntity);
    }

    /**
     * Links extension to address entity
     *
     * @param CustomerAddressEntity $addressEntity
     * @param EnderecoBaseAddressExtensionEntity $addressExtension
     */
    protected function addExtensionToAddressEntity(
        CustomerAddressEntity $addressEntity,
        EnderecoBaseAddressExtensionEntity $addressExtension
    ): void {
        $addressEntity->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $addressExtension);
    }
}