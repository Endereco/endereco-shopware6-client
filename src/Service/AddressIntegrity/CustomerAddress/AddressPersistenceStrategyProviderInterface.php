<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressIntegrity\CustomerAddress;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Shopware\Core\Framework\Context;

interface AddressPersistenceStrategyProviderInterface
{
    public function getStrategy(
        CustomerAddressDTO $customerAddressDTO,
        Context $context
    ): CustomerAddressPersistenceStrategy;
}
