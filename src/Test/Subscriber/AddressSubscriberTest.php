<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Test\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Subscriber\AddressSubscriber;
use Endereco\Shopware6Client\Test\ConfigTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

class AddressSubscriberTest extends TestCase
{
    use ConfigTrait;

    public function testIfDisabledConfigurationWillNotExtendValidation()
    {
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(false),
            $this->createMock(EnderecoService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RequestStack::class)
        );

        $definitionMock = $this->createMock(DataValidationDefinition::class);
        $definitionMock->expects($this->never())->method('add');
        $event = new BuildValidationEvent(
            $definitionMock,
            $this->createMock(DataBag::class),
            new Context(new SystemSource())
        );

        $addressSubscriber->onFormValidation($event);
    }

    public function testIfAddressSubscriberWillExtendValidation()
    {
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $this->createMock(EnderecoService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RequestStack::class)
        );

        $definitionMock = $this->createMock(DataValidationDefinition::class);
        $definitionMock->expects($this->atLeast(1))->method('add');
        $event = new BuildValidationEvent(
            $definitionMock,
            $this->createMock(DataBag::class),
            new Context(new SystemSource())
        );

        $addressSubscriber->onFormValidation($event);
    }

    public function testCheckIfGivenRequestEnderecoDataWillBeJoinedIntoStreet()
    {
        $enderecoServiceMock = $this->createConfiguredMock(EnderecoService::class, [
            'buildFullStreet' => 'Testing 44'
        ]);

        $countryRepositoryMock = $this->createConfiguredMock(EntityRepository::class, [
            'search' => $this->createConfiguredMock(EntitySearchResult::class, [
                'first' => $this->createConfiguredMock(CountryEntity::class, ['getIso' => 'DE'])
            ])
        ]);

        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $countryRepositoryMock,
            $this->createMock(RequestStack::class)
        );


        $dataMock = $this->createMock(RequestDataBag::class);
        $dataMock->method('get')->will(
            $this->onConsecutiveCalls(null, null, null, 'Testing', '44', null, 'some-country-uuid')
        );

        $dataMock->expects($this->once())->method('set')->with('street', 'Testing 44');
        $event = new BuildValidationEvent(
            $this->createMock(DataValidationDefinition::class),
            $dataMock,
            new Context(new SystemSource())
        );

        $addressSubscriber->onFormValidation($event);
    }

    public function testIfAddressSubscriberWillSplitAddressOnLoadedEvent()
    {
        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $systemConfigServiceMock
            ->method('getBool')
            ->will(
                $this->onConsecutiveCalls(true, false, true, true)
            );
        $systemConfigServiceMock
            ->method('get')->willReturn('test-api-key');
        $enderecoServiceMock = $this->createMock(EnderecoService::class);
        $addressSubscriber = new AddressSubscriber(
            $systemConfigServiceMock,
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RequestStack::class)
        );

        $event = $this->createConfiguredMock(EntityLoadedEvent::class, [
            'getContext' => $this->createConfiguredMock(Context::class, [
                'getSource' => $this->createConfiguredMock(SalesChannelApiSource::class, [
                    'getSalesChannelId' => 'some-sales-channel-id'
                ])
            ]),
            'getEntities' => [
                $this->createConfiguredMock(CustomerAddressEntity::class, [
                    'getCountry' => $this->createConfiguredMock(CountryEntity::class, [
                        'getIso' => 'DE'
                    ]),
                    'getStreet' => 'Testing 44',
                    'getExtension' => $this->createConfiguredMock(EnderecoAddressExtensionEntity::class, [
                        'getStreet' => 'Testing',
                        'getHouseNumber' => '55'
                    ])
                ])
            ]
        ]);

        $enderecoServiceMock
            ->expects($this->atLeast(1))
            ->method('splitStreet')->willReturn(['Testing', '44']);

        $addressSubscriber->onAddressLoaded($event);
    }

    public function testIfAddressSubscriberWillNotSplitAddressOnLoadedEventWhenTheyAreSame()
    {
        $enderecoServiceMock = $this->createConfiguredMock(EnderecoService::class, [
            'buildFullStreet' => 'Testing 66'
        ]);
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(RequestStack::class)
        );

        $event = $this->createConfiguredMock(EntityLoadedEvent::class, [
            'getContext' => $this->createMock(Context::class),
            'getEntities' => [
                $this->createConfiguredMock(CustomerAddressEntity::class, [
                    'getCountry' => $this->createConfiguredMock(CountryEntity::class, [
                        'getIso' => 'DE'
                    ]),
                    'getStreet' => 'Testing 66',
                    'getExtension' => $this->createConfiguredMock(EnderecoAddressExtensionEntity::class, [
                        'getStreet' => 'Testing',
                        'getHouseNumber' => '66'
                    ])
                ])
            ]
        ]);

        $enderecoServiceMock
            ->expects($this->never())
            ->method('splitStreet');

        $addressSubscriber->onAddressLoaded($event);
    }
}
