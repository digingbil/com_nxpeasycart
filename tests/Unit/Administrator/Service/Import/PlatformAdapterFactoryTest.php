<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  Tests
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service\Import;

use InvalidArgumentException;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\PlatformAdapterFactory;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\NativeAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\ShopifyAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\WoocommerceAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\VirtuemartAdapter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\HikashopAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PlatformAdapterFactory class.
 *
 * @since 0.3.0
 */
final class PlatformAdapterFactoryTest extends TestCase
{
    private PlatformAdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PlatformAdapterFactory('EUR');
    }

    // ========================================================================
    // SUPPORTED PLATFORMS TESTS
    // ========================================================================

    public function testSupportedPlatformsConstant(): void
    {
        $expected = ['native', 'shopify', 'woocommerce', 'virtuemart', 'hikashop'];
        $this->assertEquals($expected, PlatformAdapterFactory::PLATFORMS);
    }

    /**
     * @dataProvider supportedPlatformProvider
     */
    public function testIsSupportedReturnsTrueForValidPlatforms(string $platform): void
    {
        $this->assertTrue($this->factory->isSupported($platform));
    }

    public static function supportedPlatformProvider(): array
    {
        return [
            'native'      => ['native'],
            'shopify'     => ['shopify'],
            'woocommerce' => ['woocommerce'],
            'virtuemart'  => ['virtuemart'],
            'hikashop'    => ['hikashop'],
        ];
    }

    /**
     * @dataProvider unsupportedPlatformProvider
     */
    public function testIsSupportedReturnsFalseForInvalidPlatforms(string $platform): void
    {
        $this->assertFalse($this->factory->isSupported($platform));
    }

    public static function unsupportedPlatformProvider(): array
    {
        return [
            'magento'     => ['magento'],
            'prestashop'  => ['prestashop'],
            'bigcommerce' => ['bigcommerce'],
            'empty'       => [''],
            'random'      => ['some_random_platform'],
        ];
    }

    public function testIsSupportedIsCaseInsensitive(): void
    {
        $this->assertTrue($this->factory->isSupported('SHOPIFY'));
        $this->assertTrue($this->factory->isSupported('Shopify'));
        $this->assertTrue($this->factory->isSupported('ShOpIfY'));
    }

    // ========================================================================
    // ADAPTER CREATION TESTS
    // ========================================================================

    public function testGetAdapterReturnsNativeAdapter(): void
    {
        $adapter = $this->factory->getAdapter('native');
        $this->assertInstanceOf(NativeAdapter::class, $adapter);
    }

    public function testGetAdapterReturnsShopifyAdapter(): void
    {
        $adapter = $this->factory->getAdapter('shopify');
        $this->assertInstanceOf(ShopifyAdapter::class, $adapter);
    }

    public function testGetAdapterReturnsWoocommerceAdapter(): void
    {
        $adapter = $this->factory->getAdapter('woocommerce');
        $this->assertInstanceOf(WoocommerceAdapter::class, $adapter);
    }

    public function testGetAdapterReturnsVirtuemartAdapter(): void
    {
        $adapter = $this->factory->getAdapter('virtuemart');
        $this->assertInstanceOf(VirtuemartAdapter::class, $adapter);
    }

    public function testGetAdapterReturnsHikashopAdapter(): void
    {
        $adapter = $this->factory->getAdapter('hikashop');
        $this->assertInstanceOf(HikashopAdapter::class, $adapter);
    }

    public function testGetAdapterThrowsExceptionForUnsupportedPlatform(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported platform: magento');

        $this->factory->getAdapter('magento');
    }

    public function testGetAdapterCachesInstances(): void
    {
        $adapter1 = $this->factory->getAdapter('shopify');
        $adapter2 = $this->factory->getAdapter('shopify');

        $this->assertSame($adapter1, $adapter2, 'Factory should return cached adapter instance');
    }

    public function testGetAdapterIsCaseInsensitive(): void
    {
        $adapter1 = $this->factory->getAdapter('SHOPIFY');
        $adapter2 = $this->factory->getAdapter('shopify');

        $this->assertSame($adapter1, $adapter2);
    }

    // ========================================================================
    // DEFAULT CURRENCY TESTS
    // ========================================================================

    public function testDefaultCurrencyIsSetOnAdapters(): void
    {
        $factory = new PlatformAdapterFactory('USD');
        $adapter = $factory->getAdapter('native');

        // Normalize a row and check currency is USD
        $row = [
            'product_id'    => '1',
            'product_slug'  => 'test-product',
            'product_title' => 'Test Product',
            'short_desc'    => 'Test desc',
            'long_desc'     => '<p>Test</p>',
            'product_type'  => 'physical',
            'featured'      => '0',
            'active'        => '1',
            'categories'    => 'Test',
            'images'        => '',
            'variant_id'    => '1',
            'sku'           => 'TEST-SKU',
            'price'         => '29.99',
            'sale_price'    => '',
            'sale_start'    => '',
            'sale_end'      => '',
            'currency'      => '',
            'stock'         => '10',
            'weight'        => '0.5',
            'ean'           => '',
            'is_digital'    => '0',
            'variant_active' => '1',
            'options'       => '',
            'original_images' => '',
        ];

        $normalized = $adapter->normalizeRow($row, $adapter->getDefaultMapping());
        $this->assertEquals('USD', $normalized['variant']['currency']);
    }

    public function testDefaultCurrencyIsUppercased(): void
    {
        $factory = new PlatformAdapterFactory('eur');
        $adapter = $factory->getAdapter('native');

        $row = $this->createMinimalNativeRow();
        $normalized = $adapter->normalizeRow($row, $adapter->getDefaultMapping());

        $this->assertEquals('EUR', $normalized['variant']['currency']);
    }

    // ========================================================================
    // PLATFORM DETECTION TESTS
    // ========================================================================

    public function testDetectPlatformIdentifiesNativeFormat(): void
    {
        // Must include signature headers: product_id, product_slug, variant_id, sku, is_digital
        $headers = [
            'product_id', 'product_slug', 'product_title', 'short_desc', 'long_desc',
            'product_type', 'featured', 'active', 'categories', 'images',
            'variant_id', 'sku', 'price', 'sale_price', 'sale_start', 'sale_end',
            'currency', 'stock', 'weight', 'ean', 'is_digital', 'variant_active',
            'options', 'original_images',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('native', $detected);
    }

    public function testDetectPlatformIdentifiesShopifyFormat(): void
    {
        $headers = [
            'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type',
            'Tags', 'Published', 'Option1 Name', 'Option1 Value', 'Variant SKU',
            'Variant Grams', 'Variant Inventory Qty', 'Variant Price', 'Image Src',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('shopify', $detected);
    }

    public function testDetectPlatformIdentifiesWoocommerceFormat(): void
    {
        $headers = [
            'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?',
            'Short description', 'Description', 'Sale price', 'Regular price',
            'Categories', 'Images', 'Stock', 'Weight (kg)',
            'Attribute 1 name', 'Attribute 1 value(s)',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('woocommerce', $detected);
    }

    public function testDetectPlatformIdentifiesVirtuemartFormat(): void
    {
        $headers = [
            'product_id', 'product_sku', 'product_name', 'slug', 'product_s_desc',
            'product_desc', 'published', 'product_special', 'categories', 'file_url',
            'product_price', 'product_in_stock', 'customfields',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('virtuemart', $detected);
    }

    public function testDetectPlatformIdentifiesHikashopFormat(): void
    {
        $headers = [
            'product_id', 'product_code', 'product_name', 'product_alias',
            'product_meta_description', 'product_description', 'product_published',
            'product_categories', 'product_images', 'product_type', 'product_price',
            'product_quantity', 'characteristic_1',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('hikashop', $detected);
    }

    public function testDetectPlatformIsCaseInsensitive(): void
    {
        $headers = [
            'HANDLE', 'TITLE', 'BODY (HTML)', 'VENDOR', 'PRODUCT CATEGORY', 'TYPE',
            'TAGS', 'PUBLISHED', 'OPTION1 NAME', 'OPTION1 VALUE', 'VARIANT SKU',
            'VARIANT GRAMS', 'VARIANT INVENTORY QTY', 'VARIANT PRICE', 'IMAGE SRC',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('shopify', $detected);
    }

    public function testDetectPlatformTrimsWhitespace(): void
    {
        $headers = [
            '  Handle  ', ' Title ', ' Body (HTML) ', ' Vendor ', ' Product Category ',
            ' Type ', ' Tags ', ' Published ', ' Option1 Name ', ' Option1 Value ',
            ' Variant SKU ', ' Variant Grams ', ' Variant Inventory Qty ',
            ' Variant Price ', ' Image Src ',
        ];

        $detected = $this->factory->detectPlatform($headers);
        $this->assertEquals('shopify', $detected);
    }

    public function testDetectPlatformReturnsNullForUnknownFormat(): void
    {
        $headers = ['random_column', 'another_column', 'unknown_field'];
        $detected = $this->factory->detectPlatform($headers);

        $this->assertNull($detected);
    }

    public function testDetectPlatformReturnsNullForEmptyHeaders(): void
    {
        $detected = $this->factory->detectPlatform([]);
        $this->assertNull($detected);
    }

    // ========================================================================
    // GET ALL PLATFORMS TESTS
    // ========================================================================

    public function testGetAllPlatformsReturnsAllWithDisplayNames(): void
    {
        $platforms = $this->factory->getAllPlatforms();

        $this->assertCount(5, $platforms);
        $this->assertArrayHasKey('native', $platforms);
        $this->assertArrayHasKey('shopify', $platforms);
        $this->assertArrayHasKey('woocommerce', $platforms);
        $this->assertArrayHasKey('virtuemart', $platforms);
        $this->assertArrayHasKey('hikashop', $platforms);

        // Check display names are not empty
        foreach ($platforms as $key => $displayName) {
            $this->assertNotEmpty($displayName, "Display name for {$key} should not be empty");
            $this->assertIsString($displayName);
        }
    }

    public function testGetAllPlatformsDisplayNamesAreUserFriendly(): void
    {
        $platforms = $this->factory->getAllPlatforms();

        $this->assertEquals('NXP Easy Cart (Native)', $platforms['native']);
        $this->assertEquals('Shopify', $platforms['shopify']);
        $this->assertEquals('WooCommerce', $platforms['woocommerce']);
        $this->assertEquals('VirtueMart', $platforms['virtuemart']);
        $this->assertEquals('HikaShop', $platforms['hikashop']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createMinimalNativeRow(): array
    {
        return [
            'product_id'    => '1',
            'product_slug'  => 'test-product',
            'product_title' => 'Test Product',
            'short_desc'    => 'Test description',
            'long_desc'     => '<p>Long description</p>',
            'product_type'  => 'physical',
            'featured'      => '0',
            'active'        => '1',
            'categories'    => 'Test Category',
            'images'        => '',
            'variant_id'    => '1',
            'sku'           => 'TEST-001',
            'price'         => '29.99',
            'sale_price'    => '',
            'sale_start'    => '',
            'sale_end'      => '',
            'currency'      => '',
            'stock'         => '10',
            'weight'        => '0.5',
            'ean'           => '',
            'is_digital'    => '0',
            'variant_active' => '1',
            'options'       => '',
            'original_images' => '',
        ];
    }
}
