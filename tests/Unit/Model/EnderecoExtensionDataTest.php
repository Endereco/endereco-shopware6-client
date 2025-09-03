<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Model;

use Endereco\Shopware6Client\Model\EnderecoExtensionData;
use Endereco\Shopware6Client\Model\EnderecoExtensionField;
use PHPUnit\Framework\TestCase;

class EnderecoExtensionDataTest extends TestCase
{
    private const LONG_TEXT_1000_CHARS = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem.';

    /**
     * Test 1: If I use setter, I find the same data in equivalent getter
     */
    public function testSettersAndGettersMatch(): void
    {
        $data = new EnderecoExtensionData();
        
        $data->setAddressId('addr-123')
            ->setStreet('Test Street')
            ->setHouseNumber('42A')
            ->setAmsStatus('correct')
            ->setAmsRequestPayload('{"test": "payload"}')
            ->setAmsTimestamp(1234567890)
            ->setAmsPredictions(['pred1', 'pred2'])
            ->setIsPayPalAddress(true)
            ->setIsAmazonPayAddress(false);
        
        $this->assertEquals('addr-123', $data->getAddressId());
        $this->assertEquals('Test Street', $data->getStreet());
        $this->assertEquals('42A', $data->getHouseNumber());
        $this->assertEquals('correct', $data->getAmsStatus());
        $this->assertEquals('{"test": "payload"}', $data->getAmsRequestPayload());
        $this->assertEquals(1234567890, $data->getAmsTimestamp());
        $this->assertEquals(['pred1', 'pred2'], $data->getAmsPredictions());
        $this->assertTrue($data->getIsPayPalAddress());
        $this->assertFalse($data->getIsAmazonPayAddress());
    }

    /**
     * Test 2: If I use setter, I find the same data in the toArray
     */
    public function testSettersStoreDataInToArray(): void
    {
        $data = new EnderecoExtensionData();
        
        $data->setAddressId('addr-123')
            ->setStreet('Test Street')
            ->setHouseNumber('42A')
            ->setAmsStatus('correct')
            ->setAmsRequestPayload('{"test": "payload"}')
            ->setAmsTimestamp(1234567890)
            ->setAmsPredictions(['pred1', 'pred2'])
            ->setIsPayPalAddress(true)
            ->setIsAmazonPayAddress(false);
        
        $array = $data->toArray();
        
        $this->assertEquals('addr-123', $array[EnderecoExtensionField::ADDRESS_ID]);
        $this->assertEquals('Test Street', $array[EnderecoExtensionField::STREET]);
        $this->assertEquals('42A', $array[EnderecoExtensionField::HOUSE_NUMBER]);
        $this->assertEquals('correct', $array[EnderecoExtensionField::AMS_STATUS]);
        $this->assertEquals('{"test": "payload"}', $array[EnderecoExtensionField::AMS_REQUEST_PAYLOAD]);
        $this->assertEquals(1234567890, $array[EnderecoExtensionField::AMS_TIMESTAMP]);
        $this->assertEquals(['pred1', 'pred2'], $array[EnderecoExtensionField::AMS_PREDICTIONS]);
        $this->assertTrue($array[EnderecoExtensionField::IS_PAYPAL_ADDRESS]);
        $this->assertFalse($array[EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS]);
    }

    /**
     * Test 3: If I don't use setter for a field, I don't find the field in toArray.
     * But if I set a field to null, it should appear in toArray.
     */
    public function testUnsetFieldsNotInToArray(): void
    {
        $data = new EnderecoExtensionData();
        
        // Set street and amsRequestPayload to null - these should appear in array
        $data->setStreet('Test Street')
            ->setAmsRequestPayload(null);
        
        $array = $data->toArray();
        
        // Should have street and amsRequestPayload (even though it's null)
        $this->assertArrayHasKey(EnderecoExtensionField::STREET, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::AMS_REQUEST_PAYLOAD, $array);
        $this->assertEquals('Test Street', $array[EnderecoExtensionField::STREET]);
        $this->assertNull($array[EnderecoExtensionField::AMS_REQUEST_PAYLOAD]);
        
        // Should not have unset fields (fields where setter was never called)
        $this->assertArrayNotHasKey(EnderecoExtensionField::ADDRESS_ID, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::HOUSE_NUMBER, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::AMS_STATUS, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::AMS_TIMESTAMP, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::AMS_PREDICTIONS, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::IS_PAYPAL_ADDRESS, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS, $array);
    }

