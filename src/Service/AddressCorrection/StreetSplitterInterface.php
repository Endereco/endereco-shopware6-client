<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCorrection;

use Endereco\Shopware6Client\DTO\SplitStreetResultDto;
use Shopware\Core\Framework\Context;

interface StreetSplitterInterface
{
    /**
     * Splits a full street address into its components using the Endereco API.
     *
     * This method sends a "splitStreet" request to the Endereco API with a payload that includes the full street,
     * the country code (as the formatCountry), a fixed language ('de'), and optionally additional address information.
     * The API response is expected to contain the individual components: the street name, house number, and possibly
     * updated additional info. In case of any error, the method logs the issue and falls back to returning the API's
     * default values (with empty strings for missing components).
     *
     * @param string      $fullStreet      The full street address to be split.
     * @param ?string     $additionalInfo  Optional additional address information to be sent with the request.
     * @param string      $countryCode     The country code corresponding to the address.
     * @param Context     $context         The Shopware context providing details about the current execution.
     * @param ?string     $salesChannelId  The identifier for the sales channel associated with the address.
     *
     * @return SplitStreetResultDto
     */
    public function splitStreet(
        string $fullStreet,
        ?string $additionalInfo,
        string $countryCode,
        Context $context,
        ?string $salesChannelId
    ): SplitStreetResultDto;
}
