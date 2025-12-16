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

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\NativeAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NativeAdapter class.
 *
 * @since 0.3.0
 */
final class NativeAdapterTest extends TestCase
{
    private NativeAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new NativeAdapter();
    }

    // ========================================================================
    // ADAPTER IDENTITY TESTS
    // ========================================================================

    public function testGetNameReturnsNative(): void
    {
        $this->assertEquals('native', $this->adapter->getName());
    }

    public function testGetDisplayNameIsUserFriendly(): void
    {
        $displayName = $this->adapter->getDisplayName();
        $this->assertStringContainsString('NXP', $displayName);
        $this->assertStringContainsString('Easy Cart', $displayName);
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

    public function testGetSignatureHeadersContainsUniqueColumns(): void
    {
        $headers = $this->adapter->getSignatureHeaders();

        $this->assertContains('product_id', $headers);
        $this->assertContains('product_slug', $headers);
        $this->assertContains('variant_id', $headers);
        $this->assertContains('sku', $headers);
    }

    // ========================================================================
    // DEFAULT MAPPING TESTS
    // ========================================================================

    public function testGetDefaultMappingContainsAllProductFields(): void
    {
        $mapping = $this->adapter->getDefaultMapping();

        $this->assertArrayHasKey('product_id', $mapping);
        $this->assertArrayHasKey('product_slug', $mapping);
        $this->assertArrayHasKey('product_title', $mapping);
        $this->assertArrayHasKey('short_desc', $mapping);
        $this->assertArrayHasKey('long_desc', $mapping);
        $this->assertArrayHasKey('product_type', $mapping);
        $this->assertArrayHasKey('featured', $mapping);
        $this->assertArrayHasKey('active', $mapping);
        $this->assertArrayHasKey('categories', $mapping);
        $this->assertArrayHasKey('images', $mapping);
    }

    public function testGetDefaultMappingContainsAllVariantFields(): void
    {
        $mapping = $this->adapter->getDefaultMapping();

        $this->assertArrayHasKey('variant_id', $mapping);
        $this->assertArrayHasKey('sku', $mapping);
        $this->assertArrayHasKey('price', $mapping);
        $this->assertArrayHasKey('sale_price', $mapping);
        $this->assertArrayHasKey('sale_start', $mapping);
        $this->assertArrayHasKey('sale_end', $mapping);
        $this->assertArrayHasKey('currency', $mapping);
        $this->assertArrayHasKey('stock', $mapping);
        $this->assertArrayHasKey('weight', $mapping);
        $this->assertArrayHasKey('ean', $mapping);
        $this->assertArrayHasKey('is_digital', $mapping);
        $this->assertArrayHasKey('variant_active', $mapping);
        $this->assertArrayHasKey('options', $mapping);
        $this->assertArrayHasKey('original_images', $mapping);
    }

    // ========================================================================
    // ROW NORMALIZATION TESTS
    // ========================================================================

    public function testNormalizeRowBasicProduct(): void
    {
        $row = $this->createBasicRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        // Product fields
        $this->assertEquals('1', $normalized['product']['original_id']);
        $this->assertEquals('classic-cotton-tshirt', $normalized['product']['slug']);
        $this->assertEquals('Classic Cotton T-Shirt', $normalized['product']['title']);
        $this->assertEquals('Premium organic cotton t-shirt', $normalized['product']['short_desc']);
        $this->assertStringContainsString('100% organic cotton', $normalized['product']['long_desc']);
        $this->assertEquals('physical', $normalized['product']['product_type']);
        $this->assertTrue($normalized['product']['featured']);
        $this->assertTrue($normalized['product']['active']);
    }

    public function testNormalizeRowVariantFields(): void
    {
        $row = $this->createBasicRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        // Variant fields
        $this->assertEquals('1', $normalized['variant']['original_id']);
        $this->assertEquals('TSHIRT-BLK-S', $normalized['variant']['sku']);
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(45, $normalized['variant']['stock']);
        $this->assertEqualsWithDelta(0.180, $normalized['variant']['weight'], 0.001);
        $this->assertEquals('5901234123457', $normalized['variant']['ean']);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
    }

    public function testNormalizeRowSalePricing(): void
    {
        $row = $this->createBasicRow();
        $row['sale_price'] = '24.99';
        $row['sale_start'] = '2025-12-01 00:00:00';
        $row['sale_end'] = '2025-12-31 23:59:59';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(24.99, $normalized['variant']['sale_price'], 0.001);
        $this->assertEquals('2025-12-01 00:00:00', $normalized['variant']['sale_start']);
        $this->assertEquals('2025-12-31 23:59:59', $normalized['variant']['sale_end']);
    }

    public function testNormalizeRowEmptySalePrice(): void
    {
        $row = $this->createBasicRow();
        $row['sale_price'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNull($normalized['variant']['sale_price']);
        $this->assertNull($normalized['variant']['sale_start']);
        $this->assertNull($normalized['variant']['sale_end']);
    }

    public function testNormalizeRowCategories(): void
    {
        $row = $this->createBasicRow();
        $row['categories'] = 'Clothing,T-Shirts,Cotton';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(['Clothing', 'T-Shirts', 'Cotton'], $normalized['product']['categories']);
    }

    public function testNormalizeRowImages(): void
    {
        $row = $this->createBasicRow();
        $row['images'] = 'https://example.com/img1.jpg,https://example.com/img2.jpg';
        $row['original_images'] = 'https://example.com/variant1.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([
            'https://example.com/img1.jpg',
            'https://example.com/img2.jpg',
        ], $normalized['product']['images']);
        $this->assertEquals([
            'https://example.com/variant1.jpg',
        ], $normalized['variant']['original_images']);
    }

    public function testNormalizeRowOptions(): void
    {
        $row = $this->createBasicRow();
        $row['options'] = 'Color:Black|Size:S';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        $this->assertEquals(['name' => 'Color', 'value' => 'Black'], $normalized['variant']['options'][0]);
        $this->assertEquals(['name' => 'Size', 'value' => 'S'], $normalized['variant']['options'][1]);
    }

    public function testNormalizeRowEmptyOptions(): void
    {
        $row = $this->createBasicRow();
        $row['options'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([], $normalized['variant']['options']);
    }

    public function testNormalizeRowInvalidOption(): void
    {
        $row = $this->createBasicRow();
        $row['options'] = 'InvalidWithoutColon|Color:Black';

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should skip invalid option
        $this->assertCount(1, $normalized['variant']['options']);
        $this->assertEquals(['name' => 'Color', 'value' => 'Black'], $normalized['variant']['options'][0]);
    }

    public function testNormalizeRowDigitalProduct(): void
    {
        $row = $this->createBasicRow();
        $row['product_type'] = 'digital';
        $row['is_digital'] = '1';
        $row['weight'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
        $this->assertTrue($normalized['variant']['is_digital']);
        $this->assertEqualsWithDelta(0.0, $normalized['variant']['weight'], 0.001);
    }

    public function testNormalizeRowUnpublishedProduct(): void
    {
        $row = $this->createBasicRow();
        $row['active'] = '0';
        $row['variant_active'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertFalse($normalized['product']['active']);
        $this->assertFalse($normalized['variant']['active']);
    }

    public function testNormalizeRowGeneratesSlugFromTitle(): void
    {
        $row = $this->createBasicRow();
        $row['product_slug'] = ''; // Empty slug

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['product']['slug']);
        $this->assertStringContainsString('classic', strtolower($normalized['product']['slug']));
    }

    public function testNormalizeRowPreservesProvidedSlug(): void
    {
        $row = $this->createBasicRow();
        $row['product_slug'] = 'custom-slug-here';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('custom-slug-here', $normalized['product']['slug']);
    }

    public function testNormalizeRowInvalidEan(): void
    {
        $row = $this->createBasicRow();
        $row['ean'] = 'invalid-ean';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNull($normalized['variant']['ean']);
    }

    public function testNormalizeRowValidEan8(): void
    {
        $row = $this->createBasicRow();
        $row['ean'] = '96385074'; // Valid EAN-8

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('96385074', $normalized['variant']['ean']);
    }

    public function testNormalizeRowDefaultCurrency(): void
    {
        $row = $this->createBasicRow();
        $row['currency'] = ''; // Empty currency

        $this->adapter->setDefaultCurrency('USD');
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('USD', $normalized['variant']['currency']);
    }

    public function testNormalizeRowCurrencyUppercased(): void
    {
        $row = $this->createBasicRow();
        $row['currency'] = 'gbp';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('GBP', $normalized['variant']['currency']);
    }

    public function testNormalizeRowSanitizesHtml(): void
    {
        $row = $this->createBasicRow();
        $row['short_desc'] = '   Short desc with spaces   ';
        $row['long_desc'] = '  &amp; HTML entities &lt;p&gt;  ';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('Short desc with spaces', $normalized['product']['short_desc']);
        $this->assertEquals('& HTML entities <p>', $normalized['product']['long_desc']);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createBasicRow(): array
    {
        return [
            'product_id'     => '1',
            'product_slug'   => 'classic-cotton-tshirt',
            'product_title'  => 'Classic Cotton T-Shirt',
            'short_desc'     => 'Premium organic cotton t-shirt',
            'long_desc'      => '<p>Our classic cotton t-shirt is made from 100% organic cotton.</p>',
            'product_type'   => 'physical',
            'featured'       => '1',
            'active'         => '1',
            'categories'     => 'Clothing,T-Shirts',
            'images'         => 'https://example.com/images/tshirt-main.jpg',
            'variant_id'     => '1',
            'sku'            => 'TSHIRT-BLK-S',
            'price'          => '29.99',
            'sale_price'     => '',
            'sale_start'     => '',
            'sale_end'       => '',
            'currency'       => 'EUR',
            'stock'          => '45',
            'weight'         => '0.180',
            'ean'            => '5901234123457',
            'is_digital'     => '0',
            'variant_active' => '1',
            'options'        => 'Color:Black|Size:S',
            'original_images' => '',
        ];
    }
}
