<?php

namespace Endereco\Shopware6Client\Service\AddressIntegrity\OrderAddress;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\OrderAddress\OrderAddressExtension;
use Endereco\Shopware6Client\Service\AddressIntegrity\Check\IsAmsRequestPayloadIsUpToDateCheckerInterface;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;

final class AmsRequestPayloadIsUpToDateInsurance implements IntegrityInsurance
{
    /** @var EnderecoService */
    private EnderecoService $enderecoService;

    /** @var IsAmsRequestPayloadIsUpToDateCheckerInterface */
    private IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker;

    public function __construct(
        IsAmsRequestPayloadIsUpToDateCheckerInterface $isAmsRequestPayloadIsUpToDateChecker,
        EnderecoService $enderecoService
    ) {
        $this->isAmsRequestPayloadIsUpToDateChecker = $isAmsRequestPayloadIsUpToDateChecker;
        $this->enderecoService = $enderecoService;
    }

    /** @return int */
    public static function getPriority(): int
    {
        return -20;
    }

    /**
     * Resets metadata if address data changed since last check
     * @param OrderAddressEntity $addressEntity
     * @param Context $context
     * @throws \RuntimeException If extension missing
     */
    public function ensure(OrderAddressEntity $addressEntity, Context $context): void
    {
        $addressExtension = $addressEntity->getExtension(OrderAddressExtension::ENDERECO_EXTENSION);
        if (!$addressExtension instanceof EnderecoOrderAddressExtensionEntity) {
            throw new \RuntimeException('The address extension should be set at this point');
        }

        $isRequestPayloadUpToDate = $this->isAmsRequestPayloadIsUpToDateChecker->checkIfOrderAddressMetaIsUpToDate(
            $addressEntity,
            $addressExtension,
            $context
        );

        if (!$isRequestPayloadUpToDate) {
            $this->enderecoService->resetOrderAddressMetaData($addressEntity, $context);
        }
    }
}
