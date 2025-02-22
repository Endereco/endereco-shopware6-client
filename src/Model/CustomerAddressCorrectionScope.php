<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model;

class CustomerAddressCorrectionScope
{
    private bool $allowNativeAddressFieldsOverwrite;
    private bool $isPayPalAddress;
    private bool $isAmazonPayAddress;

    public function __construct(
        bool $allowNativeAddressFieldsOverwrite,
        bool $isPayPalAddress,
        bool $isAmazonPayAddress
    ) {
        $this->allowNativeAddressFieldsOverwrite = $allowNativeAddressFieldsOverwrite;
        $this->isPayPalAddress = $isPayPalAddress;
        $this->isAmazonPayAddress = $isAmazonPayAddress;
    }

    public function canWriteNativeFields(): bool
    {
        if ($this->allowNativeAddressFieldsOverwrite === false) {
            return false;
        }

        if ($this->isAmazonPayAddress === true) {
            return false;
        }

        if ($this->isPayPalAddress === true) {
            return true;
        }

        return true;
    }

    public function canWriteExtensionFields(): bool
    {
        return true;
    }
}
