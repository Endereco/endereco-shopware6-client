<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

final class PayloadPreparator implements PayloadPreparatorInterface
{
    public function preparePayload(string $method, array $params = []): array
    {
        // Prepare the payload array.
        return [
            'jsonrpc' => '2.0',  // The JSON-RPC version.
            'id' => 1,           // A default ID.
            'method' => $method, // The name of the method for the API request.
            'params' => $params  // Any additional parameters for the API request.
        ];
    }
}
