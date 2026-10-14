<?php declare(strict_types=1);

/*
 * This file is part of the Endereco Shopware 6 Client.
 *
 * (c) Endereco UG (haftungsbeschränkt)
 */

namespace Endereco\Shopware6Client\Tests\Unit\Model\AddressPersistenceStrategy;

use Endereco\Shopware6Client\DTO\CustomerAddressDTO;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Model\AddressPersistenceStrategy\PersistNativeAndExtensionFields;
use Endereco\Shopware6Client\Model\CustomerAddressField;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\CustomerAddressEntityUpdater;
use Endereco\Shopware6Client\Service\EnderecoExtensionEntityUpdater;
use Endereco\Shopware6Client\Service\PluginStatusService;
use Endereco\Shopware6Client\Tests\Unit\Entity\CustomerAddress\CreatesCustomerAddressTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Unit tests for PersistNativeAndExtensionFields
 *
 * These tests ensure that native address fields are only written when the persisted
 * value would actually change, e.g. NULL in the database and "" from the splitStreet API
 * must not trigger a write.
 */
class PersistNativeAndExtensionFieldsTest extends TestCase
{
    use CreatesCustomerAddressTrait;

    /**
     * @return iterable<string, array{string, ?string, string, ?string, bool}>
     */
    public static function provideComparisonCases(): iterable
    {
        // Values the DAL persists identically must not trigger a write
        yield 'null vs empty string' => ['Hauptstr. 1', null, 'Hauptstr. 1', '', false];
        yield 'null vs whitespace only' => ['Hauptstr. 1', null, 'Hauptstr. 1', '  ', false];
        yield 'identical values' => ['Hauptstr. 1', 'Hinterhaus', 'Hauptstr. 1', 'Hinterhaus', false];

        // Real changes must still be persisted
        yield 'additional info from API' => ['Hauptstr. 1 Hinterhaus', null, 'Hauptstr. 1', 'Hinterhaus', true];
        yield 'street split correction' => ['Hauptstr.1', null, 'Hauptstr. 1', '', true];
        yield 'surrounding whitespace is still cleaned up' => ['Hauptstr. 1 ', null, 'Hauptstr. 1', '', true];
        yield 'additional info changed' => ['Hauptstr. 1', 'Hinterhaus', 'Hauptstr. 1', 'Vorderhaus', true];
    }

    #[DataProvider('provideComparisonCases')]
    public function testWritesNativeFieldsOnlyOnRealChange(
        string $dbStreet,
        ?string $dbAdditionalInfo,
        string $newStreet,
        ?string $newAdditionalInfo,
        bool $expectWrite
    ): void {
        $checker = $this->createStub(AdditionalAddressFieldCheckerInterface::class);
        $checker->method('hasAdditionalAddressField')->willReturn(true);
        $checker->method('getAvailableAdditionalAddressFieldName')
            ->willReturn(CustomerAddressField::ADDITIONAL_ADDRESS_LINE_1);
        $checker->method('getAvailableAdditionalAddressFieldGetter')
            ->willReturn('getAdditionalAddressLine1');

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository->expects($expectWrite ? $this->once() : $this->never())->method('update');

        $address = $this->createCustomerAddress();
        $address->setCustomerId(Uuid::randomHex());
        // assign() because the entity setter doesn't accept NULL, although the DB value can be NULL
        $address->assign(['street' => $dbStreet, 'additionalAddressLine1' => $dbAdditionalInfo]);

        // Extension already matches the split result, so only native fields are relevant
        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($address);
        $extension->setStreet('Hauptstr.');
        $extension->setHouseNumber('1');

        $strategy = new PersistNativeAndExtensionFields(
            $checker,
            $addressRepository,
            $this->createStub(EntityRepository::class),
            Context::createDefaultContext(),
            new CustomerAddressEntityUpdater(),
            new EnderecoExtensionEntityUpdater(),
            new PluginStatusService([])
        );

        $postData = [];

        $strategy->execute(
            $newStreet,
            $newAdditionalInfo,
            'Hauptstr.',
            '1',
            new CustomerAddressDTO($address, $extension, $postData)
        );
    }
}
