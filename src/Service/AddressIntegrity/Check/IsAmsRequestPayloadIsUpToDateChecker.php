<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\Check;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoBaseAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Compares current address data with stored validation payload
 * Determines if validation metadata needs updating
 */
final class IsAmsRequestPayloadIsUpToDateChecker implements IsAmsRequestPayloadIsUpToDateCheckerInterface
{
    /**
     * @var AddressCheckPayloadBuilderInterface Builder service for address check payloads
     */
    private AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder;

    /**
     * @param AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder Creates validation payloads
     */
    public function __construct(AddressCheckPayloadBuilderInterface $addressCheckPayloadBuilder)
    {
        $this->addressCheckPayloadBuilder = $addressCheckPayloadBuilder;
    }

    /**
     * Validates customer address metadata freshness via payload comparison
     * @inheritDoc
     */
    public function checkIfCustomerAddressMetaIsUpToDate(
        CustomerAddressEntity $addressEntity,
        EnderecoCustomerAddressExtensionEntity $addressExtension,
        Context $context
    ): bool {

        if ($addressExtension->getAmsStatus() === EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED) {
            return true;
        }

        $amsRequestPayload = $this->addressCheckPayloadBuilder->buildFromCustomerAddress(
            $addressEntity,
            $context
        );
        $amsRequestPayloadString = $amsRequestPayload->toJSON();
        $persistedAmsRequestPayloadString = $addressExtension->getAmsRequestPayload();

        return crc32($amsRequestPayloadString) === crc32($persistedAmsRequestPayloadString);
    }

    /**
     * Validates order address metadata freshness via payload comparison
     * @inheritDoc
     */
    public function checkIfOrderAddressMetaIsUpToDate(
        OrderAddressEntity $addressEntity,
        EnderecoOrderAddressExtensionEntity $addressExtension,
        Context $context
    ): bool {

        if ($addressExtension->getAmsStatus() === EnderecoBaseAddressExtensionEntity::AMS_STATUS_NOT_CHECKED) {
            return true;
        }

        $amsRequestPayload = $this->addressCheckPayloadBuilder->buildFromOrderAddress(
            $addressEntity,
            $context
        );

        $amsRequestPayloadString = $amsRequestPayload->toJSON();
        $persistedAmsRequestPayloadString = $addressExtension->getAmsRequestPayload();

        return crc32($amsRequestPayloadString) === crc32($persistedAmsRequestPayloadString);
    }
}
