<?php

namespace Endereco\Shopware6Client\Model;

/**
 * Represents an expected configuration value for Shopware's system configuration.
 *
 * This class encapsulates a configuration key and its expected value for validation
 * of system configuration settings. It's primarily used in conjunction with the
 * BySystemConfigFilter to validate sales channel configurations.
 *
 * The class handles various primitive types for configuration values and automatically
 * prefixes the config key with the proper namespace.
 *
 * Usage example:
 * ```php
 * $config = new ExpectedSystemConfigValue('feature.enabled', true);
 * $fullyQualifiedKey = $config->getFullyQualifiedConfigKey();
 *      // Returns 'EnderecoShopware6Client.config.feature.enabled'
 * ```
 *
 * @package Endereco\Shopware6Client\Model
 */
class ExpectedSystemConfigValue
{
    /**
     * The configuration key without namespace prefix
     *
     * @var string
     */
    private string $configKey;

    /**
     * The expected value for this configuration setting
     *
     * @var bool|string|int|float
     */
    private $expectedConfigValue;

    /**
     * Creates a new expected system configuration value.
     *
     * @param string $configKey The configuration key without namespace prefix
     * @param bool|string|int|float $expectedConfigValue The expected value for this configuration
     */
    public function __construct(string $configKey, $expectedConfigValue)
    {
        $this->configKey = $configKey;
        $this->expectedConfigValue = $expectedConfigValue;
    }

    /**
     * Gets the fully qualified configuration key including namespace prefix.
     *
     * Prepends the Endereco Shopware 6 Client namespace to the configuration key.
     *
     * @return string The fully qualified configuration key
     */
    public function getFullyQualifiedConfigKey(): string
    {
        return 'EnderecoShopware6Client.config.' . $this->configKey;
    }

    /**
     * Gets the expected value for this configuration setting.
     *
     * @return bool|float|int|string The expected configuration value
     */
    public function getExpectedConfigValue()
    {
        return $this->expectedConfigValue;
    }
}
