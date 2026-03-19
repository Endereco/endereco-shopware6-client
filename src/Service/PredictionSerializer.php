<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service;

/**
 * Converts address prediction arrays to/from a WAF-safe string format
 * for transport through HTML form fields.
 *
 * Encoding uses base64(json) so that city names containing parentheses
 * (e.g. "Halle (Saale)") do not trigger WAF rules that interpret
 * "word(word)" patterns as function calls.
 *
 * Decoding accepts both base64-encoded and plain JSON for backward compatibility.
 */
class PredictionSerializer
{
    /**
     * Encodes a predictions array into a WAF-safe string for use in HTML form fields.
     *
     * @param array<int, mixed> $predictions
     */
    public function encode(array $predictions): string
    {
        return base64_encode((string) json_encode($predictions));
    }

    /**
     * Decodes a predictions string from a form POST back into an array.
     * Accepts both plain JSON (legacy) and base64-encoded JSON.
     *
     * @return array<int, mixed>
     */
    public function decode(string $value): array
    {
        // Try plain JSON first. This also guards against double decoding: if the
        // value is already a decoded JSON array, it is returned immediately without
        // a redundant base64 pass.
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $base64Decoded = base64_decode($value, true);
        if ($base64Decoded !== false) {
            $decoded = json_decode($base64Decoded, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
