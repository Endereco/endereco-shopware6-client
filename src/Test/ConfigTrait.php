<?php

namespace Endereco\Shopware6Client\Test;

use Shopware\Core\System\SystemConfig\SystemConfigService;

trait ConfigTrait
{
    private function getSystemConfigService(bool $splittingEnabled = true)
    {
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $systemConfigServiceMock
            ->method('getBool')
            ->will(
                $this->onConsecutiveCalls(true, $splittingEnabled)
            );
        $systemConfigServiceMock
            ->method('get')
            ->will(
                $this->onConsecutiveCalls('test-api-key')
            );
        return $systemConfigServiceMock;
    }
}
