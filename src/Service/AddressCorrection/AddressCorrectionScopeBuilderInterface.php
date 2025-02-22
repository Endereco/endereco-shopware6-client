<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCorrection;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\CustomerAddressCorrectionScope;
use Shopware\Core\Framework\Context;

interface AddressCorrectionScopeBuilderInterface
{
    public function buildCustomerAddressCorrectionScope(
        EnderecoCustomerAddressExtensionEntity $customerAddressExtensionEntity,
        Context $context
    ): CustomerAddressCorrectionScope;
}
