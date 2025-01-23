<?php

namespace Endereco\Shopware6Client\Service;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionDefinition;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Model\AddressCheckData;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @phpstan-import-type AddressCheckDataData from AddressCheckData
 */
final class AddressesExtensionAmsRequestPayloadUpdater implements AddressesExtensionAmsRequestPayloadUpdaterInterface
{
    private EntityRepository $customerAddressRepository;
    private EntityRepository $orderAddressRepository;
    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;
    private EntityRepository $customerAddressExtensionRepository;
    private EntityRepository $orderAddressExtensionRepository;

    public function __construct(
        EntityRepository $customerAddressRepository,
        EntityRepository $orderAddressRepository,
        AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder,
        EntityRepository $customerAddressExtensionRepository,
        EntityRepository $orderAddressExtensionRepository
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
        $this->customerAddressExtensionRepository = $customerAddressExtensionRepository;
        $this->orderAddressExtensionRepository = $orderAddressExtensionRepository;
    }

    public function updateCustomerAddressesAmsRequestPayload(
        array $customerAddressIds,
        Context $context
    ): void {
        $criteria = new Criteria();
        $criteria->setIds($customerAddressIds);

        /** @var CustomerAddressCollection $customerAddressCollection */
        $customerAddressCollection = $this->customerAddressRepository->search($criteria, $context);

        $customerAddressExtensionUpdatePayloads = $this->buildUpdatePayloadsForCustomerAddresses(
            $customerAddressCollection,
            $context
        );

        if (count($customerAddressExtensionUpdatePayloads) === 0) {
            return;
        }

        $this->customerAddressExtensionRepository->update($customerAddressExtensionUpdatePayloads, $context);
    }

    public function updateOrderAddressesAmsRequestPayload(array $orderAddressIds, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setIds($orderAddressIds);

        /** @var OrderAddressCollection $orderAddressCollection */
        $orderAddressCollection = $this->orderAddressRepository->search($criteria, $context);

        $orderAddressExtensionUpdatePayloads = $this->buildUpdatePayloadsForCustomerAddresses(
            $orderAddressCollection,
            $context
        );

        if (count($orderAddressExtensionUpdatePayloads) === 0) {
            return;
        }

        $this->orderAddressExtensionRepository->update($orderAddressExtensionUpdatePayloads, $context);
    }

    /**
     * @param CustomerAddressCollection|OrderAddressCollection $addressCollection
     * @param Context $context
     * @return array{addressId: string, amsRequestPayload: AddressCheckDataData}[]
     */
    private function buildUpdatePayloadsForCustomerAddresses(
        $addressCollection,
        Context $context
    ): array {
        $updatePayloads = [];
        /** @var CustomerAddressEntity|OrderAddressEntity $addressEntity */
        foreach ($addressCollection as $addressEntity) {
            if ($addressEntity instanceof CustomerAddressEntity) {
                $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
                if ($addressExtension === null) {
                    continue;
                }
                /** @var EnderecoCustomerAddressExtensionEntity $addressExtension */

                if ($addressExtension->getAmsStatus() === EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED) {
                    continue;
                }
            }
            if ($addressEntity instanceof OrderAddressEntity) {
                $addressExtension = $addressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
                if ($addressExtension === null) {
                    continue;
                }
                /** @var EnderecoOrderAddressExtensionEntity $addressExtension */

                if ($addressExtension->getAmsStatus() === EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED) {
                    continue;
                }
            }

            $addressCheckPayload = $this->addressCheckPayloadBuilder->buildAddressCheckPayloadWithoutLanguage(
                $addressEntity,
                $context
            );

            $updatePayloads[] = [
                'addressId' => $addressEntity->getId(),
                'amsRequestPayload' => $addressCheckPayload->data()
            ];
        }

        return $updatePayloads;
    }
}
