<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline;

use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;

/**
 * Processes customer addresses through validation, enrichment, and data loading
 */
interface PipelineInterface
{
    /**
     * Processes address through pipeline operations
     *
     * @param Workspace $workspace
     */
    public function process(Workspace $workspace): void;
}
