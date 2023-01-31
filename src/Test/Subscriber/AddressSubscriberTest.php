<?php

declare(strict_types=1);

namespace Endereco\Shopware6Client\Test\Subscriber;

use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\EnderecoAddressExtensionEntity;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Subscriber\AddressSubscriber;
use Endereco\Shopware6Client\Test\ConfigTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\Country\CountryEntity;

class AddressSubscriberTest extends TestCase
{
    use ConfigTrait;

    public function test_if_disabled_configuration_will_not_extend_validation()
    {
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(false),
            $this->createMock(EnderecoService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
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

    public function test_if_address_subscriber_will_extend_validation()
    {
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $this->createMock(EnderecoService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
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

    public function test_check_if_given_request_endereco_data_will_be_joined_into_street()
    {
        $enderecoServiceMock = $this->createConfiguredMock(EnderecoService::class, [
            'buildFullStreet' => 'Testing 44'
        ]);

        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
        );


        $dataMock = $this->createMock(RequestDataBag::class);
        $dataMock->method('get')->will(
            $this->onConsecutiveCalls(null, null, null, 'Testing', '44', 'some-country-uuid')
        );

        $dataMock->expects($this->once())->method('set')->with('street', 'Testing 44');
        $event = new BuildValidationEvent(
            $this->createMock(DataValidationDefinition::class),
            $dataMock,
            new Context(new SystemSource())
        );

        $addressSubscriber->onFormValidation($event);
    }

    public function test_if_address_subscriber_will_split_address_on_loaded_event()
    {
        $enderecoServiceMock = $this->createMock(EnderecoService::class);
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
        );

        $event = $this->createConfiguredMock(EntityLoadedEvent::class, [
            'getContext' => $this->createMock(Context::class),
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

    public function test_if_address_subscriber_will_not_split_address_on_loaded_event_when_they_are_same()
    {
        $enderecoServiceMock = $this->createConfiguredMock(EnderecoService::class, [
            'buildFullStreet' => 'Testing 66'
        ]);
        $addressSubscriber = new AddressSubscriber(
            $this->getSystemConfigService(),
            $enderecoServiceMock,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
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
