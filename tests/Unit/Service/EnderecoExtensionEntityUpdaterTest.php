<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service;

use Endereco\Shopware6Client\Service\EnderecoExtensionEntityUpdater;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use PHPUnit\Framework\TestCase;

class EnderecoExtensionEntityUpdaterTest extends TestCase
{
    private EnderecoExtensionEntityUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new EnderecoExtensionEntityUpdater();
    }

    /**
     * Basic functionality - extension fields from payload are applied to entity setters
     */
    public function testBasicExtensionFieldUpdates(): void
    {
        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Extension Street')
            ->setHouseNumber('123A')
            ->setAmsStatus('correct')
            ->setAmsRequestPayload('{"test": "payload"}')
            ->setAmsTimestamp(1234567890)
            ->setAmsPredictions(['pred1', 'pred2'])
            ->setIsPayPalAddress(true)
            ->setIsAmazonPayAddress(false);

        $extensionEntity = new EnderecoCustomerAddressExtensionEntity();
        
        $this->updater->updateFromPayload($extensionData, $extensionEntity);

        // Verify all fields were updated
        $this->assertEquals('Extension Street', $extensionEntity->getStreet());
        $this->assertEquals('123A', $extensionEntity->getHouseNumber());
        $this->assertEquals('correct', $extensionEntity->getAmsStatus());
        $this->assertEquals('{"test": "payload"}', $extensionEntity->getAmsRequestPayload());
        $this->assertEquals(1234567890, $extensionEntity->getAmsTimestamp());
        $this->assertEquals(['pred1', 'pred2'], $extensionEntity->getAmsPredictions());
        $this->assertTrue($extensionEntity->isPayPalAddress());
        $this->assertFalse($extensionEntity->isAmazonPayAddress());
    }

    /**
     * Selective updates - only fields present in extension payload are updated
     */
    public function testSelectiveExtensionUpdates(): void
    {
        $extensionEntity = new EnderecoCustomerAddressExtensionEntity();
        
        // Pre-populate entity with existing data
        $extensionEntity->setStreet('Original Street');
        $extensionEntity->setHouseNumber('Original House');
        $extensionEntity->setAmsStatus('Original Status');

        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Updated Street')
            ->setHouseNumber('Updated House');
        // Note: amsStatus is not set in payload

        $this->updater->updateFromPayload($extensionData, $extensionEntity);

        // Updated fields should have new values
        $this->assertEquals('Updated Street', $extensionEntity->getStreet());
        $this->assertEquals('Updated House', $extensionEntity->getHouseNumber());
        
        // Non-updated field should retain original value
        $this->assertEquals('Original Status', $extensionEntity->getAmsStatus());
    }

    /**
     * All extension field mappings work correctly
     */
    public function testAllExtensionFieldMappings(): void
    {
        $testCases = [
            [EnderecoExtensionField::STREET, 'setStreet', 'Test Street', 'getStreet'],
            [EnderecoExtensionField::HOUSE_NUMBER, 'setHouseNumber', '42A', 'getHouseNumber'],
            [EnderecoExtensionField::AMS_STATUS, 'setAmsStatus', 'correct', 'getAmsStatus'],
            [EnderecoExtensionField::AMS_REQUEST_PAYLOAD, 'setAmsRequestPayload', '{"test": "data"}', 'getAmsRequestPayload'],
            [EnderecoExtensionField::AMS_TIMESTAMP, 'setAmsTimestamp', 1234567890, 'getAmsTimestamp'],
            [EnderecoExtensionField::AMS_PREDICTIONS, 'setAmsPredictions', ['pred1', 'pred2'], 'getAmsPredictions'],
            [EnderecoExtensionField::IS_PAYPAL_ADDRESS, 'setIsPayPalAddress', true, 'isPayPalAddress'],
            [EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS, 'setIsAmazonPayAddress', false, 'isAmazonPayAddress'],
        ];

        foreach ($testCases as [$fieldConstant, $payloadSetter, $testValue, $entityGetter]) {
            $extensionData = new EnderecoExtensionData();
            $extensionEntity = new EnderecoCustomerAddressExtensionEntity();
            
            $extensionData->$payloadSetter($testValue);
            $this->updater->updateFromPayload($extensionData, $extensionEntity);
            
            $this->assertEquals($testValue, $extensionEntity->$entityGetter(), "Field mapping failed for {$fieldConstant}");
        }
    }

    /**
     * Empty extension payload behavior - no updates should occur
     */
    public function testEmptyExtensionPayloadBehavior(): void
    {
        $extensionEntity = new EnderecoCustomerAddressExtensionEntity();
        
        // Pre-populate entity
        $extensionEntity->setStreet('Original Street');
        $extensionEntity->setHouseNumber('Original House');

        $extensionData = new EnderecoExtensionData();
        // No setters called on extension payload

        $this->updater->updateFromPayload($extensionData, $extensionEntity);

        // Entity should remain unchanged
        $this->assertEquals('Original Street', $extensionEntity->getStreet());
        $this->assertEquals('Original House', $extensionEntity->getHouseNumber());
    }
}