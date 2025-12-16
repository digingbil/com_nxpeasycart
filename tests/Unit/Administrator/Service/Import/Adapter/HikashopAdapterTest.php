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

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\HikashopAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HikashopAdapter class.
 *
 * @since 0.3.0
 */
final class HikashopAdapterTest extends TestCase
{
    private HikashopAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new HikashopAdapter();
    }

    // ========================================================================
    // ADAPTER IDENTITY TESTS
    // ========================================================================

    public function testGetNameReturnsHikashop(): void
    {
        $this->assertEquals('hikashop', $this->adapter->getName());
    }

    public function testGetDisplayNameIsUserFriendly(): void
    {
        $this->assertEquals('HikaShop', $this->adapter->getDisplayName());
    }

    public function testGetWeightUnitIsKilograms(): void
    {
        $this->assertEquals('kg', $this->adapter->getWeightUnit());
    }

    public function testGetCategorySeparatorIsComma(): void
    {
        $this->assertEquals(',', $this->adapter->getCategorySeparator());
    }

    public function testShouldGroupVariantsIsTrue(): void
    {
        $this->assertTrue($this->adapter->shouldGroupVariants());
    }

    public function testGetGroupingColumnIsProductId(): void
    {
        $this->assertEquals('product_id', $this->adapter->getGroupingColumn());
    }

    // ========================================================================
    // SIGNATURE HEADERS TESTS
    // ========================================================================

    public function testGetSignatureHeadersContainsHikaShopSpecificColumns(): void
    {
        $headers = $this->adapter->getSignatureHeaders();

        $this->assertContains('product_id', $headers);
        $this->assertContains('product_code', $headers);
        $this->assertContains('product_name', $headers);
        $this->assertContains('product_price', $headers);
        $this->assertContains('product_quantity', $headers);
    }

    // ========================================================================
    // ROW NORMALIZATION TESTS
    // ========================================================================

    public function testNormalizeRowBasicProduct(): void
    {
        $row = $this->createBasicHikashopRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('2001', $normalized['product']['original_id']);
        $this->assertEquals('Classic Cotton T-Shirt', $normalized['product']['title']);
        $this->assertEquals('classic-cotton-tshirt', $normalized['product']['slug']);
        $this->assertStringContainsString('Premium organic cotton', $normalized['product']['short_desc']);
        $this->assertStringContainsString('100% organic cotton', $normalized['product']['long_desc']);
        $this->assertTrue($normalized['product']['active']);
        $this->assertFalse($normalized['product']['featured']); // HikaShop doesn't have featured flag
        $this->assertEquals('physical', $normalized['product']['product_type']);
    }

    public function testNormalizeRowVariantFields(): void
    {
        $row = $this->createBasicHikashopRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('3001', $normalized['variant']['original_id']);
        $this->assertEquals('TSHIRT-BLK-S', $normalized['variant']['sku']);
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(45, $normalized['variant']['stock']);
        $this->assertEqualsWithDelta(0.18, $normalized['variant']['weight'], 0.001);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
    }

    public function testNormalizeRowVariantSkuPriority(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_code'] = 'VARIANT-SKU';
        $row['product_code'] = 'PRODUCT-SKU';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('VARIANT-SKU', $normalized['variant']['sku']);
    }

    public function testNormalizeRowFallsBackToProductCode(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_code'] = '';
        $row['product_code'] = 'PRODUCT-SKU';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('PRODUCT-SKU', $normalized['variant']['sku']);
    }

    public function testNormalizeRowVariantPricePriority(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_price'] = '34.99';
        $row['product_price'] = '29.99';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(34.99, $normalized['variant']['price'], 0.001);
    }

    public function testNormalizeRowFallsBackToProductPrice(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_price'] = '';
        $row['product_price'] = '29.99';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
    }

    public function testNormalizeRowVariantQuantityPriority(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_quantity'] = '100';
        $row['product_quantity'] = '45';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(100, $normalized['variant']['stock']);
    }

    public function testNormalizeRowSalePricing(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_sale_price'] = '24.99';
        $row['product_sale_start'] = '2025-12-01';
        $row['product_sale_end'] = '2025-12-31';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(24.99, $normalized['variant']['sale_price'], 0.001);
        $this->assertNotNull($normalized['variant']['sale_start']);
        $this->assertNotNull($normalized['variant']['sale_end']);
    }

    public function testNormalizeRowSalePriceZeroIsIgnored(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_sale_price'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNull($normalized['variant']['sale_price']);
    }

    public function testNormalizeRowCategories(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_categories'] = 'Clothing,T-Shirts,Cotton';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['Clothing', 'T-Shirts', 'Cotton'], $normalized['product']['categories']);
    }

    public function testNormalizeRowImages(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_images'] = 'https://hikashop.example.com/images/img1.jpg,https://hikashop.example.com/images/img2.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([
            'https://hikashop.example.com/images/img1.jpg',
            'https://hikashop.example.com/images/img2.jpg',
        ], $normalized['product']['images']);
    }

    public function testNormalizeRowCharacteristics(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['characteristic_1'] = 'Black';
        $row['characteristic_2'] = 'S';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        // HikaShop infers option names from values
        $this->assertEquals('Color', $normalized['variant']['options'][0]['name']);
        $this->assertEquals('Black', $normalized['variant']['options'][0]['value']);
        $this->assertEquals('Size', $normalized['variant']['options'][1]['name']);
        $this->assertEquals('S', $normalized['variant']['options'][1]['value']);
    }

    public function testNormalizeRowDuplicateOptionNames(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['characteristic_1'] = 'Black';
        $row['characteristic_2'] = 'Navy'; // Both are colors

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        // Second one should be numbered
        $this->assertEquals('Color', $normalized['variant']['options'][0]['name']);
        $this->assertEquals('Color 2', $normalized['variant']['options'][1]['name']);
    }

    public function testNormalizeRowDigitalProduct(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_type'] = 'file';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
        $this->assertTrue($normalized['variant']['is_digital']);
    }

    public function testNormalizeRowDigitalProductTypeDigital(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_type'] = 'digital';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
    }

    public function testNormalizeRowUnpublishedProduct(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_published'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertFalse($normalized['product']['active']);
        $this->assertFalse($normalized['variant']['active']);
    }

    public function testNormalizeRowGeneratesSlugFromTitle(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_alias'] = ''; // Empty alias

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['product']['slug']);
        $this->assertStringContainsString('classic', strtolower($normalized['product']['slug']));
    }

    public function testNormalizeRowUsesVariantIdForOriginalId(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_id'] = '9001';
        $row['product_id'] = '2001';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('9001', $normalized['variant']['original_id']);
    }

    public function testNormalizeRowFallsBackToProductIdForVariantOriginalId(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['variant_id'] = '';
        $row['product_id'] = '2001';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('2001', $normalized['variant']['original_id']);
    }

    public function testNormalizeRowGeneratesSkuWhenMissing(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_code'] = '';
        $row['variant_code'] = '';
        $row['characteristic_1'] = 'Black';
        $row['characteristic_2'] = 'S';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['variant']['sku']);
    }

    public function testNormalizeRowEmptyCharacteristics(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['characteristic_1'] = '';
        $row['characteristic_2'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([], $normalized['variant']['options']);
    }

    public function testNormalizeRowMainProductType(): void
    {
        $row = $this->createBasicHikashopRow();
        $row['product_type'] = 'main';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('physical', $normalized['product']['product_type']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createBasicHikashopRow(): array
    {
        return [
            'product_id'              => '2001',
            'product_code'            => 'TSHIRT-BLK-S',
            'product_name'            => 'Classic Cotton T-Shirt',
            'product_alias'           => 'classic-cotton-tshirt',
            'product_meta_description' => 'Premium organic cotton t-shirt',
            'product_description'     => '<p>Our classic cotton t-shirt is made from 100% organic cotton.</p>',
            'product_published'       => '1',
            'product_categories'      => 'Clothing,T-Shirts',
            'product_images'          => 'https://hikashop.example.com/images/tshirt-main.jpg',
            'product_type'            => 'main',
            'product_price'           => '29.99',
            'variant_price'           => '',
            'product_sale_price'      => '',
            'product_sale_start'      => '',
            'product_sale_end'        => '',
            'product_quantity'        => '45',
            'variant_quantity'        => '',
            'product_weight'          => '0.18',
            'variant_code'            => 'TSHIRT-BLK-S',
            'characteristic_1'        => 'Black',
            'characteristic_2'        => 'S',
            'characteristic_3'        => '',
            'characteristic_4'        => '',
            'characteristic_5'        => '',
            'variant_id'              => '3001',
        ];
    }
}
