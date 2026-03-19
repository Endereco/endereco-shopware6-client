<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service;

use Endereco\Shopware6Client\Service\PredictionSerializer;
use PHPUnit\Framework\TestCase;

class PredictionSerializerTest extends TestCase
{
    private PredictionSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PredictionSerializer();
    }

    public function testEncodeProducesBase64OfJson(): void
    {
        $predictions = [['city' => 'Berlin', 'zipcode' => '10115']];
        $encoded = $this->serializer->encode($predictions);

        $this->assertSame(
            $predictions,
            json_decode(base64_decode($encoded, true), true)
        );
    }

    public function testEncodeEmptyArray(): void
    {
        $this->assertSame(
            base64_encode('[]'),
            $this->serializer->encode([])
        );
    }

    public function testDecodeBase64(): void
    {
        $predictions = [['city' => 'Halle (Saale)', 'zipcode' => '06108']];
        $encoded = base64_encode(json_encode($predictions));

        $this->assertSame($predictions, $this->serializer->decode($encoded));
    }

    public function testDecodePlainJson(): void
    {
        $predictions = [['city' => 'Berlin', 'zipcode' => '10115']];
        $json = json_encode($predictions);

        $this->assertSame($predictions, $this->serializer->decode($json));
    }

    public function testDecodeEmptyString(): void
    {
        $this->assertSame([], $this->serializer->decode(''));
    }

    public function testDecodeGarbage(): void
    {
        $this->assertSame([], $this->serializer->decode('not-json-and-not-base64!!!'));
    }

    public function testRoundTripWithParentheses(): void
    {
        $predictions = [
            ['city' => 'Halle (Saale)', 'zipcode' => '06108'],
            ['city' => 'Frankfurt (Oder)', 'zipcode' => '15230'],
        ];

        $encoded = $this->serializer->encode($predictions);
        $decoded = $this->serializer->decode($encoded);

        $this->assertSame($predictions, $decoded);
    }

    public function testRoundTripWithUtf8(): void
    {
        $predictions = [
            ['city' => 'München', 'zipcode' => '80331'],
            ['city' => 'Düsseldorf', 'zipcode' => '40210'],
            ['city' => 'Łódź', 'zipcode' => '90-001'],
        ];

        $encoded = $this->serializer->encode($predictions);
        $decoded = $this->serializer->decode($encoded);

        $this->assertSame($predictions, $decoded);
    }

    public function testDecodeBase64OfNonJsonReturnsEmptyArray(): void
    {
        $encoded = base64_encode('this is not json');

        $this->assertSame([], $this->serializer->decode($encoded));
    }

    public function testDecodeJsonScalarReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->serializer->decode('"just a string"'));
        $this->assertSame([], $this->serializer->decode('42'));
        $this->assertSame([], $this->serializer->decode('true'));
    }
}
