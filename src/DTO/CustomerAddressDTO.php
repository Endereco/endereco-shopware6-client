<?php

namespace Endereco\Shopware6Client\DTO;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class CustomerAddressDTO
{
    /**
     * Constructor allows setting all properties at once
     *
     * @param CustomerAddressEntity|null $customerAddress
     * @param EnderecoCustomerAddressExtensionEntity|null $enderecoCustomerAddressExtension
     * @param array<string, mixed>|null $postData Reference to postData array
     */
    public function __construct(
        ?CustomerAddressEntity $customerAddress = null,
        ?EnderecoCustomerAddressExtensionEntity $enderecoCustomerAddressExtension = null,
        ?array &$postData = null
    ) {
        $this->customerAddress = $customerAddress;
        $this->enderecoCustomerAddressExtension = $enderecoCustomerAddressExtension;
        $this->postData = &$postData;
    }

    /**
     * Reference to CustomerAddress
     *
     * @var CustomerAddressEntity|null
     */
    private ?CustomerAddressEntity $customerAddress = null;

    /**
     * Reference to EnderecoCustomerAddressExtension
     *
     * @var EnderecoCustomerAddressExtensionEntity|null
     */
    private ?EnderecoCustomerAddressExtensionEntity $enderecoCustomerAddressExtension = null;

    /**
     * Reference to an array (Shopware 6 plugin context)
     *
     * @var array<string, mixed>|null
     */
    private ?array $postData = null;

    /**
     * Get CustomerAddress reference
     *
     * @return CustomerAddressEntity|null
     */
    public function getCustomerAddress(): ?CustomerAddressEntity
    {
        return $this->customerAddress;
    }

    /**
     * Set CustomerAddress reference
     *
     * @param CustomerAddressEntity|null $customerAddress
     * @return self
     */
    public function setCustomerAddress(?CustomerAddressEntity $customerAddress): self
    {
        $this->customerAddress = $customerAddress;
        return $this;
    }

    /**
     * Get EnderecoCustomerAddressExtension reference
     *
     * @return EnderecoCustomerAddressExtensionEntity|null
     */
    public function getEnderecoCustomerAddressExtension(): ?EnderecoCustomerAddressExtensionEntity
    {
        return $this->enderecoCustomerAddressExtension;
    }

    /**
     * Set EnderecoCustomerAddressExtension reference
     *
     * @param EnderecoCustomerAddressExtensionEntity|null $enderecoCustomerAddressExtension
     * @return self
     */
    public function setEnderecoCustomerAddressExtension(
        ?EnderecoCustomerAddressExtensionEntity $enderecoCustomerAddressExtension
    ): self {
        $this->enderecoCustomerAddressExtension = $enderecoCustomerAddressExtension;
        return $this;
    }

    /**
     * Get post data reference
     *
     * @return array<string, mixed>|null
     */
    public function &getPostData(): ?array
    {
        return $this->postData;
    }

    /**
     * Set post data reference
     *
     * @param array<string, mixed>|null $postData
     * @return self
     */
    public function setPostData(?array &$postData): self
    {
        $this->postData = &$postData;
        return $this;
    }
}
