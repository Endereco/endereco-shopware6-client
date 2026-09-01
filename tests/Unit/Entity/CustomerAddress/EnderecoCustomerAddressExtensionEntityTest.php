<?php declare(strict_types=1);

/*
 * This file is part of the Endereco Shopware 6 Client.
 *
 * (c) Endereco UG (haftungsbeschränkt)
 */

namespace Endereco\Shopware6Client\Tests\Unit\Entity\CustomerAddress;

use Endereco\Shopware6Client\Entity\CustomerAddress\CustomerAddressExtension;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionCollection;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\CustomerAddress\EnderecoCustomerAddressExtensionEntity;
use Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionEntity;
use Endereco\Shopware6Client\Tests\Unit\Entity\CustomerAddress\CreatesCustomerAddressTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;

/**
 * Tests for EnderecoCustomerAddressExtensionEntity
 * 
 * These tests are critical for preventing the getUniqueIdentifier TypeError
 * that caused customer outages in production.
 */
class EnderecoCustomerAddressExtensionEntityTest extends TestCase
{
    use CreatesCustomerAddressTrait;

    #[DataProvider('extensionScenarioProvider')]
    public function testExtensionCreationAndCollectionCompatibility(string $street, string $amsStatus): void
    {
        $addressId = Uuid::randomHex();
        $customerAddress = $this->createCustomerAddress($addressId);
        
        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($customerAddress);
        $extension->setStreet($street);
        $extension->setAmsStatus($amsStatus);
        
        $this->assertSame($addressId, $extension->getUniqueIdentifier());
        $this->assertSame($street, $extension->getStreet());
        $this->assertSame($amsStatus, $extension->getAmsStatus());
        
        // Test collection compatibility
        $collection = new EnderecoCustomerAddressExtensionCollection();
        $collection->add($extension);
        $this->assertSame(1, $collection->count());
        $this->assertSame($extension, $collection->first());
    }

    public static function extensionScenarioProvider(): array
    {
        return [
            'German address with umlaut' => ['Lindenstraße', 'address_correct'],
            'Simple street name' => ['Main Street', 'not-checked'],
            'Address with abbreviation' => ['Lindenstr.', 'address_needs_correction'],
        ];
    }


    /**
     * Direct validation that getUniqueIdentifier returns the addressId
     * 
     * This ensures the override in EnderecoCustomerAddressExtensionEntity works correctly.
     */
    public function testGetUniqueIdentifierReturnsAddressId(): void
    {
        $addressId = Uuid::randomHex();
        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($this->createCustomerAddress($addressId));
        
        $this->assertSame($addressId, $extension->getUniqueIdentifier());
    }

    /**
     * Critical safety test - ensures getUniqueIdentifier never returns null
     * 
     * This prevents the TypeError that was the root cause of the production issue.
     */
    public function testGetUniqueIdentifierNeverReturnsNull(): void
    {
        $addressId = Uuid::randomHex();
        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($this->createCustomerAddress($addressId));
        
        $uniqueId = $extension->getUniqueIdentifier();
        
        $this->assertNotNull($uniqueId);
        $this->assertIsString($uniqueId);
        $this->assertNotEmpty($uniqueId);
        $this->assertSame($addressId, $uniqueId);
    }

    /**
     * Tests the createOrderAddressExtension method creates entity with proper unique identifier
     * 
     * This method is called during order conversion and must create valid entities.
     */
    public function testCreateOrderAddressExtensionHasUniqueIdentifier(): void
    {
        $customerAddressId = Uuid::randomHex();
        $orderAddressId = Uuid::randomHex();
        
        $customerExtension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($this->createCustomerAddress($customerAddressId));
        $customerExtension->setAmsStatus('address_correct');
        $customerExtension->setStreet('Lindenstraße');
        $customerExtension->setHouseNumber('2');
        
        $orderExtension = $customerExtension->createOrderAddressExtension($orderAddressId);
        
        // The created order extension must have a unique identifier
        $this->assertNotNull($orderExtension->getUniqueIdentifier());
        $this->assertIsString($orderExtension->getUniqueIdentifier());
        $this->assertNotEmpty($orderExtension->getUniqueIdentifier());
        // The unique identifier should be the entity's own ID, not the orderAddressId
        $this->assertSame($orderExtension->getId(), $orderExtension->getUniqueIdentifier());
        $this->assertSame($orderAddressId, $orderExtension->getAddressId());
        
        // It should be addable to a collection without errors
        $collection = new \Endereco\Shopware6Client\Entity\EnderecoAddressExtension\OrderAddress\EnderecoOrderAddressExtensionCollection();
        $collection->add($orderExtension);
        
        $this->assertSame(1, $collection->count());
    }

    /**
     * Tests that sync operation preserves unique identifier functionality
     * 
     * Ensures sync method doesn't interfere with collection processing.
     */
    public function testSyncDoesNotAffectUniqueIdentifier(): void
    {
        $addressId1 = Uuid::randomHex();
        $addressId2 = Uuid::randomHex();
        
        $extension1 = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($this->createCustomerAddress($addressId1));
        $extension1->setStreet('Lindenstraße');
        
        $extension2 = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($this->createCustomerAddress($addressId2));
        $extension2->setStreet('Lindenstr.');
        
        $originalUniqueId = $extension1->getUniqueIdentifier();
        
        // Sync should not affect unique identifier
        $extension1->sync($extension2);
        
        $this->assertSame($originalUniqueId, $extension1->getUniqueIdentifier());
        $this->assertSame('Lindenstr.', $extension1->getStreet());
        
        // Extension should still work in collections
        $collection = new EnderecoCustomerAddressExtensionCollection();
        $collection->add($extension1);
        
        $this->assertSame(1, $collection->count());
    }

