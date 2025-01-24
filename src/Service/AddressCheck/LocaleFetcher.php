<?php

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * Implements locale code fetching from Shopware sales channels.
 *
 * This service retrieves 2-character locale codes from sales channel domains
 * for use in address validation API requests. If the locale fetch fails,
 * the address validation process will fall back to using 'de' as the default
 * locale.
 */
final class LocaleFetcher implements LocaleFetcherInterface
{
    /**
     * Repository for accessing sales channel domain entities
     */
    private EntityRepository $salesChannelDomainRepository;

    /**
     * Creates a new LocaleFetcher with required dependencies.
     *
     * @param EntityRepository $salesChannelDomainRepository Repository for sales channel domain access
     */
    public function __construct(
        EntityRepository $salesChannelDomainRepository
    ) {
        $this->salesChannelDomainRepository = $salesChannelDomainRepository;
    }

    /**
     * @inheritDoc
     *
     * Implementation details:
     * 1. Creates criteria with sales channel ID filter
     * 2. Adds language.locale association for accessing locale information
     * 3. Optionally filters by context language ID if available
     * 4. Retrieves and validates language and locale entities
     * 5. Returns first two characters of the locale code
     *
     * Note: If this method throws an exception, the address validation process
     * will fall back to using 'de' as the default locale code.
     *
     * @throws \RuntimeException When required entities or relationships are not found
     */
    public function fetchLocaleBySalesChannelId(string $salesChannelId, Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addAssociation('language.locale');

        if (!empty($context->getLanguageId())) {
            $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        }

        /** @var SalesChannelDomainEntity|null $salesChannelDomain */
        $salesChannelDomain = $this->salesChannelDomainRepository->search($criteria, $context)->first();

        if (!$salesChannelDomain) {
            throw new \RuntimeException(sprintf('Sales channel with id %s not found', $salesChannelId));
        }

        // Get the locale code from the sales channel
        $language = $salesChannelDomain->getLanguage();
        if ($language === null) {
            throw new \RuntimeException('Language entity is not available.');
        }

        $locale = $language->getLocale();
        if ($locale === null) {
            throw new \RuntimeException('Locale entity is not available.');
        }

        return substr($locale->getCode(), 0, 2);
    }
}
