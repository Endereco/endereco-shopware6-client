<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;

interface PluginVersionFetcherInterface
{
    /**
     * Retrieves the version of the 'EnderecoShopware6Client' plugin.
     *
     * This method constructs a criteria object and applies a filter to it to target the plugin
     * with the name 'EnderecoShopware6Client'.
     * This criteria object is then used to perform a search within the plugin repository.
     * The version of the first matching plugin is retrieved.
     *
     * The returned value is the version number (X.X.X) of the 'EnderecoShopware6Client' plugin.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string The version number of the 'EnderecoShopware6Client' plugin.
     */
    public function getPluginVersion(Context $context): string;
}
