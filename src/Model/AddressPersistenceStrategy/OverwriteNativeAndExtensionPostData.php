<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Model\CustomerAddressPersistenceStrategy;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Shopware\Core\Framework\Context;

final class OverwriteNativeAndExtensionPostData implements CustomerAddressPersistenceStrategy
{
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private Context $context;

    public function __construct(
        AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker,
        Context $context
    )
    {
        $this->additionalAddressFieldChecker = $additionalAddressFieldChecker;
        $this->context = $context;
    }

    public function execute(
        string $normalizedStreetFull,
        ?string $normalizedAdditionalInfo,
        string $streetName,
        string $buildingNumber,
        CustomerAddressDTO $customerAddressDTO
    ): void {
        $postData = &$customerAddressDTO->getPostData();

        $this->maybeUpdateNative(
            $normalizedStreetFull,
            $normalizedAdditionalInfo,
            $postData
        );

        $this->maybeUpdateExtension(
            $streetName,
            $buildingNumber,
            $postData
        );
    }

    /**
     * Updates the native address fields if values have changed
     *
     * @param string $streetFull Complete street address
     * @param string|null $additionalInfo Additional address information
     * @param array<string, mixed> &$postData The POST data containing address information (passed by reference)
     *
     * @return void
     */
    private function maybeUpdateNative(string $streetFull, ?string $additionalInfo, array &$postData): void
    {
        $postData['street'] = $streetFull;

        if ($this->additionalAddressFieldChecker->hasAdditionalAddressField($this->context)) {
            $fieldName = $this->additionalAddressFieldChecker->getAvailableAdditionalAddressFieldName($this->context);
            $postData[$fieldName] = $additionalInfo;
        }
    }

    /**
     * Updates the Endereco extension in the POST data with street name and house number
     *
     * @param string $streetName Street name component
     * @param string $buildingNumber Building/house number component
     * @param array<string, mixed> &$postData The POST data containing address information (passed by reference)
     *
     * @return void
     */
    private function maybeUpdateExtension(
        string $streetName,
        string $buildingNumber,
        array &$postData
    ): void {

        if (!isset($postData['extensions']) || !isset($postData['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION])) {
            // Initialize the extension structure if it doesn't exist
            if (!isset($postData['extensions'])) {
                $postData['extensions'] = [];
            }

            $postData['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION] = [];
        }

        $postData['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['street'] = $streetName;
        $postData['extensions'][CustomerAddressExtension::ENDERECO_EXTENSION]['houseNumber'] = $buildingNumber;
    }
}
