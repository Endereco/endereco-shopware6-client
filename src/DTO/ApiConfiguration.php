<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\DTO;

final class ApiConfiguration
{
    public function __construct(
        public readonly string $url,
        public readonly string $accessKey,
    ) {
    }
}
