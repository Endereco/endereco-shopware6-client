<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service;

use Endereco\Shopware6Client\Service\AddressExtensionAsArrayUpdater;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use PHPUnit\Framework\TestCase;

class AddressExtensionAsArrayUpdaterTest extends TestCase
{
    private AddressExtensionAsArrayUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new AddressExtensionAsArrayUpdater();
    }

    /**
     * Basic functionality - extension payload fields are correctly applied to nested array structure
     */
    public function testBasicExtensionArrayUpdates(): void
    {
        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Extension Street')
            ->setHouseNumber('123A')
            ->setAmsStatus('correct')
            ->setAmsPredictions(['pred1', 'pred2'])
            ->setAmsTimestamp(1234567890);

        $addressData = [
            'street' => 'Native Street',
            'city' => 'Berlin'
        ];
        
        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        // Verify nested extension structure was created
        $this->assertArrayHasKey(CustomerAddressField::EXTENSIONS, $addressData);
        $this->assertArrayHasKey(CustomerAddressExtension::ENDERECO_EXTENSION, $addressData[CustomerAddressField::EXTENSIONS]);
        
        $extension = $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION];
        $this->assertEquals('Extension Street', $extension[EnderecoExtensionField::STREET]);
        $this->assertEquals('123A', $extension[EnderecoExtensionField::HOUSE_NUMBER]);
        $this->assertEquals('correct', $extension[EnderecoExtensionField::AMS_STATUS]);
        $this->assertEquals(['pred1', 'pred2'], $extension[EnderecoExtensionField::AMS_PREDICTIONS]);
        $this->assertEquals(1234567890, $extension[EnderecoExtensionField::AMS_TIMESTAMP]);
        
        // Native fields should be preserved
        $this->assertEquals('Native Street', $addressData['street']);
        $this->assertEquals('Berlin', $addressData['city']);
    }

    /**
     * Extension structure creation - creates nested structure if it doesn't exist
     */
    public function testExtensionStructureCreation(): void
    {
        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Test Street');

        $addressData = []; // Empty array
        
        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        // Extension structure should be created
        $this->assertArrayHasKey(CustomerAddressField::EXTENSIONS, $addressData);
        $this->assertArrayHasKey(CustomerAddressExtension::ENDERECO_EXTENSION, $addressData[CustomerAddressField::EXTENSIONS]);
        $this->assertEquals('Test Street', $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION][EnderecoExtensionField::STREET]);
    }

    /**
     * Selective updates - only extension payload fields are updated
     */
    public function testSelectiveExtensionUpdates(): void
    {
        $addressData = [
            CustomerAddressField::EXTENSIONS => [
                CustomerAddressExtension::ENDERECO_EXTENSION => [
                    EnderecoExtensionField::STREET => 'Original Street',
                    EnderecoExtensionField::HOUSE_NUMBER => 'Original House',
                    EnderecoExtensionField::AMS_STATUS => 'Original Status'
                ]
            ]
        ];

        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Updated Street')
            ->setHouseNumber('Updated House');
        // Note: amsStatus is not set in payload

        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        $extension = $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION];
        
        // Updated fields should have new values
        $this->assertEquals('Updated Street', $extension[EnderecoExtensionField::STREET]);
        $this->assertEquals('Updated House', $extension[EnderecoExtensionField::HOUSE_NUMBER]);
        
        // Non-updated field should retain original value
        $this->assertEquals('Original Status', $extension[EnderecoExtensionField::AMS_STATUS]);
    }

    /**
     * Empty extension payload handling - early return
     */
    public function testEmptyExtensionPayloadHandling(): void
    {
        $addressData = [
            'existing' => 'value'
        ];

        $extensionData = new EnderecoExtensionData();
        // No setters called on extension payload

        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        // Nothing should be added - early return for empty payload
        $this->assertEquals(['existing' => 'value'], $addressData);
        $this->assertArrayNotHasKey(CustomerAddressField::EXTENSIONS, $addressData);
    }

    /**
     * Nested structure preservation - existing extension data is preserved when updating specific fields
     */
    public function testNestedStructurePreservation(): void
    {
        $addressData = [
            'nativeField' => 'native value',
            CustomerAddressField::EXTENSIONS => [
                'other_extension' => [
                    'data' => 'should be preserved'
                ],
                CustomerAddressExtension::ENDERECO_EXTENSION => [
                    EnderecoExtensionField::STREET => 'Original Street',
                    EnderecoExtensionField::HOUSE_NUMBER => 'Original House',
                    EnderecoExtensionField::AMS_STATUS => 'Original Status'
                ]
            ]
        ];

        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Updated Street');

        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        // Native field should be preserved
        $this->assertEquals('native value', $addressData['nativeField']);
        
        // Other extensions should be preserved
        $this->assertEquals('should be preserved', $addressData[CustomerAddressField::EXTENSIONS]['other_extension']['data']);
        
        $enderecoExtension = $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION];
        
        // Updated field
        $this->assertEquals('Updated Street', $enderecoExtension[EnderecoExtensionField::STREET]);
        
        // Existing extension fields should be preserved
        $this->assertEquals('Original House', $enderecoExtension[EnderecoExtensionField::HOUSE_NUMBER]);
        $this->assertEquals('Original Status', $enderecoExtension[EnderecoExtensionField::AMS_STATUS]);
    }


    /**
     * All supported extension field mappings
     */
    public function testAllExtensionFieldMappings(): void
    {
        $testCases = [
            [EnderecoExtensionField::STREET, 'setStreet', 'Test Street'],
            [EnderecoExtensionField::HOUSE_NUMBER, 'setHouseNumber', '42A'],
            [EnderecoExtensionField::AMS_STATUS, 'setAmsStatus', 'correct'],
            [EnderecoExtensionField::AMS_PREDICTIONS, 'setAmsPredictions', ['pred1', 'pred2']],
            [EnderecoExtensionField::AMS_TIMESTAMP, 'setAmsTimestamp', 1234567890],
        ];

        foreach ($testCases as [$fieldConstant, $payloadSetter, $testValue]) {
            $extensionData = new EnderecoExtensionData();
            $addressData = [];
            
            $extensionData->$payloadSetter($testValue);
            $this->updater->updateFromExtensionPayload($extensionData, $addressData);
            
            $actualValue = $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION][$fieldConstant];
            $this->assertEquals($testValue, $actualValue, "Field mapping failed for {$fieldConstant}");
        }
    }

    /**
     * Extension structure initialization with partial extensions
     */
    public function testExtensionStructureInitializationWithPartialExtensions(): void
    {
        $addressData = [
            CustomerAddressField::EXTENSIONS => [
                'other_extension' => ['existing' => 'data']
            ]
        ];

        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Test Street');

        $this->updater->updateFromExtensionPayload($extensionData, $addressData);

        // Should add endereco extension while preserving existing extensions
        $this->assertArrayHasKey('other_extension', $addressData[CustomerAddressField::EXTENSIONS]);
        $this->assertArrayHasKey(CustomerAddressExtension::ENDERECO_EXTENSION, $addressData[CustomerAddressField::EXTENSIONS]);
        $this->assertEquals('Test Street', $addressData[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION][EnderecoExtensionField::STREET]);
        $this->assertEquals(['existing' => 'data'], $addressData[CustomerAddressField::EXTENSIONS]['other_extension']);
    }
}