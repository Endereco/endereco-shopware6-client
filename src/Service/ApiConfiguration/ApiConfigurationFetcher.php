<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\ApiConfiguration;

use Endereco\Shopware6Client\DTO\ApiConfiguration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Service for fetching Endereco API configuration from Shopware system configuration.
 * 
 * This service retrieves the API URL and access key from the plugin's system
 * configuration, supporting per-sales-channel configuration for multi-store setups.
 * 
 * CONFIGURATION SCOPE: Supports both global and sales-channel-specific settings,
 * allowing different Endereco API credentials per sales channel if needed.
 */
final class ApiConfigurationFetcher implements ApiConfigurationFetcherInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    ) {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Fetches API configuration for the specified sales channel.
     * 
     * Retrieves the Endereco API URL and access key from system configuration.
     * If no sales channel ID is provided, returns the global configuration.
     * 
     * @param string|null $salesChannelId The sales channel ID for channel-specific config, null for global
     * @return ApiConfiguration The API configuration containing URL and access key
     */
    public function fetchConfiguration(?string $salesChannelId): ApiConfiguration
    {
        $apiKey = $this->systemConfigService->getString('EnderecoShopware6Client.config.enderecoApiKey', $salesChannelId);
        $apiUrl = $this->systemConfigService->getString('EnderecoShopware6Client.config.enderecoRemoteUrl', $salesChannelId);

        return new ApiConfiguration($apiUrl, $apiKey);
    }
}