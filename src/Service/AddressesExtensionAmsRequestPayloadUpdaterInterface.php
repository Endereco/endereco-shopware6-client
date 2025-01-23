<?php

namespace Endereco\Shopware6Client\Service;

use Shopware\Core\Framework\Context;

interface AddressesExtensionAmsRequestPayloadUpdaterInterface
{
    /**
     * Updates the `amsRequestPayload` property of customer address extensions.
     * Only address extensions with another `amsStatus` than "not-checked" will be updated.
     *
     * @param string[] $customerAddressIds
     * @param Context $context
     */
    public function updateCustomerAddressesAmsRequestPayload(
        array $customerAddressIds,
        Context $context
    ): void;

    /**
     * Updates the `amsRequestPayload` property of order address extensions.
     * Only address extensions with another `amsStatus` than "not-checked" will be updated.
     *
     * @param string[] $orderAddressIds
     * @param Context $context
     */
    public function updateOrderAddressesAmsRequestPayload(
        array $orderAddressIds,
        Context $context
    ): void;
}
