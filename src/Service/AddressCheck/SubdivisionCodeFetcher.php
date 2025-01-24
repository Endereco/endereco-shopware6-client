<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;

/**
 * Implements retrieval of standardized subdivision codes.
 *
 * This service fetches and standardizes administrative subdivision codes
 * (state/province) from Shopware's country state repository for use in
 * address validation. Returns uppercase codes to maintain consistency
 * with API requirements.
 */
final class SubdivisionCodeFetcher implements SubdivisionCodeFetcherInterface
{
    /**
     * Repository for accessing country state entities
     */
    private EntityRepository $countryStateRepository;

    /**
     * Creates a new SubdivisionCodeFetcher with required dependencies.
     *
     * @param EntityRepository $countryStateRepository Repository for country state access
     */
    public function __construct(
        EntityRepository $countryStateRepository
    ) {
        $this->countryStateRepository = $countryStateRepository;
    }

    /**
     * @inheritDoc
     *
     * Implementation details:
     * 1. Searches for country state using provided ID
     * 2. If found, converts its short code to uppercase
     * 3. Returns null if state cannot be found
     *
     * The returned uppercase code is used to ensure consistent
     * subdivision validation during address checks.
     *
     * @param string $countryStateId Shopware ID of the subdivision
     * @param Context $context Current Shopware context
     *
     * @return string|null Uppercase subdivision code or null if not found
     */
    public function fetchSubdivisionCodeByCountryStateId(string $countryStateId, Context $context): ?string
    {
        /** @var CountryStateEntity|null $state */
        $state = $this->countryStateRepository->search(new Criteria([$countryStateId]), $context)->first();

        if ($state === null) {
            return null;
        }

        // If a subdivision is found, get its ISO code and convert it to uppercase
        return strtoupper($state->getShortCode());
    }
}
