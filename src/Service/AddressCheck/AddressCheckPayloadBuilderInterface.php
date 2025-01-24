<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayload;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;

/**
 * Interface for building address check payloads for the Endereco API.
 *
 * This interface defines the contract for services that transform Shopware customer addresses
 * into API-compatible payloads for address validation. Implementers should handle:
 * - Sales channel specific configurations
 * - Address data extraction and formatting
 * - Locale and country code resolution
 * - State/subdivision code processing
 */
interface AddressCheckPayloadBuilderInterface
{
    /**
     * Builds an address check payload from a Shopware customer address entity.
     *
     * Takes a customer address and transforms it into a structured format suitable
     * for address validation via the Endereco API. Handles locale detection,
     * country codes, and state/subdivision processing.
     *
     * @param string $salesChannelId ID of the sales channel for configuration context
     * @param CustomerAddressEntity $addressEntity The customer address to transform
     * @param Context $context Shopware context for the operation
     *
     * @return AddressCheckPayload A structured payload ready for API submission
     */
    public function buildAddressCheckPayload(
        string $salesChannelId,
        CustomerAddressEntity $addressEntity,
        Context $context
    ): AddressCheckPayload;
}
