<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

interface PayloadPreparatorInterface
{
    /**
     * Prepares a payload for an API request in the JSON-RPC 2.0 format.
     *
     * This method prepares a payload array containing the JSON-RPC version, a default ID, the request method,
     * and an optional params array. The params array can be used to include any additional data that the API request
     * might require.
     *
     * @param string $method The name of the method for the API request.
     * @param array<string, string> $params Additional parameters to include in the API request (optional).
     *
     * @return array<string, string|int|array<string, string>> The prepared payload for the API request.
     */
    public function preparePayload(string $method, array $params = []): array;
}