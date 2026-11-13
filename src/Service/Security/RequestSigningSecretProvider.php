<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Provides the per-sales-channel secret used for HMAC request signing (falls back to a
 * globally configured secret, if one exists, via the regular System Config inheritance).
 *
 * Deliberately NOT derived from Shopware's own `APP_SECRET`/`kernel.secret` - that
 * value backs many other security-critical mechanisms (sessions, CSRF, ...), and its
 * quality/rotation hygiene is outside this plugin's control. A dedicated secret can be
 * rotated independently without side effects on the rest of the shop.
 *
 * Generated lazily on first access via a cryptographically secure random generator,
 * so no separate installer/migration step is needed. Not exposed in config.xml - this
 * is an internal, non-merchant-editable value.
 */
final class RequestSigningSecretProvider implements RequestSigningSecretProviderInterface
{
    private const CONFIG_KEY = 'EnderecoShopware6Client.config.enderecoRequestSigningSecret';

    private const SECRET_BYTE_LENGTH = 32;

    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getSecret(?string $salesChannelId): string
    {
        $secret = $this->systemConfigService->getString(self::CONFIG_KEY, $salesChannelId);

        if ($secret === '') {
            $secret = bin2hex(random_bytes(self::SECRET_BYTE_LENGTH));
            $this->systemConfigService->set(self::CONFIG_KEY, $secret, $salesChannelId);
        }

        return $secret;
    }
}
