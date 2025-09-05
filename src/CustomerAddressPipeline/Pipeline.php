<?php

namespace Endereco\Shopware6Client\CustomerAddressPipeline;

use Endereco\Shopware6Client\CustomerAddressPipeline\Operation\Operation;
use Endereco\Shopware6Client\CustomerAddressPipeline\Workspace;

/**
 * Coordinates address integrity checks and data synchronization
 */
final class Pipeline implements PipelineInterface
{
    /** @var iterable<Operation> */
    private iterable $operations;

    /**
     * @param iterable<Operation> $operations
     */
    public function __construct(iterable $operations)
    {
        $this->operations = $operations;
    }

    /**
     * Runs integrity checks or syncs cached data
     *
     * @param Workspace $workspace
     */
    public function process(Workspace $workspace): void
    {
        foreach ($this->operations as $operation) {
            if ($operation->applies($workspace)) {
                $operation->process($workspace);
            }
        }
    }
}
