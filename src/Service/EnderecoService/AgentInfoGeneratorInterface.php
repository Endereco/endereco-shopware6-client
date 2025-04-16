<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;

interface AgentInfoGeneratorInterface
{
    /**
     * Fetches the version of the 'EnderecoShopware6Client' plugin and formats it along with the plugin name.
     *
     * This method calls the getPluginVersion method to fetch the version of the 'EnderecoShopware6Client' plugin.
     * The fetched version is then appended to the formatted plugin name string.
     *
     * The returned string is in the format 'Endereco Shopware6 Client (Download) vX.X.X',
     * where 'X.X.X' is the version number of the plugin.
     *
     * @param Context $context The context which includes details of the event triggering this method.
     *
     * @return string The formatted string containing the name and version of the plugin.
     */
    public function getAgentInfo(Context $context): string;
}
