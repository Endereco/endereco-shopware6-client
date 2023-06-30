<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Test\Service;

use Endereco\Shopware6Client\Service\EnderecoService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class EnderecoServiceTest extends TestCase
{
    /**
     * @dataProvider provideCountryIsoStreetOrderData
     */
    public function testIfBuildAddressWillReturnDifferentOrderOnDifferentIso(
        string $countryIso,
        string $street,
        string $houseNumber,
        string $expected
    ) {
        $enderecoService = new EnderecoService(
            $this->createMock(SystemConfigService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class)
        );
        $this->assertEquals($expected, $enderecoService->buildFullStreet($street, $houseNumber, $countryIso));
    }


    public function provideCountryIsoStreetOrderData(): array
    {
        return [
            [
                'DE',
                'Berliner Straße',
                '55',
                'Berliner Straße 55',
            ],
            [
                'AT',
                'Alp street',
                '10',
                'Alp street 10',
            ],
            [
                'PL',
                'Short street',
                '5',
                'Short street 5',
            ],
            [
                'FR',
                'Paris street',
                '9/12',
                '9/12 Paris street',
            ],
            [
                'DZ',
                'Long street',
                '8',
                '8 Long street',
            ],
            [
                'TZ',
                'Flower street',
                '7',
                '7 Flower street',
            ],
            [
                'some-undefined-country',
                'Testing street',
                '55',
                'Testing street 55',
            ],
        ];
    }
}
