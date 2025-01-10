<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;

/**
 * Defines interface for checking if countries support administrative subdivisions.
 *
 * This interface specifies the contract for services that determine whether a country
 * has any administrative subdivisions (states/provinces) available for assignment.
 * This check is used to determine if state information should be considered during
 * address validation - if a country has no states, the state field will be ignored
 * in address checks.
 */
interface CountryHasStatesCheckerInterface
{
    /**
     * Checks if a country, specified by its ID, has associated subdivisions (states).
     *
     * This method searches the country repository for a country that matches the provided ID.
     * If the country is found, it checks if the country has any associated states.
     * If the country has more than one associated state, it returns true, indicating that the
     * country has subdivisions. If no states are associated or only one is present, it returns false.
     *
     * @param string $countryId The ID of the country to check for subdivisions.
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return bool True if the country has more than one subdivision, false otherwise.
     */
    public function hasCountryStates(string $countryId, Context $context): bool;
}