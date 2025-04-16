<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;

final class AgentInfoGenerator implements AgentInfoGeneratorInterface
{
    private PluginVersionFetcherInterface $pluginVersionFetcher;

    public function __construct(PluginVersionFetcherInterface $pluginVersionFetcher)
    {
        $this->pluginVersionFetcher = $pluginVersionFetcher;
    }

    public function getAgentInfo(Context $context): string
    {
        $versionTag = $this->pluginVersionFetcher->getPluginVersion($context);

        return sprintf(
            'Endereco Shopware6 Client (Download) v%s',
            $versionTag
        );
    }
}
