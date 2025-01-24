<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;

/**
 * Defines interface for retrieving ISO codes of administrative subdivisions.
 *
 * This interface specifies the contract for services that fetch standardized
 * subdivision codes (e.g., state/province codes) used in address validation.
 * The returned codes are used to verify and validate administrative divisions
 * during address checks.
 */
interface SubdivisionCodeFetcherInterface
{
    /**
     * Fetches the ISO code for an administrative subdivision.
     *
     * Retrieves the standardized code for a country's subdivision (state/province)
     * using its Shopware ID. Used during address validation to ensure correct
     * administrative division information.
     *
     * @param string $countryStateId Shopware ID of the subdivision to look up
     * @param Context $context Current Shopware context
     *
     * @return string|null Uppercase ISO code of the subdivision if found,
     *                     null if subdivision doesn't exist
     */
    public function fetchSubdivisionCodeByCountryStateId(string $countryStateId, Context $context): ?string;
}
