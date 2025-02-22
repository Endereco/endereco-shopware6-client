<?php
declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCorrection;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\CustomerAddressCorrectionScope;
use Endereco\Shopware6Client\Service\EnderecoService;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class AddressCorrectionScopeBuilder implements AddressCorrectionScopeBuilderInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    ) {
        $this->systemConfigService = $systemConfigService;
    }

    // TODO: use DTO instead of the extension
    public function buildCustomerAddressCorrectionScope(
        EnderecoCustomerAddressExtensionEntity $customerAddressExtensionEntity,
        Context $context
    ): CustomerAddressCorrectionScope {

        // TODO: refactor to a service in one of the future version. Don't use EnderecoService to prevent circular dep.
        $salesChannelId = null;
        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        $allowNativeAddressFieldsOverwrite = $this->systemConfigService->getBool(
            'EnderecoShopware6Client.config.enderecoAllowNativeAddressFieldsOverwrite',
            $salesChannelId
        );

        $isPayPalAddress = $customerAddressExtensionEntity->isPayPalAddress();
        $isAmazonPayAddress = $customerAddressExtensionEntity->isAmazonPayAddress();

        return new CustomerAddressCorrectionScope($allowNativeAddressFieldsOverwrite, $isPayPalAddress, $isAmazonPayAddress);
    }
}
