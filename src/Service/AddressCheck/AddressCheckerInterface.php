<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckResult;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

interface AddressCheckerInterface
{
    /**
     * Validates a given address using the Endereco API.
     *
     * This method uses the provided customer address and sales channel ID to prepare a set of headers and a payload for
     * an address check request. The headers are generated using the sales channel settings and context, and the payload
     * is constructed using data from the address.
     *
     * The method then sends a request to the Endereco API and interprets the response. It can handle scenarios
     * where the street address is split into separate fields and where the country of the address has subdivisions.
     *
     * In case of any errors during the address check request, this method falls back to returning
     * a FailedAddressCheckResult.
     *
     * @param CustomerAddressEntity $addressEntity The customer address to be checked.
     * @param Context $context The context which includes details of the event triggering this method.
     * @param string $salesChannelId The ID of the sales channel the address is associated with.
     * @param string $sessionId (optional) The session ID. If not provided, a new one will be generated.
     *
     * @return AddressCheckResult The result of the address check operation.
     */
    public function checkAddress(
        CustomerAddressEntity $addressEntity,
        Context $context,
        string $salesChannelId,
        string $sessionId = ''
    ): AddressCheckResult;
}
