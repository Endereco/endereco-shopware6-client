<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

interface RequestSignatureFailureLoggerInterface
{
    /**
     * Records a failed request signature validation (and thus a rejected request).
     * Writes at most one log entry per sales channel within the throttle window;
     * failures in between are only counted and reported with the next entry.
     *
     * @param string|null $salesChannelId Sales channel the request was made in
     */
    public function logFailure(?string $salesChannelId): void;
}
