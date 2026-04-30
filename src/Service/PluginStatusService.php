<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

class PluginStatusService
{
    /**
     * @var array<class-string, array{name: string, path: string, class: class-string}>
     */
    private array $activePlugins;

    /**
     * @param array<class-string, array{name: string, path: string, class: class-string}> $activePlugins
     */
    public function __construct(array $activePlugins)
    {
        $this->activePlugins = $activePlugins;
    }

    public function isAcrisStreetActive(): bool
    {
        $search = 'AcrisSeparateStreetCS';

        foreach (array_keys($this->activePlugins) as $key) {
            if (is_string($key) && str_contains($key, $search)) {
                return true;
            }
        }

        return false;
    }
}
