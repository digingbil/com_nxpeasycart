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

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\VirtuemartAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the VirtuemartAdapter class.
 *
 * @since 0.3.0
 */
final class VirtuemartAdapterTest extends TestCase
{
    private VirtuemartAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new VirtuemartAdapter();
    }

    // ========================================================================
    // ADAPTER IDENTITY TESTS
    // ========================================================================

    public function testGetNameReturnsVirtuemart(): void
    {
        $this->assertEquals('virtuemart', $this->adapter->getName());
    }

    public function testGetDisplayNameIsUserFriendly(): void
    {
        $this->assertEquals('VirtueMart', $this->adapter->getDisplayName());
    }

    public function testGetWeightUnitIsKilograms(): void
    {
        $this->assertEquals('kg', $this->adapter->getWeightUnit());
    }

    public function testGetCategorySeparatorIsPipe(): void
    {
        $this->assertEquals('|', $this->adapter->getCategorySeparator());
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

    public function testGetSignatureHeadersContainsVirtueMartSpecificColumns(): void
    {
        $headers = $this->adapter->getSignatureHeaders();

        $this->assertContains('product_id', $headers);
        $this->assertContains('product_sku', $headers);
        $this->assertContains('product_name', $headers);
        $this->assertContains('product_price', $headers);
        $this->assertContains('product_in_stock', $headers);
    }

    // ========================================================================
    // ROW NORMALIZATION TESTS
    // ========================================================================

    public function testNormalizeRowBasicProduct(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('1001', $normalized['product']['original_id']);
        $this->assertEquals('Classic Cotton T-Shirt', $normalized['product']['title']);
        $this->assertEquals('classic-cotton-tshirt', $normalized['product']['slug']);
        $this->assertStringContainsString('Premium organic cotton', $normalized['product']['short_desc']);
        $this->assertStringContainsString('100% organic cotton', $normalized['product']['long_desc']);
        $this->assertTrue($normalized['product']['active']);
        $this->assertTrue($normalized['product']['featured']);
        $this->assertEquals('physical', $normalized['product']['product_type']);
    }

    public function testNormalizeRowVariantFields(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('1001', $normalized['variant']['original_id']); // Uses child_id or product_id
        $this->assertEquals('TSHIRT-BLK-S', $normalized['variant']['sku']);
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(45, $normalized['variant']['stock']);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
    }

    public function testNormalizeRowPipeSeparatedCategories(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['categories'] = 'Clothing|T-Shirts|Cotton';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['Clothing', 'T-Shirts', 'Cotton'], $normalized['product']['categories']);
    }

    public function testNormalizeRowUseCategoryPathAsFallback(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['categories'] = '';
        $row['category_path'] = 'Apparel|Shirts';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['Apparel', 'Shirts'], $normalized['product']['categories']);
    }

    public function testNormalizeRowPipeSeparatedImages(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['file_url'] = 'https://example.com/img1.jpg|https://example.com/img2.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([
            'https://example.com/img1.jpg',
            'https://example.com/img2.jpg',
        ], $normalized['product']['images']);
    }

    public function testNormalizeRowCustomfields(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['customfields'] = 'color:Black|size:S';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        $this->assertEquals(['name' => 'Color', 'value' => 'Black'], $normalized['variant']['options'][0]);
        $this->assertEquals(['name' => 'Size', 'value' => 'S'], $normalized['variant']['options'][1]);
    }

    public function testNormalizeRowOverridePrice(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_price'] = '29.99';
        $row['product_override_price'] = '24.99'; // Lower than regular

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEqualsWithDelta(24.99, $normalized['variant']['sale_price'], 0.001);
    }

    public function testNormalizeRowOverridePriceNotLower(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_price'] = '29.99';
        $row['product_override_price'] = '34.99'; // Higher than regular

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertNull($normalized['variant']['sale_price']);
    }

    public function testNormalizeRowWeightConversionGrams(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_weight'] = '180';
        $row['product_weight_uom'] = 'G';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(0.180, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowWeightConversionPounds(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_weight'] = '2';
        $row['product_weight_uom'] = 'LB';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(0.907, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowWeightConversionOunces(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_weight'] = '16';
        $row['product_weight_uom'] = 'OZ';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(0.454, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowWeightDefaultsToKg(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_weight'] = '1.5';
        $row['product_weight_uom'] = 'KG';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(1.5, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowGeneratesSlugFromTitle(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['slug'] = ''; // Empty slug

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['product']['slug']);
        $this->assertStringContainsString('classic', strtolower($normalized['product']['slug']));
    }

    public function testNormalizeRowUsesChildIdForVariantOriginalId(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['child_id'] = '5001';
        $row['product_id'] = '1001';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('5001', $normalized['variant']['original_id']);
    }

    public function testNormalizeRowFallsBackToProductIdForVariantOriginalId(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['child_id'] = '';
        $row['product_id'] = '1001';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('1001', $normalized['variant']['original_id']);
    }

    public function testNormalizeRowUnpublishedProduct(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['published'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertFalse($normalized['product']['active']);
        $this->assertFalse($normalized['variant']['active']);
    }

    public function testNormalizeRowGeneratesSkuWhenMissing(): void
    {
        $row = $this->createBasicVirtueMartRow();
        $row['product_sku'] = '';
        $row['product_id'] = '12345';
        $row['child_id'] = '12345';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['variant']['sku']);
        // SKU is generated from slug + variant original_id
        $this->assertStringContainsString('classic', strtolower($normalized['variant']['sku']));
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createBasicVirtueMartRow(): array
    {
        return [
            'product_id'             => '1001',
            'product_sku'            => 'TSHIRT-BLK-S',
            'product_name'           => 'Classic Cotton T-Shirt',
            'slug'                   => 'classic-cotton-tshirt',
            'product_s_desc'         => 'Premium organic cotton t-shirt',
            'product_desc'           => '<p>Our classic cotton t-shirt is made from 100% organic cotton.</p>',
            'published'              => '1',
            'product_special'        => '1',
            'categories'             => 'Clothing|T-Shirts',
            'category_path'          => '',
            'file_url'               => 'https://vm.example.com/images/tshirt-main.jpg',
            'file_url_thumb'         => '',
            'product_price'          => '29.99',
            'product_override_price' => '',
            'product_in_stock'       => '45',
            'product_weight'         => '0.18',
            'product_weight_uom'     => 'KG',
            'customfields'           => 'color:Black|size:S',
            'child_id'               => '1001',
        ];
    }
}
