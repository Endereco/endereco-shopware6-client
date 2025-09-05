<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline\Operation;

use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;

interface Operation
{
    public static function getPriority(): int;

    /**
     * Determines if this operation should be applied to the given workspace
     *
     * @param Workspace $workspace
     * @return bool
     */
    public function applies(Workspace $workspace): bool;

    /**
     * @param Workspace $workspace
     */
    public function process(Workspace $workspace): void;
}