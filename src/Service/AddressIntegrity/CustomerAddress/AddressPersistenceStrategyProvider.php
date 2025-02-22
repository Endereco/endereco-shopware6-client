<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Model\AddressPersistenceStrategy\DoNothing;
use Endereco\Shopware6Client\Model\AddressPersistenceStrategy\OverwriteNativeAndExtensionPostData;
use Endereco\Shopware6Client\Model\AddressPersistenceStrategy\PersistNativeAndExtensionFields;
use Endereco\Shopware6Client\Model\AddressPersistenceStrategy\PersistOnlyExtensionFields;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\AddressCorrectionScopeBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class AddressPersistenceStrategyProvider implements AddressPersistenceStrategyProviderInterface
{
    private AddressCorrectionScopeBuilderInterface $addressCorrectionScopeBuilder;
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private EntityRepository $customerAddressExtensionRepository;
    private EntityRepository $customerAddressRepository;

    public function __construct(
        AddressCorrectionScopeBuilderInterface $addressCorrectionScopeBuilder,
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        EntityRepository $customerAddressRepository,
        EntityRepository $customerAddressExtensionRepository
    ) {
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->addressCorrectionScopeBuilder = $addressCorrectionScopeBuilder;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerAddressExtensionRepository = $customerAddressExtensionRepository;
    }

    /**
     * Returns the appropriate address persistence strategy based on the available write permissions.
     * Strategy selection depends on which fields (native, extension, or both) can be written to.
     */
    public function getStrategy(
        CustomerAddressDTO $customerAddressDTO,
        Context $context
    ): CustomerAddressPersistenceStrategy {
        $postData = &$customerAddressDTO->getPostData();
        if ($postData) {
            return new OverwriteNativeAndExtensionPostData(
                $this->additionalAddressFieldChecker,
                $context
            );
        }

        $customerAddressExtensionEntity = $customerAddressDTO->getEnderecoCustomerAddressExtension();
        if ($customerAddressExtensionEntity) {
            $addressCorrectionScope = $this->addressCorrectionScopeBuilder->buildCustomerAddressCorrectionScope(
                $customerAddressExtensionEntity,
                $context
            );

            if ($addressCorrectionScope->canWriteNativeFields() && $addressCorrectionScope->canWriteExtensionFields()) {
                return new PersistNativeAndExtensionFields(
                    $this->additionalAddressFieldChecker,
                    $this->customerAddressRepository,
                    $this->customerAddressExtensionRepository,
                    $context
                );
            }

            if ($addressCorrectionScope->canWriteExtensionFields()) {
                return new PersistOnlyExtensionFields(
                    $this->customerAddressExtensionRepository,
                    $context
                );
            }
        }

        // DoNothing strategy is used when no fields can be written to (due to permissions or other constraints)
        // This preserves the original data without making any changes
        return new DoNothing();
    }
}
