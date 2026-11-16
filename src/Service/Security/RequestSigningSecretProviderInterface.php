<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

interface RequestSigningSecretProviderInterface
{
    /**
     * Returns the sales channel's secret used to sign/verify requests to the Endereco
     * proxy endpoint. Generated on first access and persisted, so no separate
     * install/migration step is needed.
     *
     * @param string|null $salesChannelId Sales channel whose secret is returned (and
     *                                    generated, if it doesn't exist yet).
     */
    public function getSecret(?string $salesChannelId): string;
}
