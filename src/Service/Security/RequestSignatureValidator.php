<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\Security;

/**
 * Verifies the HMAC-SHA256 signature the JS-SDK attaches to requests when it has been
 * given a signing secret.
 *
 * The SDK only ever signs; it never validates - validation/enforcement is a per-backend
 * decision. This class is Shopware's side of that decision. It only verifies: the
 * caller invokes it only while "enderecoEnforceRequestSignature" is enabled and
 * rejects the request if the signature is invalid or missing.
 */
final class RequestSignatureValidator implements RequestSignatureValidatorInterface
{
    private const REPLAY_WINDOW_SECONDS = 300;

    private RequestSigningSecretProviderInterface $secretProvider;

    public function __construct(RequestSigningSecretProviderInterface $secretProvider)
    {
        $this->secretProvider = $secretProvider;
    }

    public function isValid(
        string $rawBody,
        ?string $signature,
        ?string $timestamp,
        ?string $salesChannelId
    ): bool {
        if ($signature === null || $signature === '' || $timestamp === null || $timestamp === '') {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::REPLAY_WINDOW_SECONDS) {
            return false;
        }

        $secret = $this->secretProvider->getSecret($salesChannelId);

        // Message format must match the JS-SDK exactly: raw body + "|" + timestamp,
        // computed over the raw bytes actually received, never a re-encoded version.
        $expectedSignature = hash_hmac('sha256', $rawBody . '|' . $timestamp, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