    /**
     * Test 4: String normalization - street and houseNumber are truncated to 255 chars, 
     * amsStatus and amsRequestPayload are NOT truncated (LongTextField)
     */
    public function testStringNormalization(): void
    {
        $data = new EnderecoExtensionData();
        
        $data->setStreet(self::LONG_TEXT_1000_CHARS)
            ->setHouseNumber(self::LONG_TEXT_1000_CHARS)
            ->setAmsStatus(self::LONG_TEXT_1000_CHARS)
            ->setAmsRequestPayload(self::LONG_TEXT_1000_CHARS);
        
        // Test via getters - normalized fields should be ≤ 255 chars
        $this->assertLessThanOrEqual(255, mb_strlen($data->getStreet()));
        $this->assertLessThanOrEqual(255, mb_strlen($data->getHouseNumber()));
        
        // LongTextField fields should NOT be truncated - they should be longer than 255 chars
        $this->assertGreaterThan(255, mb_strlen($data->getAmsStatus()));
        $this->assertGreaterThan(255, mb_strlen($data->getAmsRequestPayload()));
        
        // Test via toArray - same behavior
        $array = $data->toArray();
        $this->assertLessThanOrEqual(255, mb_strlen($array[EnderecoExtensionField::STREET]));
        $this->assertLessThanOrEqual(255, mb_strlen($array[EnderecoExtensionField::HOUSE_NUMBER]));
        $this->assertGreaterThan(255, mb_strlen($array[EnderecoExtensionField::AMS_STATUS]));
        $this->assertGreaterThan(255, mb_strlen($array[EnderecoExtensionField::AMS_REQUEST_PAYLOAD]));
    }

    /**
     * Test 5: Null values are preserved
     */
    public function testNullValuesArePreserved(): void
    {
        $data = new EnderecoExtensionData();
        
        $data->setAddressId(null)
            ->setStreet(null)
            ->setHouseNumber(null)
            ->setAmsStatus(null)
            ->setAmsRequestPayload(null)
            ->setAmsTimestamp(null)
            ->setAmsPredictions(null)
            ->setIsPayPalAddress(null)
            ->setIsAmazonPayAddress(null);
        
        // Test via getters
        $this->assertNull($data->getAddressId());
        $this->assertNull($data->getStreet());
        $this->assertNull($data->getHouseNumber());
        $this->assertNull($data->getAmsStatus());
        $this->assertNull($data->getAmsRequestPayload());
        $this->assertNull($data->getAmsTimestamp());
        $this->assertNull($data->getAmsPredictions());
        $this->assertNull($data->getIsPayPalAddress());
        $this->assertNull($data->getIsAmazonPayAddress());
        
        // Test toArray - null fields should be present with null values
        $array = $data->toArray();
        $this->assertCount(9, $array); // All 9 fields should be present
        $this->assertNull($array[EnderecoExtensionField::ADDRESS_ID]);
        $this->assertNull($array[EnderecoExtensionField::STREET]);
        $this->assertNull($array[EnderecoExtensionField::HOUSE_NUMBER]);
        $this->assertNull($array[EnderecoExtensionField::AMS_STATUS]);
        $this->assertNull($array[EnderecoExtensionField::AMS_REQUEST_PAYLOAD]);
        $this->assertNull($array[EnderecoExtensionField::AMS_TIMESTAMP]);
        $this->assertNull($array[EnderecoExtensionField::AMS_PREDICTIONS]);
        $this->assertNull($array[EnderecoExtensionField::IS_PAYPAL_ADDRESS]);
        $this->assertNull($array[EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS]);
    }

    /**
     * Test 6: Data type handling - integers, booleans, arrays
     */
    public function testDataTypeHandling(): void
    {
        $data = new EnderecoExtensionData();
        
        // Test integer
        $data->setAmsTimestamp(1234567890);
        $this->assertIsInt($data->getAmsTimestamp());
        $this->assertEquals(1234567890, $data->getAmsTimestamp());
        
        // Test booleans
        $data->setIsPayPalAddress(true);
        $data->setIsAmazonPayAddress(false);
        $this->assertIsBool($data->getIsPayPalAddress());
        $this->assertIsBool($data->getIsAmazonPayAddress());
        $this->assertTrue($data->getIsPayPalAddress());
        $this->assertFalse($data->getIsAmazonPayAddress());
        
        // Test array
        $predictions = ['prediction1', 'prediction2', ['nested' => 'data']];
        $data->setAmsPredictions($predictions);
        $this->assertIsArray($data->getAmsPredictions());
        $this->assertEquals($predictions, $data->getAmsPredictions());
        
        // Test same via toArray
        $array = $data->toArray();
        $this->assertIsInt($array[EnderecoExtensionField::AMS_TIMESTAMP]);
        $this->assertIsBool($array[EnderecoExtensionField::IS_PAYPAL_ADDRESS]);
        $this->assertIsBool($array[EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS]);
        $this->assertIsArray($array[EnderecoExtensionField::AMS_PREDICTIONS]);
    }

    /**
     * Test 7: If text is truncated, beginning should persist, end can be truncated
     */
    public function testTruncationPreservesBeginning(): void
    {
        $data = new EnderecoExtensionData();
        
        $longText = 'BEGINNING_MARKER_' . str_repeat('X', 1000) . '_END_MARKER';
        
        $data->setStreet($longText)
            ->setHouseNumber($longText);
        
        // Normalized fields should start with beginning marker
        $this->assertStringStartsWith('BEGINNING_MARKER_', $data->getStreet());
        $this->assertStringStartsWith('BEGINNING_MARKER_', $data->getHouseNumber());
        
        // End marker should be truncated away (not present)
        $this->assertStringNotContainsString('_END_MARKER', $data->getStreet());
        $this->assertStringNotContainsString('_END_MARKER', $data->getHouseNumber());
        
        // Same tests via toArray
        $array = $data->toArray();
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[EnderecoExtensionField::STREET]);
        $this->assertStringStartsWith('BEGINNING_MARKER_', $array[EnderecoExtensionField::HOUSE_NUMBER]);
        
