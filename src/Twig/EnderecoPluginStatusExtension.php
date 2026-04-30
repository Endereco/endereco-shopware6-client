<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Twig;

use Endereco\Shopware6Client\Service\PluginStatusService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EnderecoPluginStatusExtension extends AbstractExtension
{
    private PluginStatusService $pluginStatusService;

    public function __construct(PluginStatusService $pluginStatusService)
    {
        $this->pluginStatusService = $pluginStatusService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'enderecoIsAcrisStreetActive',
                [
                    $this->pluginStatusService,
                    'isAcrisStreetActive'
                ]
            ),
        ];
    }
}
