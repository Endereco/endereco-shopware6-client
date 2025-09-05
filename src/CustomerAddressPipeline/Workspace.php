<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\CustomerAddressPipeline;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * Workspace containing all data needed for customer address processing
 *
 * Decouples the pipeline from direct Shopware entity dependencies
 */
final class Workspace
{
    private ?CustomerAddressEntity $addressEntity = null;
    private ?EnderecoCustomerAddressExtensionEntity $extension = null;
    private ?Context $context = null;
    private ?Request $request = null;
    private bool $skip = false;

    public function getAddressEntity(): ?CustomerAddressEntity
    {
        return $this->addressEntity;
    }

    /**
     * Sets the customer address entity and automatically tries to extract the extension
     */
    public function setCustomerAddressEntity(CustomerAddressEntity $addressEntity): void
    {
        $this->addressEntity = $addressEntity;

        // Try to automatically extract the extension from the entity
        $extensionStruct = $addressEntity->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        $this->extension = $extensionStruct instanceof EnderecoCustomerAddressExtensionEntity ? $extensionStruct : null;
    }

    public function getExtension(): ?EnderecoCustomerAddressExtensionEntity
    {
        return $this->extension;
    }

    public function setExtension(?EnderecoCustomerAddressExtensionEntity $extension): void
    {
        $this->extension = $extension;
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Check if remaining operations should be skipped
     */
    public function shouldSkip(): bool
    {
        return $this->skip;
    }

    /**
     * Mark to skip remaining operations in the pipeline
     */
    public function skipRemaining(): void
    {
        $this->skip = true;
    }
}
