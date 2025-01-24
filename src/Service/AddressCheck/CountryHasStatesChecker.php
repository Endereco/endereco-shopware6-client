<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Implements checking for country subdivision availability.
 *
 * This service determines whether a country has any administrative subdivisions
 * available for assignment. The result is used to determine if state information
 * should be considered during address validation processes.
 */
final class CountryHasStatesChecker implements CountryHasStatesCheckerInterface
{
    /**
     * Repository for accessing country entities
     */
    private EntityRepository $countryRepository;

    /**
     * Creates a new CountryHasStatesChecker with required dependencies.
     *
     * @param EntityRepository $countryRepository Repository for country entity access
     */
    public function __construct(
        EntityRepository $countryRepository
    ) {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @inheritDoc
     *
     * Implementation details:
     * 1. Creates criteria with country ID and states association
     * 2. Queries country repository
     * 3. Checks if states collection exists and has any entries
     *
     * If a country has no states available, its state field will be ignored
     * during address validation.
     *
     * @param string $countryId The ID of the country to check
     * @param Context $context Current Shopware context
     *
     * @return bool True if country has states available, false if country
     *              has no states or is not found
     */
    public function hasCountryStates(string $countryId, Context $context): bool
    {
        $criteria = new Criteria([$countryId]);
        $criteria->addAssociation('states');

        /** @var CountryEntity $country */
        $country = $this->countryRepository->search($criteria, $context)->first();

        // Check if the country was found and if it has more than one state
        // If so, return true, indicating that the country has subdivisions
        if (!is_null($country->getStates()) && $country->getStates()->count() > 1) {
            return true;
        }

        // If the country is not found or does not have more than one state, return false
        return false;
    }
}
