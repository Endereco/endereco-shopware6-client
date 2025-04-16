<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;

interface RequestHeadersGeneratorInterface
{
    /**
     * Generates headers for an API request.
     *
     * The headers include the 'Content-Type', 'X-Auth-Key', 'X-Transaction-Id', 'X-Transaction-Referer',
     * and 'X-Agent'. The 'X-Auth-Key' is retrieved from the system configuration service using the provided
     * sales channel ID. The 'X-Transaction-Id' is the provided session ID. The 'X-Transaction-Referer' is
     * retrieved from the server's HTTP_REFERER variable, defaulting to __FILE__ if it's not set. The 'X-Agent'
     * is retrieved using the provided context.
     *
     * @param Context $context The context.
     * @param ?string $salesChannelId The sales channel ID.
     * @param string|null $sessionId The session ID, defaulting to 'not_required'.
     *
     * @return array<string, string> The generated headers.
     */
    public function generateRequestHeaders(
        Context $context,
        ?string $salesChannelId,
        ?string $sessionId = 'not_required'
    ): array;
}