    /**
     * Tests that extension can be created with unique identifier different from address ID
     * 
     * This validates that manually created extensions can have custom unique identifiers
     * separate from their address ID when not using the factory method.
     */
    public function testCanCreateExtensionWithDifferentUniqueIdFromAddressId(): void
    {
        $addressId = Uuid::randomHex();
        $customUniqueId = Uuid::randomHex();
        
        // Create extension manually without factory
        $extension = new EnderecoCustomerAddressExtensionEntity();
        $extension->setAddressId($addressId);  
        $extension->setUniqueIdentifier($customUniqueId);
        
        // Verify the unique identifier is different from address ID
        $this->assertSame($customUniqueId, $extension->getUniqueIdentifier());
        $this->assertSame($addressId, $extension->getAddressId());
        $this->assertNotSame($extension->getUniqueIdentifier(), $extension->getAddressId());
        
        // Ensure it still works in collections
        $collection = new EnderecoCustomerAddressExtensionCollection();
        $collection->add($extension);
        
        $this->assertSame(1, $collection->count());
        $this->assertTrue($collection->has($customUniqueId));
        $this->assertFalse($collection->has($addressId));
        $this->assertSame($extension, $collection->get($customUniqueId));
    }

    /**
     * Tests that json_encode() does not fail with a circular reference error when
     * the extension holds a back-reference to its parent CustomerAddressEntity.
     *
     * Without jsonSerialize() being overridden to exclude $address, json_encode()
     * would detect the cycle CustomerAddressEntity → extension → CustomerAddressEntity
     * and return false with JSON_ERROR_RECURSION.
     */
    public function testJsonEncodeDoesNotProduceCircularReferenceError(): void
    {
        $addressId = Uuid::randomHex();
        $customerAddress = $this->createCustomerAddress($addressId);

        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($customerAddress);

        // Set up the circular reference that triggered the production bug:
        // CustomerAddressEntity → EnderecoCustomerAddressExtensionEntity → CustomerAddressEntity
        $extension->setAddress($customerAddress);
        $customerAddress->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $extension);

        // Mirrors what Shopware's StructNormalizer::normalize() does during webhook serialization:
        // Without a jsonSerialize() override this cycle causes JSON_ERROR_RECURSION.
        $result = json_encode($customerAddress);
        $this->assertNotFalse($result, 'json_encode($customerAddress) failed: ' . json_last_error_msg());
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * Tests that cloning the EnderecoCustomerAddressExtension does not lead
     * to infinite recursions.
     *
     * Cloning a Cart that contains a CustomerAddressEntity must not recurse
     * infinitely through the address ↔ enderecoAddress back-reference cycle.
     * This is the case, if the address property of the EnderecoCustomerAddressExtension
     * is not dropped before copying.
 */
    public function testCloningCartWithDeliveryDoesNotProduceInfiniteRecursion(): void
    {
        $addressId = Uuid::randomHex();
        $address = $this->createCustomerAddress($addressId);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setUniqueIdentifier($country->getId());
        $address->setCountry($country);

        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($address);
        $address->addExtension(CustomerAddressExtension::ENDERECO_EXTENSION, $extension);

        $location = ShippingLocation::createFromAddress($address);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());
        $shippingMethod->setUniqueIdentifier($shippingMethod->getId());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            $location,
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart = new Cart('test-token');
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        // Without the __clone() fix, this line throws:
        // "Maximum call stack size of ... bytes ... reached. Infinite recursion?"
        $clonedCart = clone $cart;

        $this->assertNotSame($cart, $clonedCart);

        $clonedAddress = $clonedCart->getDeliveries()->first()?->getLocation()->getAddress();
        $this->assertInstanceOf(CustomerAddressEntity::class, $clonedAddress);
        $this->assertNotSame($address, $clonedAddress);

        // The clone's back-reference must be nulled to have broken the cycle.
        $clonedExtension = $clonedAddress->getExtension(CustomerAddressExtension::ENDERECO_EXTENSION);
        $this->assertInstanceOf(EnderecoCustomerAddressExtensionEntity::class, $clonedExtension);
        $this->assertNull($clonedExtension->getAddress());

        // The original object graph must remain untouched by cloning.
        $this->assertSame($address, $extension->getAddress());
    }

    /**
     * Tests that extension with uninitialized addressId throws expected error
     * 
     * This negative test ensures we catch malformed extensions early.
     */
    public function testExtensionWithUninitializedAddressIdThrowsError(): void
    {
        $extension = new EnderecoCustomerAddressExtensionEntity();
        // Don't initialize addressId, leaving it uninitialized
        
        $collection = new EnderecoCustomerAddressExtensionCollection();
        
        $this->expectException(\Error::class);
        
        $collection->add($extension);
    }

    /**
     * Tests that factory method creates extension with all required fields
     * 
     * Validates the factory method sets up extensions correctly for collection use.
     */
    public function testFactoryMethodCreatesCompleteExtension(): void
    {
        $addressId = Uuid::randomHex();
        $customerAddress = $this->createCustomerAddress($addressId);
        
        $extension = EnderecoCustomerAddressExtensionEntity::createWithDefaultValues($customerAddress);
        
        $this->assertSame($addressId, $extension->getAddressId());
        $this->assertSame($addressId, $extension->getUniqueIdentifier());
        $this->assertSame($customerAddress, $extension->getAddress());
        $this->assertSame('not-checked', $extension->getAmsStatus());
        $this->assertSame([], $extension->getAmsPredictions());
        
        // Test all collection operations work
        $collection = new EnderecoCustomerAddressExtensionCollection();
        $collection->add($extension);
        
        $this->assertSame(1, $collection->count());
        $this->assertTrue($collection->has($addressId));
        $this->assertSame($extension, $collection->get($addressId));
    }
}
