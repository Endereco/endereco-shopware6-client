<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

interface RequestSignatureValidatorInterface
{
    /**
     * @param string $rawBody The raw, unparsed request body exactly as received (must be
     *                        the same bytes the client signed - never a re-encoded/
     *                        re-serialized version).
     * @param string|null $signature Value of the X-Signature header, if present.
     * @param string|null $timestamp Value of the X-Timestamp header, if present (Unix
     *                                timestamp in seconds, as a numeric string).
     * @param string|null $salesChannelId Sales channel the request was made in, used to
     *                                    look up its signing secret.
     */
    public function isValid(
        string $rawBody,
        ?string $signature,
        ?string $timestamp,
        ?string $salesChannelId
    ): bool;
}
