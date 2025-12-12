<?php

namespace Endereco\Shopware6Client\Model;

/**
 * Interface for address check payloads supporting different address formats.
 *
 * This interface defines the common contract for address payloads that can be
 * used with the Endereco API, supporting both combined (traditional) and split
 * (street name + house number) address formats.
 */
interface AddressCheckPayloadInterface
{
    /**
     * Format type for combined street format (street name and house number in one field)
     */
    public const FORMAT_COMBINED = 'combined';

    /**
     * Format type for split street format (separate street name and house number fields)
     */
    public const FORMAT_SPLIT = 'split';
    /**
     * Converts the payload into an array format suitable for API submission.
     *
     * @return array<string, string> Array representation of the payload
     */
    public function data(): array;

    /**
     * Converts payload to JSON string with proper UTF-8 handling.
     *
     * @throws \JsonException On encoding failure
     * @return string JSON representation of address data
     */
    public function toJSON(): string;

    /**
     * Returns the format type of this payload.
     *
     * @return string Either 'combined' for traditional street format or 'split' for separate street name/house number
     */
    public function getFormatType(): string;
}
