<?php

namespace Endereco\Shopware6Client\Struct;

/**
 * Represents order custom fields for address validation data.
 *
 * This struct encapsulates validation data for both billing and shipping addresses
 * in a format suitable for Shopware order custom fields. It provides a standardized
 * way to store and access address validation metadata within orders.
 *
 * @package Endereco\Shopware6Client\Struct
 * @final
 */
final class OrderCustomFields
{
    /**
     * Custom field key for billing address validation data.
     */
    public const BILLING_ADDRESS_VALIDATION_DATA = 'endereco_order_billing_addresses_validation_data_gh';

    /**
     * Custom field key for shipping address validation data.
     */
    public const SHIPPING_ADDRESS_VALIDATION_DATA = 'endereco_order_shipping_addresses_validation_data_gh';

    /**
     * List of all custom field keys used by this struct.
     *
     * @var string[]
     */
    public const FIELDS = [
        self::BILLING_ADDRESS_VALIDATION_DATA,
        self::SHIPPING_ADDRESS_VALIDATION_DATA,
    ];

    /**
     * Validation data for billing addresses.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $billingAddressValidationData;

    /**
     * Validation data for shipping addresses.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $shippingAddressValidationData;

    /**
     * Creates a new instance of OrderCustomFields.
     *
     * @param array<string, array<string, mixed>> $billingAddressValidationData Validation data for billing addresses
     * @param array<string, array<string, mixed>> $shippingAddressValidationData Validation data for shipping addresses
     */
    public function __construct(
        array $billingAddressValidationData,
        array $shippingAddressValidationData
    ) {
        $this->billingAddressValidationData = $billingAddressValidationData;
        $this->shippingAddressValidationData = $shippingAddressValidationData;
    }


    /**
     * Returns the custom fields data in a format ready for storage.
     *
     * The returned array uses the defined custom field keys as top-level keys,
     * with the corresponding validation data as values.
     *
     * @return array<string, array<string, array<string, mixed>>> Custom fields data
     */
    public function data(): array
    {
        return [
            self::BILLING_ADDRESS_VALIDATION_DATA => $this->billingAddressValidationData,
            self::SHIPPING_ADDRESS_VALIDATION_DATA => $this->shippingAddressValidationData,
        ];
    }
}
