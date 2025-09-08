<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service;

use Endereco\Shopware6Client\Service\AddressAsArrayUpdater;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use PHPUnit\Framework\TestCase;

class AddressAsArrayUpdaterTest extends TestCase
{
    private AddressAsArrayUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new AddressAsArrayUpdater();
    }

    /**
     * Basic functionality - payload fields are correctly copied to array by reference
     */
    public function testBasicArrayUpdates(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet('Test Street 123')
            ->setCity('Berlin')
            ->setZipcode('12345')
            ->setCountryId('country-456')
            ->setCountryStateId('state-789')
            ->setAdditionalAddressLine1('Building A')
            ->setAdditionalAddressLine2('Floor 2');

        $addressData = [
            'existingField' => 'existingValue'
        ];
        
        $this->updater->updateFromPayload($payload, $addressData);

        // Verify all payload fields were added to array
        $this->assertEquals('Test Street 123', $addressData[CustomerAddressField::STREET]);
        $this->assertEquals('Berlin', $addressData[CustomerAddressField::CITY]);
        $this->assertEquals('12345', $addressData[CustomerAddressField::ZIPCODE]);
        $this->assertEquals('country-456', $addressData[CustomerAddressField::COUNTRY_ID]);
        $this->assertEquals('state-789', $addressData[CustomerAddressField::COUNTRY_STATE_ID]);
        $this->assertEquals('Building A', $addressData[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]);
        $this->assertEquals('Floor 2', $addressData[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]);
        
        // Existing data should be preserved
        $this->assertEquals('existingValue', $addressData['existingField']);
    }

    /**
     * Selective updates - only fields present in payload are updated in array
     */
    public function testSelectiveArrayUpdates(): void
    {
        $addressData = [
            CustomerAddressField::STREET => 'Original Street',
            CustomerAddressField::CITY => 'Original City',
            CustomerAddressField::ZIPCODE => '00000',
            'otherField' => 'should remain'
        ];

        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet('Updated Street')
            ->setCity('Updated City');
        // Note: zipcode is not set in payload

        $this->updater->updateFromPayload($payload, $addressData);

        // Updated fields should have new values
        $this->assertEquals('Updated Street', $addressData[CustomerAddressField::STREET]);
        $this->assertEquals('Updated City', $addressData[CustomerAddressField::CITY]);
        
        // Non-updated field should retain original value
        $this->assertEquals('00000', $addressData[CustomerAddressField::ZIPCODE]);
        
        // Other fields should remain unchanged
        $this->assertEquals('should remain', $addressData['otherField']);
    }

    /**
     * Null values from payload are properly applied to array
     */
    public function testNullValueHandling(): void
    {
        $addressData = [
            CustomerAddressField::STREET => 'Original Street',
            CustomerAddressField::CITY => 'Original City'
        ];

        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet(null);

        $this->updater->updateFromPayload($payload, $addressData);

        // Null value should be applied
        $this->assertNull($addressData[CustomerAddressField::STREET]);
    }

    /**
     * Only native Shopware fields are updated (no extension fields)
     */
    public function testNativeFieldsOnly(): void
    {
        $addressData = [
            'extensions' => [
                'endereco_extension' => [
                    'street' => 'Extension Street'
                ]
            ]
        ];

        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet('Native Street');

        $this->updater->updateFromPayload($payload, $addressData);

        // Native field should be updated
        $this->assertEquals('Native Street', $addressData[CustomerAddressField::STREET]);
        
        // Extension data should remain untouched
        $this->assertEquals('Extension Street', $addressData['extensions']['endereco_extension']['street']);
    }

    /**
     * All supported field mappings work correctly
     */
    public function testAllFieldMappings(): void
    {
        $testCases = [
            [CustomerAddressField::STREET, 'setStreet', 'Test Street'],
            [CustomerAddressField::ZIPCODE, 'setZipcode', '12345'],
            [CustomerAddressField::CITY, 'setCity', 'Berlin'],
            [CustomerAddressField::COUNTRY_ID, 'setCountryId', 'country-id'],
            [CustomerAddressField::COUNTRY_STATE_ID, 'setCountryStateId', 'state-id'],
            [CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1, 'setAdditionalAddressLine1', 'Line 1'],
            [CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2, 'setAdditionalAddressLine2', 'Line 2'],
        ];

        foreach ($testCases as [$fieldConstant, $payloadSetter, $testValue]) {
            $payload = new CustomerAddressUpdatePayload('test-id');
            $addressData = [];
            
            $payload->$payloadSetter($testValue);
            $this->updater->updateFromPayload($payload, $addressData);
            
            $this->assertEquals($testValue, $addressData[$fieldConstant], "Field mapping failed for {$fieldConstant}");
        }
    }

    /**
     * Empty payload behavior - array structure and values remain unchanged
     */
    public function testEmptyPayloadBehavior(): void
    {
        $addressData = [
            'existing' => 'value',
            CustomerAddressField::STREET => 'original street',
            'customField' => 'custom value'
        ];

        $payload = new CustomerAddressUpdatePayload('test-id');
        // No setters called on payload

        $this->updater->updateFromPayload($payload, $addressData);

        // Array should remain completely unchanged
        $this->assertEquals([
            'existing' => 'value',
            CustomerAddressField::STREET => 'original street',
            'customField' => 'custom value'
        ], $addressData);
        
        // Specifically verify no ID was added
        $this->assertArrayNotHasKey(CustomerAddressField::ID, $addressData);
        
        // Verify count hasn't changed
        $this->assertCount(3, $addressData);
    }
}