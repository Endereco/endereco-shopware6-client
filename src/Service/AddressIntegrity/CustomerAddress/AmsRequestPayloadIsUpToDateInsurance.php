<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Ensures AMS request payload matches current address data
 */
final class AmsRequestPayloadIsUpToDateInsurance implements IntegrityInsurance
{
    /** @var EnderecoService */
    private EnderecoService $enderecoService;

    /** @var IsAmsRequestPayloadIsUpToDateCheckerInterface */
    private IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker;

    /**
     * @param IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker Checker service
     * @param EnderecoService $enderecoService Endereco service
     */
    public function __construct(
        IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker,
        EnderecoService $enderecoService
    ) {
        $this->isAmsRequestPayloadIsUpToDateChecker = $isAmsRequestPayloadIsUpToDateChecker;
        $this->enderecoService = $enderecoService;
    }

    /**
     * Gets priority for insurance execution order
     *
     * @return int Priority value
     */
    public static function getPriority(): int
    {
        return -15;
    }

    /**
     * Ensures that the meta data from address check are up to date the the customer address entity. If its not, it
     * would mean that the address was changed, so the meta data is disgarded (reset to default). This would potentially
     * trigger an address check in a later coming ensurance or at leas make sure we dont work with wrong meta data.
     *
     * @param CustomerAddressEntity $addressEntity Customer address entity
     * @param Context $context Shopware context
     * @throws \RuntimeException If address extension not found
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function ensure(CustomerAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        if (!$addressExtension instanceof EnderecoCustomerAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $isRequestPayloadUpToDate = $this->isAmsRequestPayloadIsUpToDateChecker->checkIfCustomerAddressMetaIsUpToDate(
            $addressEntity,
            $addressExtension,
            $context
        );

        if (!$isRequestPayloadUpToDate) {
            $this->enderecoService->resetCustomerAddressMetaData($addressEntity, $context);
        }
    }
}
