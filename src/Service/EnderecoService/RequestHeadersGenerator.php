<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\EnderecoService;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class RequestHeadersGenerator implements RequestHeadersGeneratorInterface
{
    private AgentInfoGeneratorInterface $agentInfoGenerator;

    private SystemConfigService $systemConfigService;

    public function __construct(
        AgentInfoGeneratorInterface $agentInfoGenerator,
        SystemConfigService $systemConfigService
    ) {
        $this->agentInfoGenerator = $agentInfoGenerator;
        $this->systemConfigService = $systemConfigService;
    }

    public function generateRequestHeaders(
        Context $context,
        ?string $salesChannelId,
        ?string $sessionId = 'not_required'
    ): array {
        $appName = $this->agentInfoGenerator->getAgentInfo($context);
        $apiKey = $this->systemConfigService
            ->getString('EnderecoShopware6Client.config.enderecoApiKey', $salesChannelId);

        return [
            'Content-Type' => 'application/json',
            'X-Auth-Key' => $apiKey,
            'X-Transaction-Id' => $sessionId,
            'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ?? __FILE__,
            'X-Agent' => $appName,
        ];
    }
}
