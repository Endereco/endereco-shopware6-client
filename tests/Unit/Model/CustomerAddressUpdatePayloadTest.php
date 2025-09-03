<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Model;

use Endereco\Shopware6Client\Model\CustomerAddressUpdatePayload;
use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use PHPUnit\Framework\TestCase;

class CustomerAddressUpdatePayloadTest extends TestCase
{
    private const LONG_TEXT_1000_CHARS = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.';

    public function testConstructorSetsId(): void
    {
        $addressId = 'test-address-id';
        $payload = new CustomerAddressUpdatePayload($addressId);
        
        $this->assertEquals($addressId, $payload->getId());
        $this->assertEquals([CustomerAddressField::ID => $addressId], $payload->toArray());
    }

    /**
     * Test 1: If I use setter, I find the same data in the toArray
     */
    public function testSettersStoreDataInToArray(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $payload->setStreet('Test Street 123')
            ->setCity('Berlin')
            ->setZipcode('12345')
            ->setCountryId('country-456')
            ->setCountryStateId('state-123')
            ->setAdditionalAddressLine1('Building A')
            ->setAdditionalAddressLine2('Floor 2');
        
        $array = $payload->toArray();
        
        $this->assertEquals('Test Street 123', $array[CustomerAddressField::STREET]);
        $this->assertEquals('Berlin', $array[CustomerAddressField::CITY]);
        $this->assertEquals('12345', $array[CustomerAddressField::ZIPCODE]);
        $this->assertEquals('country-456', $array[CustomerAddressField::COUNTRY_ID]);
        $this->assertEquals('state-123', $array[CustomerAddressField::COUNTRY_STATE_ID]);
        $this->assertEquals('Building A', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]);
        $this->assertEquals('Floor 2', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]);
    }

    /**
     * Test 2: If I use setter, I find the same data in equivalent getter
     */
    public function testSettersAndGettersMatch(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $payload->setStreet('Test Street 123')
            ->setCity('Berlin')
            ->setZipcode('12345')
            ->setCountryId('country-456')
            ->setCountryStateId('state-123')
            ->setAdditionalAddressLine1('Building A')
            ->setAdditionalAddressLine2('Floor 2');
        
        $this->assertEquals('Test Street 123', $payload->getStreet());
        $this->assertEquals('Berlin', $payload->getCity());
        $this->assertEquals('12345', $payload->getZipcode());
        $this->assertEquals('country-456', $payload->getCountryId());
        $this->assertEquals('state-123', $payload->getCountryStateId());
        $this->assertEquals('Building A', $payload->getAdditionalAddressLine1());
        $this->assertEquals('Floor 2', $payload->getAdditionalAddressLine2());
    }

    /**
     * Test 3: If I don't use setter for a field, I don't find the field in toArray
     */
    public function testUnsetFieldsNotInToArray(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        // Only set street, leave others unset
        $payload->setStreet('Test Street');
        
        $array = $payload->toArray();
        
        // Should have ID and street
        $this->assertArrayHasKey(CustomerAddressField::ID, $array);
        $this->assertArrayHasKey(CustomerAddressField::STREET, $array);
        
        // Should not have unset fields
        $this->assertArrayNotHasKey(CustomerAddressField::CITY, $array);
        $this->assertArrayNotHasKey(CustomerAddressField::ZIPCODE, $array);
        $this->assertArrayNotHasKey(CustomerAddressField::COUNTRY_ID, $array);
        $this->assertArrayNotHasKey(CustomerAddressField::COUNTRY_STATE_ID, $array);
        $this->assertArrayNotHasKey(CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1, $array);
        $this->assertArrayNotHasKey(CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2, $array);
    }

    /**
     * Test 4: Long text truncation - zipcode 50 chars, city 70 chars, rest 255 chars
     */
    public function testLongTextTruncation(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $payload->setZipcode(self::LONG_TEXT_1000_CHARS)
            ->setCity(self::LONG_TEXT_1000_CHARS)
            ->setStreet(self::LONG_TEXT_1000_CHARS)
            ->setAdditionalAddressLine1(self::LONG_TEXT_1000_CHARS)
            ->setAdditionalAddressLine2(self::LONG_TEXT_1000_CHARS);
        
        // Test via getters
        $this->assertLessThanOrEqual(50, mb_strlen($payload->getZipcode()));
        $this->assertLessThanOrEqual(70, mb_strlen($payload->getCity()));
        $this->assertLessThanOrEqual(255, mb_strlen($payload->getStreet()));
        $this->assertLessThanOrEqual(255, mb_strlen($payload->getAdditionalAddressLine1()));
        $this->assertLessThanOrEqual(255, mb_strlen($payload->getAdditionalAddressLine2()));
        
        // Test via toArray
        $array = $payload->toArray();
        $this->assertLessThanOrEqual(50, mb_strlen($array[CustomerAddressField::ZIPCODE]));
        $this->assertLessThanOrEqual(70, mb_strlen($array[CustomerAddressField::CITY]));
        $this->assertLessThanOrEqual(255, mb_strlen($array[CustomerAddressField::STREET]));
        $this->assertLessThanOrEqual(255, mb_strlen($array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]));
        $this->assertLessThanOrEqual(255, mb_strlen($array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]));
    }

    /**
     * Test 5: If I assign EnderecoExtensionData object via setter, then I receive EnderecoExtensionData via extension getter
     */
    public function testEnderecoExtensionDataPreservedAsObject(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Extension Street')
            ->setHouseNumber('42A')
            ->setAmsStatus('correct')
            ->setAmsTimestamp(1234567890)
            ->setAmsPredictions(['pred1', 'pred2']);
        
        $payload->setEnderecoExtension($extensionData);
        
        // We should get the same object back
        $retrievedExtension = $payload->getEnderecoExtension();
        $this->assertInstanceOf(EnderecoExtensionData::class, $retrievedExtension);
        
        // Verify the data is correct
        $this->assertEquals('Extension Street', $retrievedExtension->getStreet());
        $this->assertEquals('42A', $retrievedExtension->getHouseNumber());
        $this->assertEquals('correct', $retrievedExtension->getAmsStatus());
        $this->assertEquals(1234567890, $retrievedExtension->getAmsTimestamp());
        $this->assertEquals(['pred1', 'pred2'], $retrievedExtension->getAmsPredictions());
    }

    /**
     * Test 6: If I assign EnderecoExtensionData via setter, toArray returns both customer address and extension data
     */
    public function testEnderecoExtensionInToArray(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $payload->setStreet('Main Street 123');
        
        $extensionData = new EnderecoExtensionData();
        $extensionData->setStreet('Extension Street')
            ->setHouseNumber('42A')
            ->setAmsStatus('correct')
            ->setAmsTimestamp(1234567890);
        
        $payload->setEnderecoExtension($extensionData);
        
        $array = $payload->toArray();
        
        // Should have customer address data
        $this->assertEquals('test-id', $array[CustomerAddressField::ID]);
        $this->assertEquals('Main Street 123', $array[CustomerAddressField::STREET]);
        
        // Should have extension data as nested array
        $this->assertArrayHasKey(CustomerAddressField::EXTENSIONS, $array);
        $this->assertArrayHasKey(CustomerAddressExtension::ENDERECO_EXTENSION, $array[CustomerAddressField::EXTENSIONS]);
        
        $extensionArray = $array[CustomerAddressField::EXTENSIONS][CustomerAddressExtension::ENDERECO_EXTENSION];
        $this->assertEquals('Extension Street', $extensionArray['street']);
        $this->assertEquals('42A', $extensionArray['houseNumber']);
        $this->assertEquals('correct', $extensionArray['amsStatus']);
        $this->assertEquals(1234567890, $extensionArray['amsTimestamp']);
    }

    /**
     * Test 7: Null values are preserved
     */
    public function testNullValuesArePreserved(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $payload->setStreet(null)
            ->setCity(null)
            ->setZipcode(null)
            ->setCountryId(null)
            ->setCountryStateId(null)
            ->setAdditionalAddressLine1(null)
            ->setAdditionalAddressLine2(null);
        
        // Test via getters
        $this->assertNull($payload->getStreet());
        $this->assertNull($payload->getCity());
        $this->assertNull($payload->getZipcode());
        $this->assertNull($payload->getCountryId());
        $this->assertNull($payload->getCountryStateId());
        $this->assertNull($payload->getAdditionalAddressLine1());
        $this->assertNull($payload->getAdditionalAddressLine2());
        
        // Test via toArray
        $array = $payload->toArray();
        $this->assertNull($array[CustomerAddressField::STREET]);
        $this->assertNull($array[CustomerAddressField::CITY]);
        $this->assertNull($array[CustomerAddressField::ZIPCODE]);
        $this->assertNull($array[CustomerAddressField::COUNTRY_ID]);
        $this->assertNull($array[CustomerAddressField::COUNTRY_STATE_ID]);
        $this->assertNull($array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]);
        $this->assertNull($array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]);
    }

    /**
     * Test 8: If text is truncated, beginning should persist, end can be truncated
     */
    public function testTruncationPreservesBeginning(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $longText = 'BEGINNING_MARKER_' . str_repeat('X', 1000) . '_END_MARKER';
        
        $payload->setStreet($longText)
            ->setCity($longText)
            ->setZipcode($longText)
            ->setAdditionalAddressLine1($longText)
            ->setAdditionalAddressLine2($longText);
        
        // All truncated texts should start with the beginning marker
        $this->assertStringStartsWith('BEGINNING_MARKER_', $payload->getStreet());
        $this->assertStringStartsWith('BEGINNING_MARKER_', $payload->getCity());
        $this->assertStringStartsWith('BEGINNING_MARKER_', $payload->getZipcode());
        $this->assertStringStartsWith('BEGINNING_MARKER_', $payload->getAdditionalAddressLine1());
        $this->assertStringStartsWith('BEGINNING_MARKER_', $payload->getAdditionalAddressLine2());
        
        // End marker should be truncated away (not present)
        $this->assertStringNotContainsString('_END_MARKER', $payload->getStreet());
        $this->assertStringNotContainsString('_END_MARKER', $payload->getCity());
        $this->assertStringNotContainsString('_END_MARKER', $payload->getZipcode());
        $this->assertStringNotContainsString('_END_MARKER', $payload->getAdditionalAddressLine1());
        $this->assertStringNotContainsString('_END_MARKER', $payload->getAdditionalAddressLine2());
        
        // Same tests via toArray
        $array = $payload->toArray();
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[CustomerAddressField::STREET]);
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[CustomerAddressField::CITY]);
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[CustomerAddressField::ZIPCODE]);
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]);
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]);
        
        $this->assertStringNotContainsString('_END_MARKER', $array[CustomerAddressField::STREET]);
        $this->assertStringNotContainsString('_END_MARKER', $array[CustomerAddressField::CITY]);
        $this->assertStringNotContainsString('_END_MARKER', $array[CustomerAddressField::ZIPCODE]);
        $this->assertStringNotContainsString('_END_MARKER', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1]);
        $this->assertStringNotContainsString('_END_MARKER', $array[CustomerAddressField::ADDITIONAL_ADDRESS_LINE_2]);
    }

    public function testFluentInterface(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        $result = $payload
            ->setStreet('Test Street')
            ->setCity('Berlin')
            ->setZipcode('12345')
            ->setCountryId('country-123')
            ->setCountryStateId('state-456');
        
        // Should return same instance for fluent chaining
        $this->assertSame($payload, $result);
    }

    public function testWordBoundaryTruncation(): void
    {
        $payload = new CustomerAddressUpdatePayload('test-id');
        
        // Create a string with clear word boundaries near truncation point
        $street = str_repeat('Word ', 60) . 'FinalWord'; // Should be > 255 chars
        $payload->setStreet($street);
        $result = $payload->getStreet();
        
        // Should trim at word boundary when possible
        $this->assertLessThanOrEqual(255, mb_strlen($result));
        
        // Should end with a complete word (either "Word" or "FinalWord")
        $this->assertTrue(
            str_ends_with($result, 'Word') || str_ends_with($result, 'FinalWord'),
            "Result should end with complete word, got: " . substr($result, -10)
        );
    }
}