<?php
declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AdditionalAddressFieldChecker implements AdditionalAddressFieldCheckerInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param SystemConfigService $systemConfigService Injected SystemConfigService to retrieve config values.
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAdditionalAddressField(Context $context): bool
    {
        $salesChannelId = method_exists($context, 'getSalesChannelId')
            ? $context->getSalesChannelId()
            : null;

        $field1 = (bool) $this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField1', $salesChannelId);
        $field2 = (bool) $this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField2', $salesChannelId);

        return $field1 || $field2;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableAdditionalAddressFieldName(Context $context): string
    {
        $salesChannelId = method_exists($context, 'getSalesChannelId')
            ? $context->getSalesChannelId()
            : null;

        $field1 = (bool) $this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField1', $salesChannelId);
        $field2 = (bool) $this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField2', $salesChannelId);

        if ($field1) {
            return 'additionalAddressLine1';
        } elseif ($field2) {
            return 'additionalAddressLine2';
        }

        return '';
    }
}
