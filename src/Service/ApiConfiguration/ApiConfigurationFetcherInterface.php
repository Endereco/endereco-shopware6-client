<?php

namespace Endereco\Shopware6Client\Service\ApiConfiguration;

use Endereco\Shopware6Client\DTO\ApiConfiguration;

interface ApiConfigurationFetcherInterface
{
    public function fetchConfiguration(?string $salesChannelId): ApiConfiguration;
}