        $this->assertStringNotContainsString('_END_MARKER', $array[EnderecoExtensionField::STREET]);
        $this->assertStringNotContainsString('_END_MARKER', $array[EnderecoExtensionField::HOUSE_NUMBER]);
        
        // Non-normalized fields should preserve the entire text including end marker
        $data->setAmsStatus($longText)
            ->setAmsRequestPayload($longText);
        
        $this->assertStringContainsString('_END_MARKER', $data->getAmsStatus());
        $this->assertStringContainsString('_END_MARKER', $data->getAmsRequestPayload());
    }

    /**
     * Test 8: Fluent interface - all setters return self
     */
    public function testFluentInterface(): void
    {
        $data = new EnderecoExtensionData();
        
        $result = $data
            ->setAddressId('test')
            ->setStreet('Street')
            ->setHouseNumber('123')
            ->setAmsStatus('status')
            ->setAmsRequestPayload('payload')
            ->setAmsTimestamp(123456)
            ->setAmsPredictions(['pred'])
            ->setIsPayPalAddress(true)
            ->setIsAmazonPayAddress(false);
        
        // Should return same instance for fluent chaining
        $this->assertSame($data, $result);
    }

    /**
     * Test 9: Word boundary truncation for normalized string fields
     */
    public function testWordBoundaryTruncation(): void
    {
        $data = new EnderecoExtensionData();
        
        // Create a string with clear word boundaries near truncation point
        $longText = str_repeat('Word ', 60) . 'FinalWord'; // Should be > 255 chars
        $data->setStreet($longText);
        $result = $data->getStreet();
        
        // Should trim at word boundary when possible
        $this->assertLessThanOrEqual(255, mb_strlen($result));
        
        // Should end with a complete word (either "Word" or "FinalWord")
        $this->assertTrue(
            str_ends_with($result, 'Word') || str_ends_with($result, 'FinalWord'),
            "Result should end with complete word, got: " . substr($result, -10)
        );
    }

    /**
     * Test 10: Empty constructor creates empty object
     */
    public function testEmptyConstructor(): void
    {
        $data = new EnderecoExtensionData();
        
        // All getters should return null for new instance
        $this->assertNull($data->getAddressId());
        $this->assertNull($data->getStreet());
        $this->assertNull($data->getHouseNumber());
        $this->assertNull($data->getAmsStatus());
        $this->assertNull($data->getAmsRequestPayload());
        $this->assertNull($data->getAmsTimestamp());
        $this->assertNull($data->getAmsPredictions());
        $this->assertNull($data->getIsPayPalAddress());
        $this->assertNull($data->getIsAmazonPayAddress());
        
        // toArray should be empty (no setters called yet)
        $this->assertEmpty($data->toArray());
    }

    /**
     * Test 11: Complex scenario - mixed data types and partial setting
     */
    public function testComplexScenario(): void
    {
        $data = new EnderecoExtensionData();
        
        $data->setAddressId('complex-test-id')
            ->setStreet('Musterstraße')
            ->setHouseNumber('123A')
            ->setAmsStatus('address_correct,building_number_corrected')
            ->setAmsTimestamp(time())
            ->setAmsPredictions([
                ['streetName' => 'Musterstraße', 'buildingNumber' => '123'],
                ['streetName' => 'Musterstr.', 'buildingNumber' => '123A']
            ])
            ->setIsPayPalAddress(false);
            // Note: deliberately not setting amsRequestPayload and isAmazonPayAddress
        
        $array = $data->toArray();
        
        // Verify set fields are present
        $this->assertArrayHasKey(EnderecoExtensionField::ADDRESS_ID, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::STREET, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::HOUSE_NUMBER, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::AMS_STATUS, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::AMS_TIMESTAMP, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::AMS_PREDICTIONS, $array);
        $this->assertArrayHasKey(EnderecoExtensionField::IS_PAYPAL_ADDRESS, $array);
        
        // Verify unset fields are NOT present
        $this->assertArrayNotHasKey(EnderecoExtensionField::AMS_REQUEST_PAYLOAD, $array);
        $this->assertArrayNotHasKey(EnderecoExtensionField::IS_AMAZON_PAY_ADDRESS, $array);
        
        // Verify data integrity
        $this->assertEquals('complex-test-id', $array[EnderecoExtensionField::ADDRESS_ID]);
        $this->assertIsArray($array[EnderecoExtensionField::AMS_PREDICTIONS]);
        $this->assertCount(2, $array[EnderecoExtensionField::AMS_PREDICTIONS]);
        $this->assertFalse($array[EnderecoExtensionField::IS_PAYPAL_ADDRESS]);
    }
}