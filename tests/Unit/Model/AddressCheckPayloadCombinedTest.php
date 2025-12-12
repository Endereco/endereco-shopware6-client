<?php declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Model;

use Endereco\Shopware6Client\Model\AddressCheckPayloadCombined;
use Endereco\Shopware6Client\Model\AddressCheckPayloadInterface;
use PHPUnit\Framework\TestCase;

class AddressCheckPayloadCombinedTest extends TestCase
{
    /**
     * Tests that constructor properly sets all properties and they are accessible via data() method.
     */
    public function testConstructorSetsAllProperties(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            'Additional info'
        );

        $data = $payload->data();

        $this->assertSame('DE', $data['country']);
        $this->assertSame('12345', $data['postCode']);
        $this->assertSame('Berlin', $data['cityName']);
        $this->assertSame('Lindenstraße 2', $data['streetFull']);
        $this->assertSame('BE', $data['subdivisionCode']);
        $this->assertSame('Additional info', $data['additionalInfo']);
    }

    /**
     * Tests that null subdivision code and additional info are excluded from data array.
     */
    public function testDataArrayExcludesNullValues(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            null,
            null
        );

        $data = $payload->data();

        $this->assertArrayNotHasKey('subdivisionCode', $data);
        $this->assertArrayNotHasKey('additionalInfo', $data);
    }

    /**
     * Tests that empty string subdivision code and additional info are included in data array.
     */
    public function testDataArrayIncludesEmptyStringValues(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'US',
            '90210',
            'Beverly Hills',
            '123 Main Street',
            '',
            ''
        );

        $data = $payload->data();

        $this->assertArrayHasKey('subdivisionCode', $data);
        $this->assertSame('', $data['subdivisionCode']);
        $this->assertArrayHasKey('additionalInfo', $data);
        $this->assertSame('', $data['additionalInfo']);
    }

    /**
     * Tests that data array keys are sorted alphabetically for consistent signatures.
     */
    public function testDataArrayIsSorted(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            'Additional info'
        );

        $data = $payload->data();
        $keys = array_keys($data);
        $sortedKeys = $keys;
        sort($sortedKeys);

        $this->assertSame($sortedKeys, $keys);
    }

    /**
     * Tests that toJSON() produces valid JSON string with correct data.
     */
    public function testToJSONProducesValidJson(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            'Additional info'
        );

        $json = $payload->toJSON();
        
        // Check JSON is valid
        json_decode($json);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON should be valid');
        
        // Compare JSON structure
        $expectedJson = json_encode([
            'additionalInfo' => 'Additional info',
            'cityName' => 'Berlin',
            'country' => 'DE',
            'postCode' => '12345',
            'streetFull' => 'Lindenstraße 2',
            'subdivisionCode' => 'BE'
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        
        $this->assertJsonStringEqualsJsonString($expectedJson, $json);
    }

    /**
     * Tests that toJSON() properly handles Unicode characters without escaping.
     */
    public function testToJSONHandlesUnicodeCharacters(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'München',
            'Bürgerstraße 5',
            'BY',
            'Zusätzliche Info'
        );

        $json = $payload->toJSON();
        
        // Check JSON is valid
        json_decode($json);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON with Unicode should be valid');
        
        // Verify Unicode characters are not escaped
        $this->assertStringContainsString('München', $json);
        $this->assertStringContainsString('Bürgerstraße', $json);
        $this->assertStringContainsString('Zusätzliche Info', $json);
    }

    /**
     * Tests that getFormatType() returns 'combined' identifier.
     */
    public function testGetFormatTypeReturnsCombined(): void
    {
        $payload = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            null
        );

        $this->assertSame(AddressCheckPayloadInterface::FORMAT_COMBINED, $payload->getFormatType());
    }

    /**
     * Tests that identical address data produces consistent signatures for caching.
     */
    public function testConsistentSignatureForSameAddress(): void
    {
        $payload1 = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            'Additional info'
        );

        $payload2 = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            'Additional info'
        );

        $this->assertSame($payload1->toJSON(), $payload2->toJSON());
        $this->assertSame($payload1->data(), $payload2->data());
    }

    /**
     * Tests that different address data produces different signatures.
     */
    public function testDifferentSignatureForDifferentAddresses(): void
    {
        $payload1 = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            null
        );

        $payload2 = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 3',
            'BE',
            null
        );

        $this->assertNotEquals($payload1->toJSON(), $payload2->toJSON());
        $this->assertNotEquals($payload1->data(), $payload2->data());
    }

    /**
     * Tests that null vs empty string additional info produces different signatures.
     */
    public function testDifferentSignatureForNullVsEmptyAdditionalInfo(): void
    {
        $payloadWithNull = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            null
        );

        $payloadWithEmpty = new AddressCheckPayloadCombined(
            'DE',
            '12345',
            'Berlin',
            'Lindenstraße 2',
            'BE',
            ''
        );

        $this->assertNotEquals($payloadWithNull->toJSON(), $payloadWithEmpty->toJSON());
        $this->assertNotEquals($payloadWithNull->data(), $payloadWithEmpty->data());
        
        // Verify the null version doesn't include additionalInfo field
        $nullData = $payloadWithNull->data();
        $this->assertArrayNotHasKey('additionalInfo', $nullData);
        
        // Verify the empty string version includes additionalInfo field
        $emptyData = $payloadWithEmpty->data();
        $this->assertArrayHasKey('additionalInfo', $emptyData);
        $this->assertSame('', $emptyData['additionalInfo']);
    }
}
