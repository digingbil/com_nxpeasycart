<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  Tests
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service\Import\Adapter;

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\ShopifyAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ShopifyAdapter class.
 *
 * @since 0.3.0
 */
final class ShopifyAdapterTest extends TestCase
{
    private ShopifyAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new ShopifyAdapter();
    }

    // ========================================================================
    // ADAPTER IDENTITY TESTS
    // ========================================================================

    public function testGetNameReturnsShopify(): void
    {
        $this->assertEquals('shopify', $this->adapter->getName());
    }

    public function testGetDisplayNameIsUserFriendly(): void
    {
        $this->assertEquals('Shopify', $this->adapter->getDisplayName());
    }

    public function testGetWeightUnitIsGrams(): void
    {
        $this->assertEquals('g', $this->adapter->getWeightUnit());
    }

    public function testGetCategorySeparatorIsGreaterThan(): void
    {
        $this->assertEquals('>', $this->adapter->getCategorySeparator());
    }

    public function testShouldGroupVariantsIsTrue(): void
    {
        $this->assertTrue($this->adapter->shouldGroupVariants());
    }

    public function testGetGroupingColumnIsHandle(): void
    {
        $this->assertEquals('Handle', $this->adapter->getGroupingColumn());
    }

    // ========================================================================
    // SIGNATURE HEADERS TESTS
    // ========================================================================

    public function testGetSignatureHeadersContainsShopifySpecificColumns(): void
    {
        $headers = $this->adapter->getSignatureHeaders();

        $this->assertContains('Handle', $headers);
        $this->assertContains('Title', $headers);
        $this->assertContains('Variant SKU', $headers);
        $this->assertContains('Option1 Name', $headers);
        $this->assertContains('Option1 Value', $headers);
    }

    // ========================================================================
    // DEFAULT MAPPING TESTS
    // ========================================================================

    public function testGetDefaultMappingContainsShopifyProductFields(): void
    {
        $mapping = $this->adapter->getDefaultMapping();

        $this->assertArrayHasKey('Handle', $mapping);
        $this->assertArrayHasKey('Title', $mapping);
        $this->assertArrayHasKey('Body (HTML)', $mapping);
        $this->assertArrayHasKey('Type', $mapping);
        $this->assertArrayHasKey('Tags', $mapping);
        $this->assertArrayHasKey('Published', $mapping);
        $this->assertArrayHasKey('Product Category', $mapping);
        $this->assertArrayHasKey('Image Src', $mapping);
    }

    public function testGetDefaultMappingContainsShopifyVariantFields(): void
    {
        $mapping = $this->adapter->getDefaultMapping();

        $this->assertArrayHasKey('Variant SKU', $mapping);
        $this->assertArrayHasKey('Variant Price', $mapping);
        $this->assertArrayHasKey('Variant Compare At Price', $mapping);
        $this->assertArrayHasKey('Variant Inventory Qty', $mapping);
        $this->assertArrayHasKey('Variant Grams', $mapping);
        $this->assertArrayHasKey('Variant Barcode', $mapping);
        $this->assertArrayHasKey('Option1 Name', $mapping);
        $this->assertArrayHasKey('Option1 Value', $mapping);
        $this->assertArrayHasKey('Option2 Name', $mapping);
        $this->assertArrayHasKey('Option2 Value', $mapping);
        $this->assertArrayHasKey('Option3 Name', $mapping);
        $this->assertArrayHasKey('Option3 Value', $mapping);
        $this->assertArrayHasKey('Variant Image', $mapping);
    }

    // ========================================================================
    // ROW NORMALIZATION TESTS
    // ========================================================================

    public function testNormalizeRowBasicProduct(): void
    {
        $row = $this->createBasicShopifyRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('classic-cotton-tee', $normalized['product']['original_id']);
        $this->assertNotEmpty($normalized['product']['slug']);
        $this->assertEquals('Classic Cotton T-Shirt', $normalized['product']['title']);
        $this->assertStringContainsString('Premium organic cotton', $normalized['product']['long_desc']);
        $this->assertTrue($normalized['product']['active']);
        $this->assertEquals('physical', $normalized['product']['product_type']);
        $this->assertFalse($normalized['product']['featured']);
    }

    public function testNormalizeRowVariantFields(): void
    {
        $row = $this->createBasicShopifyRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('TSHIRT-BLK-S', $normalized['variant']['sku']);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(45, $normalized['variant']['stock']);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
    }

    public function testNormalizeRowWeightConversion(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Grams'] = '180'; // 180 grams

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should be converted to kg: 180g = 0.180 kg
        $this->assertEqualsWithDelta(0.180, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowCategories(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Product Category'] = 'Apparel & Accessories > Clothing > Shirts';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([
            'Apparel & Accessories',
            'Clothing',
            'Shirts',
        ], $normalized['product']['categories']);
    }

    public function testNormalizeRowComparePriceCreatedSale(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Price'] = '24.99'; // Current sale price
        $row['Variant Compare At Price'] = '29.99'; // Original price

        $normalized = $this->adapter->normalizeRow($row, []);

        // Price should be swapped - compare price becomes regular price
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEqualsWithDelta(24.99, $normalized['variant']['sale_price'], 0.001);
    }

    public function testNormalizeRowNoComparePrice(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Price'] = '29.99';
        $row['Variant Compare At Price'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertNull($normalized['variant']['sale_price']);
    }

    public function testNormalizeRowComparePriceLowerThanPrice(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Price'] = '29.99';
        $row['Variant Compare At Price'] = '19.99'; // Lower than current price - unusual

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should not swap when compare is lower
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertNull($normalized['variant']['sale_price']);
    }

    public function testNormalizeRowOptions(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Option1 Name'] = 'Color';
        $row['Option1 Value'] = 'Black';
        $row['Option2 Name'] = 'Size';
        $row['Option2 Value'] = 'S';
        $row['Option3 Name'] = '';
        $row['Option3 Value'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        $this->assertEquals(['name' => 'Color', 'value' => 'Black'], $normalized['variant']['options'][0]);
        $this->assertEquals(['name' => 'Size', 'value' => 'S'], $normalized['variant']['options'][1]);
    }

    public function testNormalizeRowSkipsTitleOption(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Option1 Name'] = 'Title'; // Shopify default for no variants
        $row['Option1 Value'] = 'Default Title';
        $row['Option2 Name'] = '';
        $row['Option2 Value'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        // Title option should be skipped
        $this->assertCount(0, $normalized['variant']['options']);
    }

    public function testNormalizeRowPublishedFalse(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Published'] = 'FALSE';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertFalse($normalized['product']['active']);
    }

    public function testNormalizeRowProductImage(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Image Src'] = 'https://cdn.shopify.com/tshirt-main.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['https://cdn.shopify.com/tshirt-main.jpg'], $normalized['product']['images']);
    }

    public function testNormalizeRowVariantImage(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Image'] = 'https://cdn.shopify.com/variant.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['https://cdn.shopify.com/variant.jpg'], $normalized['variant']['original_images']);
    }

    public function testNormalizeRowInvalidImageUrl(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Image Src'] = 'not-a-valid-url';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([], $normalized['product']['images']);
    }

    public function testNormalizeRowValidBarcode(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Barcode'] = '5901234123457';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('5901234123457', $normalized['variant']['ean']);
    }

    public function testNormalizeRowInvalidBarcode(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant Barcode'] = 'invalid';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNull($normalized['variant']['ean']);
    }

    public function testNormalizeRowGeneratesSkuFromHandleAndOptions(): void
    {
        $row = $this->createBasicShopifyRow();
        $row['Variant SKU'] = ''; // Empty SKU
        $row['Option1 Name'] = 'Color';
        $row['Option1 Value'] = 'Black';
        $row['Option2 Name'] = 'Size';
        $row['Option2 Value'] = 'S';

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should generate SKU from handle-option values
        $this->assertNotEmpty($normalized['variant']['sku']);
        $this->assertStringContainsString('classic', strtolower($normalized['variant']['sku']));
    }

    public function testNormalizeRowEmptyRow(): void
    {
        $row = [
            'Handle' => '',
            'Title' => '',
            'Body (HTML)' => '',
            'Published' => '',
            'Product Category' => '',
            'Image Src' => '',
            'Variant SKU' => '',
            'Variant Price' => '',
            'Variant Compare At Price' => '',
            'Variant Inventory Qty' => '',
            'Variant Grams' => '',
            'Variant Barcode' => '',
            'Option1 Name' => '',
            'Option1 Value' => '',
            'Option2 Name' => '',
            'Option2 Value' => '',
            'Option3 Name' => '',
            'Option3 Value' => '',
            'Variant Image' => '',
        ];

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should return valid structure with defaults
        $this->assertArrayHasKey('product', $normalized);
        $this->assertArrayHasKey('variant', $normalized);
        $this->assertEquals('', $normalized['product']['title']);
        $this->assertEquals(0.0, $normalized['variant']['price']);
        $this->assertEquals(0, $normalized['variant']['stock']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createBasicShopifyRow(): array
    {
        return [
            'Handle'                    => 'classic-cotton-tee',
            'Title'                     => 'Classic Cotton T-Shirt',
            'Body (HTML)'               => '<p>Premium organic cotton t-shirt. Perfect for everyday wear.</p>',
            'Vendor'                    => 'My Store',
            'Product Category'          => 'Apparel & Accessories > Clothing > Shirts',
            'Type'                      => 'T-Shirts',
            'Tags'                      => 'cotton,casual,organic',
            'Published'                 => 'TRUE',
            'Option1 Name'              => 'Color',
            'Option1 Value'             => 'Black',
            'Option2 Name'              => 'Size',
            'Option2 Value'             => 'S',
            'Option3 Name'              => '',
            'Option3 Value'             => '',
            'Variant SKU'               => 'TSHIRT-BLK-S',
            'Variant Grams'             => '180',
            'Variant Inventory Qty'     => '45',
            'Variant Price'             => '24.99',
            'Variant Compare At Price'  => '29.99',
            'Variant Requires Shipping' => 'TRUE',
            'Variant Taxable'           => 'TRUE',
            'Variant Barcode'           => '5901234123457',
            'Image Src'                 => 'https://cdn.shopify.com/tshirt-main.jpg',
            'Variant Image'             => 'https://cdn.shopify.com/tshirt-black-s.jpg',
        ];
    }
}
