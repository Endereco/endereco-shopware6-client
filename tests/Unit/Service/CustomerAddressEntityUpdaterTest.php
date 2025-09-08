<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service;

use Endereco\Shopware6Client\Service\CustomerAddressEntityUpdater;
use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use PHPUnit\Framework\TestCase;

class CustomerAddressEntityUpdaterTest extends TestCase
{
    private CustomerAddressEntityUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new CustomerAddressEntityUpdater();
    }

    /**
     * Basic functionality - fields from payload are applied to entity setters
     */
    public function testBasicFieldUpdates(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet('Test Street 123')
            ->setCity('Berlin')
            ->setZipcode('12345')
            ->setCountryId('country-456')
            ->setCountryStateId('state-789')
            ->setAdditionalAddressLine1('Building A')
            ->setAdditionalAddressLine2('Floor 2');

        $entity = new CustomerAddressEntity();
        
        $this->updater->updateFromPayload($payload, $entity);

        // Verify all fields were updated
        $this->assertEquals('Test Street 123', $entity->getStreet());
        $this->assertEquals('Berlin', $entity->getCity());
        $this->assertEquals('12345', $entity->getZipcode());
        $this->assertEquals('country-456', $entity->getCountryId());
        $this->assertEquals('state-789', $entity->getCountryStateId());
        $this->assertEquals('Building A', $entity->getAdditionalAddressLine1());
        $this->assertEquals('Floor 2', $entity->getAdditionalAddressLine2());
    }

    /**
     * Selective updates - only fields present in payload are updated
     */
    public function testSelectiveUpdates(): void
    {
        $entity = new CustomerAddressEntity();
        
        // Pre-populate entity with existing data
        $entity->setStreet('Original Street');
        $entity->setCity('Original City');
        $entity->setZipcode('00000');

        $payload = new CustomerAddressUpdatePayload('test-id');
        $payload->setStreet('Updated Street')
            ->setCity('Updated City');
        // Note: zipcode is not set in payload

        $this->updater->updateFromPayload($payload, $entity);

        // Updated fields should have new values
        $this->assertEquals('Updated Street', $entity->getStreet());
        $this->assertEquals('Updated City', $entity->getCity());
        
        // Non-updated field should retain original value
        $this->assertEquals('00000', $entity->getZipcode());
    }

    /**
     * All field mappings work correctly
     */
    public function testAllFieldMappings(): void
    {
        // Test each field individually
        $testCases = [
            [CustomerAddressField::STREET, 'setStreet', 'Test Street', 'getStreet'],
            [CustomerAddressField::ZIPCODE, 'setZipcode', '12345', 'getZipcode'],
            [CustomerAddressField::CITY, 'setCity', 'Berlin', 'getCity'],
            [CustomerAddressField::COUNTRY_ID, 'setCountryId', 'country-id', 'getCountryId'],
            [CustomerAddressField::COUNTRY_STATE_ID, 'setCountryStateId', 'state-id', 'getCountryStateId'],
            [CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1, 'setAdditionalAddressLine1', 'Line 1', 'getAdditionalAddressLine1'],
            [CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2, 'setAdditionalAddressLine2', 'Line 2', 'getAdditionalAddressLine2'],
        ];

        foreach ($testCases as [$fieldConstant, $payloadSetter, $testValue, $entityGetter]) {
            $payload = new CustomerAddressUpdatePayload('test-id');
            $entity = new CustomerAddressEntity();
            
            $payload->$payloadSetter($testValue);
            $this->updater->updateFromPayload($payload, $entity);
            
            $this->assertEquals($testValue, $entity->$entityGetter(), "Field mapping failed for {$fieldConstant}");
        }
    }

    /**
     * Empty payload behavior - no updates should occur
     */
    public function testEmptyPayloadBehavior(): void
    {
        $entity = new CustomerAddressEntity();
        
        // Pre-populate entity
        $entity->setStreet('Original Street');
        $entity->setCity('Original City');

        $payload = new CustomerAddressUpdatePayload('test-id');
        // No setters called on payload

        $this->updater->updateFromPayload($payload, $entity);

        // Entity should remain unchanged
        $this->assertEquals('Original Street', $entity->getStreet());
        $this->assertEquals('Original City', $entity->getCity());
    }
}