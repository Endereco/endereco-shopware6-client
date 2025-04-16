<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginEntity as Plugin;

final class PluginVersionFetcher implements PluginVersionFetcherInterface
{
    private EntityRepository $pluginRepository;

    public function __construct(EntityRepository $pluginRepository)
    {
        $this->pluginRepository = $pluginRepository;
    }

    public function getPluginVersion(Context $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'EnderecoShopware6Client'));

        /** @var Plugin|null $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();

        if ($plugin !== null) {
            $versionTag = $plugin->getVersion();
        } else {
            $versionTag = 'unknown';
        }

        return $versionTag;
    }
}
