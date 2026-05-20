<?php declare(strict_types=1);

namespace Endereco\Shopware6Client\Tests\Unit\Service\AddressCheck;

use Endereco\Shopware6Client\Model\AddressCheckPayloadCombined;
use Endereco\Shopware6Client\Model\AddressCheckPayloadInterface;
use Endereco\Shopware6Client\Model\AddressCheckPayloadSplit;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilder;
use Endereco\Shopware6Client\Service\AddressCheck\AddressCheckPayloadBuilderInterface;
use Endereco\Shopware6Client\Service\AddressCheck\AdditionalAddressFieldCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCheck\CountryHasStatesCheckerInterface;
use Endereco\Shopware6Client\Service\AddressCheck\SubdivisionCodeFetcherInterface;
use Endereco\Shopware6Client\Service\AddressCorrection\StreetSplitterInterface;
use Endereco\Shopware6Client\DTO\SplitStreetResultDto;
use Endereco\Shopware6Client\Service\EnderecoService;
use Endereco\Shopware6Client\Service\PluginStatusService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AddressCheckPayloadBuilderTest extends TestCase
{
    private CountryCodeFetcherInterface $countryCodeFetcher;
    private SubdivisionCodeFetcherInterface $subdivisionCodeFetcher;
    private CountryHasStatesCheckerInterface $countryHasStatesChecker;
    private AdditionalAddressFieldCheckerInterface $additionalAddressFieldChecker;
    private SystemConfigService $systemConfigService;
    private StreetSplitterInterface $streetSplitter;
    private EntityRepository $customerAddressExtensionRepository;
    private EntityRepository $orderAddressExtensionRepository;
    private EnderecoService $enderecoService;
    private PluginStatusService $pluginStatusService;
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * Creates a payload builder with mocked dependencies and optional configurations.
     * 
     * @param array<string, mixed> $config Configuration for common scenarios:
     *   - 'splitStreetEnabled' => bool: Configure split street setting
     *   - 'countryCode' => string: Default country code to return
     *   - 'hasStates' => bool: Whether country has states
     *   - 'hasAdditionalField' => bool: Whether additional address field is available
     *   - 'additionalFieldName' => string: Name of additional field
     *   - 'subdivisionCode' => string|null: Subdivision code to return
     * @return AddressCheckPayloadBuilder
     */
    private function createPayloadBuilder(array $config = []): AddressCheckPayloadBuilder
    {
        // Create mocks
        $this->countryCodeFetcher = $this->createMock(CountryCodeFetcherInterface::class);
        $this->subdivisionCodeFetcher = $this->createMock(SubdivisionCodeFetcherInterface::class);
        $this->countryHasStatesChecker = $this->createMock(CountryHasStatesCheckerInterface::class);
        $this->additionalAddressFieldChecker = $this->createMock(AdditionalAddressFieldCheckerInterface::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->streetSplitter = $this->createMock(StreetSplitterInterface::class);
        $this->customerAddressExtensionRepository = $this->createMock(EntityRepository::class);
        $this->orderAddressExtensionRepository = $this->createMock(EntityRepository::class);
        $this->enderecoService = $this->createMock(EnderecoService::class);
        $this->pluginStatusService = $this->createMock(PluginStatusService::class);

        // Configure common defaults that can be overridden
        if (isset($config['splitStreetEnabled'])) {
            $this->systemConfigService->method('getBool')->willReturn($config['splitStreetEnabled']);
        }
        
        if (isset($config['countryCode'])) {
            $this->countryCodeFetcher->method('fetchCountryCodeByCountryIdAndContext')->willReturn($config['countryCode']);
        }
        
        if (isset($config['hasStates'])) {
            $this->countryHasStatesChecker->method('hasCountryStates')->willReturn($config['hasStates']);
        }
        
        if (isset($config['hasAdditionalField'])) {
            $this->additionalAddressFieldChecker->method('hasAdditionalAddressField')->willReturn($config['hasAdditionalField']);
        }
        
        if (isset($config['additionalFieldName'])) {
            $this->additionalAddressFieldChecker->method('getAvailableAdditionalAddressFieldName')->willReturn($config['additionalFieldName']);
        }
        
        if (isset($config['subdivisionCode'])) {
            $this->subdivisionCodeFetcher->method('fetchSubdivisionCodeByCountryStateId')->willReturn($config['subdivisionCode']);
        }

        return new AddressCheckPayloadBuilder(
            $this->countryCodeFetcher,
            $this->subdivisionCodeFetcher,
            $this->countryHasStatesChecker,
            $this->additionalAddressFieldChecker,
            $this->systemConfigService,
            $this->streetSplitter,
            $this->customerAddressExtensionRepository,
            $this->orderAddressExtensionRepository,
            $this->enderecoService,
            $this->pluginStatusService
        );
    }

    /**
     * Tests that buildFromArray returns a combined payload when split street format is disabled.
     */
    public function testBuildFromArrayReturnsCombinedPayloadWhenSplitFormatDisabled(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);

        // Still need to set specific expectations for testing
        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with(AddressCheckPayloadBuilderInterface::CONFIG_SPLIT_STREET, null);

        $this->countryCodeFetcher
            ->expects($this->once())
            ->method('fetchCountryCodeByCountryIdAndContext');

        $this->countryHasStatesChecker
            ->expects($this->once())
            ->method('hasCountryStates');

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('hasAdditionalAddressField')
            ->with($this->context);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('getAvailableAdditionalAddressFieldName')
            ->with($this->context);

        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => 'Additional info',
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);

        $this->assertInstanceOf(AddressCheckPayloadCombined::class, $payload);
        $this->assertSame(AddressCheckPayloadInterface::FORMAT_COMBINED, $payload->getFormatType());
    }

    /**
     * Tests that buildFromArray returns a split payload when split street format is enabled.
     */
    public function testBuildFromArrayReturnsSplitPayloadWhenSplitFormatEnabled(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => true,
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);

        // Still need to set specific expectations for testing
        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with(AddressCheckPayloadBuilderInterface::CONFIG_SPLIT_STREET, null);

        $this->countryCodeFetcher
            ->expects($this->once())
            ->method('fetchCountryCodeByCountryIdAndContext');

        $this->countryHasStatesChecker
            ->expects($this->once())
            ->method('hasCountryStates');

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('hasAdditionalAddressField')
            ->with($this->context);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('getAvailableAdditionalAddressFieldName')
            ->with($this->context);

        $this->streetSplitter
            ->expects($this->once())
            ->method('splitStreet')
            ->with('Lindenstraße 2')
            ->willReturn(new SplitStreetResultDto(
                'Lindenstraße 2',
                'Lindenstraße',
                '2',
                null
            ));

        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => 'Additional info',
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);

        $this->assertInstanceOf(AddressCheckPayloadSplit::class, $payload);
        $this->assertSame(AddressCheckPayloadInterface::FORMAT_SPLIT, $payload->getFormatType());
    }

    /**
     * Tests that buildFromArray uses sales channel specific system configuration when context contains sales channel.
     */
    public function testBuildFromArrayUsesSystemConfigWithSalesChannelContext(): void
    {
        $salesChannelId = 'test-sales-channel-id';
        $source = new SalesChannelApiSource($salesChannelId);
        $context = new Context($source);

        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with(AddressCheckPayloadBuilderInterface::CONFIG_SPLIT_STREET, $salesChannelId);

        $this->countryCodeFetcher
            ->expects($this->once())
            ->method('fetchCountryCodeByCountryIdAndContext');

        $this->countryHasStatesChecker
            ->expects($this->once())
            ->method('hasCountryStates');

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('hasAdditionalAddressField')
            ->with($context);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('getAvailableAdditionalAddressFieldName')
            ->with($context);

        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $context);

        $this->assertInstanceOf(AddressCheckPayloadCombined::class, $payload);
    }

    /**
     * Tests that the same address data produces different payloads when format setting changes,
     * ensuring different API signatures to force status code cache invalidation.
     */
    public function testSameAddressDataProducesDifferentPayloadsWhenFormatSettingChanges(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);
        
        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => 'Additional info',
            'additionalAddressLine2' => null,
        ];

        // Setup common mock expectations - factory handles defaults
        $this->countryCodeFetcher
            ->expects($this->exactly(2))
            ->method('fetchCountryCodeByCountryIdAndContext');

        $this->countryHasStatesChecker
            ->expects($this->exactly(2))
            ->method('hasCountryStates');

        $this->additionalAddressFieldChecker
            ->expects($this->exactly(2))
            ->method('hasAdditionalAddressField');

        $this->additionalAddressFieldChecker
            ->expects($this->exactly(2))
            ->method('getAvailableAdditionalAddressFieldName');

        // First call: combined format (split disabled)
        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->with(AddressCheckPayloadBuilderInterface::CONFIG_SPLIT_STREET, null)
            ->willReturnOnConsecutiveCalls(false, true);

        // Mock street splitter for split format
        $this->streetSplitter
            ->expects($this->once())
            ->method('splitStreet')
            ->with('Lindenstraße 2')
            ->willReturn(new SplitStreetResultDto(
                'Lindenstraße 2',
                'Lindenstraße',
                '2',
                'Additional info'
            ));

        // Build payloads with different format settings
        $combinedPayload = $payloadBuilder->buildFromArray($addressData, $this->context);
        $splitPayload = $payloadBuilder->buildFromArray($addressData, $this->context);

        // Verify different payload types are created
        $this->assertInstanceOf(AddressCheckPayloadCombined::class, $combinedPayload);
        $this->assertInstanceOf(AddressCheckPayloadSplit::class, $splitPayload);
        
        // Verify different format types
        $this->assertSame(AddressCheckPayloadInterface::FORMAT_COMBINED, $combinedPayload->getFormatType());
        $this->assertSame(AddressCheckPayloadInterface::FORMAT_SPLIT, $splitPayload->getFormatType());

        // Most importantly: verify the payloads are different
        // This ensures different API signatures and forces status code refresh
        $combinedData = $combinedPayload->data();
        $splitData = $splitPayload->data();
        
        $this->assertNotEquals($combinedData, $splitData, 'Payloads should be different when format setting changes');
        
        // Verify that combined format has full street
        $this->assertSame('Lindenstraße 2', $combinedData['streetFull']);
        $this->assertArrayNotHasKey('street', $combinedData);
        $this->assertArrayNotHasKey('houseNumber', $combinedData);
        
        // Verify that split format has separate street name and building number  
        $this->assertSame('Lindenstraße', $splitData['street']);
        $this->assertSame('2', $splitData['houseNumber']);
        $this->assertArrayNotHasKey('streetFull', $splitData);
    }

    /**
     * Tests that subdivision codes are correctly fetched and included in payload for addresses with states.
     */
    public function testHandlesSubdivisionCodeCorrectly(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'US',
            'hasStates' => true,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1',
            'subdivisionCode' => 'CA'
        ]);

        $this->subdivisionCodeFetcher
            ->expects($this->once())
            ->method('fetchSubdivisionCodeByCountryStateId')
            ->with('state-id', $this->context);

        $addressData = [
            'countryId' => 'country-id',
            'countryStateId' => 'state-id',
            'zipcode' => '90210',
            'city' => 'Beverly Hills',
            'street' => '123 Main Street',
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);
        $data = $payload->data();

        $this->assertSame('CA', $data['subdivisionCode']);
    }

    /**
     * Tests that empty subdivision code is set when country has states but no state is selected.
     */
    public function testHandlesCountryWithStatesButNoStateSelected(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'US',
            'hasStates' => true,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);

        $this->subdivisionCodeFetcher
            ->expects($this->never())
            ->method('fetchSubdivisionCodeByCountryStateId');

        $this->countryHasStatesChecker
            ->expects($this->once())
            ->method('hasCountryStates')
            ->with('country-id', $this->context);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('hasAdditionalAddressField')
            ->with($this->context);

        $addressData = [
            'countryId' => 'country-id',
            'countryStateId' => '',
            'zipcode' => '90210',
            'city' => 'Beverly Hills',
            'street' => '123 Main Street',
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);
        $data = $payload->data();

        $this->assertSame('', $data['subdivisionCode']);
    }

    /**
     * Tests that subdivision code is omitted from payload when country has no states.
     */
    public function testHandlesCountryWithoutStates(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine1'
        ]);

        $this->countryHasStatesChecker
            ->expects($this->once())
            ->method('hasCountryStates')
            ->with('country-id', $this->context);

        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);
        $data = $payload->data();

        $this->assertArrayNotHasKey('subdivisionCode', $data);
    }

    /**
     * Tests that additional address info is correctly extracted based on available field configuration.
     */
    public function testHandlesAdditionalAddressInfo(): void
    {
        $payloadBuilder = $this->createPayloadBuilder([
            'splitStreetEnabled' => false,
            'countryCode' => 'DE',
            'hasStates' => false,
            'hasAdditionalField' => true,
            'additionalFieldName' => 'additionalAddressLine2'
        ]);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('hasAdditionalAddressField')
            ->with($this->context);

        $this->additionalAddressFieldChecker
            ->expects($this->once())
            ->method('getAvailableAdditionalAddressFieldName')
            ->with($this->context);

        $addressData = [
            'countryId' => 'country-id',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'street' => 'Lindenstraße 2',
            'additionalAddressLine1' => 'First additional info',
            'additionalAddressLine2' => 'Second additional info',
        ];

        $payload = $payloadBuilder->buildFromArray($addressData, $this->context);
        $data = $payload->data();

        $this->assertSame('Second additional info', $data['additionalInfo']);
    }
}
