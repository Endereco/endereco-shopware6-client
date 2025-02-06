<?php
declare(strict_types=1);

namespace Endereco\Shopware6Client\Service\AddressCheck;

use Shopware\Core\Framework\Context;

interface AdditionalAddressFieldCheckerInterface
{
    /**
     * Checks if any additional address field is available in the given context.
     *
     * @param Context $context The current Shopware context. If it has a getSalesChannelId() method,
     *                         its sales-channel configuration will be used.
     *
     * @return bool True if at least one additional field is enabled.
     */
    public function hasAdditionalAddressField(Context $context): bool;

    /**
     * Returns the name of the available additional address field.
     *
     * If additionalAddressLine1 is enabled it is returned; if not, but additionalAddressLine2 is enabled,
     * that name is returned; otherwise, null is returned.
     *
     * @param Context $context The current Shopware context.
     *
     * @return string The field name to use or empty string if none are enabled.
     */
    public function getAvailableAdditionalAddressFieldName(Context $context): string;
}
