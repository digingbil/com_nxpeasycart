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

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\WoocommerceAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the WoocommerceAdapter class.
 *
 * @since 0.3.0
 */
final class WoocommerceAdapterTest extends TestCase
{
    private WoocommerceAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new WoocommerceAdapter();
    }

    // ========================================================================
    // ADAPTER IDENTITY TESTS
    // ========================================================================

    public function testGetNameReturnsWoocommerce(): void
    {
        $this->assertEquals('woocommerce', $this->adapter->getName());
    }

    public function testGetDisplayNameIsUserFriendly(): void
    {
        $this->assertEquals('WooCommerce', $this->adapter->getDisplayName());
    }

    public function testGetWeightUnitIsKilograms(): void
    {
        $this->assertEquals('kg', $this->adapter->getWeightUnit());
    }

    public function testGetCategorySeparatorIsGreaterThan(): void
    {
        $this->assertEquals('>', $this->adapter->getCategorySeparator());
    }

    public function testShouldGroupVariantsIsTrue(): void
    {
        $this->assertTrue($this->adapter->shouldGroupVariants());
    }

    public function testGetGroupingColumnIsName(): void
    {
        $this->assertEquals('Name', $this->adapter->getGroupingColumn());
    }

    // ========================================================================
    // SIGNATURE HEADERS TESTS
    // ========================================================================

    public function testGetSignatureHeadersContainsWooCommerceSpecificColumns(): void
    {
        $headers = $this->adapter->getSignatureHeaders();

        $this->assertContains('ID', $headers);
        $this->assertContains('Type', $headers);
        $this->assertContains('SKU', $headers);
        $this->assertContains('Name', $headers);
        $this->assertContains('Published', $headers);
        $this->assertContains('Regular price', $headers);
    }

    // ========================================================================
    // ROW NORMALIZATION TESTS
    // ========================================================================

    public function testNormalizeRowSimpleProduct(): void
    {
        $row = $this->createSimpleProductRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('101', $normalized['product']['original_id']);
        $this->assertEquals('Classic Cotton T-Shirt - Black Small', $normalized['product']['title']);
        $this->assertNotEmpty($normalized['product']['slug']);
        $this->assertStringContainsString('Premium organic cotton', $normalized['product']['short_desc']);
        $this->assertStringContainsString('100% organic cotton', $normalized['product']['long_desc']);
        $this->assertTrue($normalized['product']['active']);
        $this->assertTrue($normalized['product']['featured']);
        $this->assertEquals('physical', $normalized['product']['product_type']);
    }

    public function testNormalizeRowVariantFields(): void
    {
        $row = $this->createSimpleProductRow();
        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('101', $normalized['variant']['original_id']);
        $this->assertEquals('TSHIRT-BLK-S', $normalized['variant']['sku']);
        $this->assertEqualsWithDelta(29.99, $normalized['variant']['price'], 0.001);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(45, $normalized['variant']['stock']);
        $this->assertEqualsWithDelta(0.18, $normalized['variant']['weight'], 0.001);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
    }

    public function testNormalizeRowSkipsVariableProducts(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Type'] = 'variable';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertTrue($normalized['_skip']);
        $this->assertStringContainsString('Variable', $normalized['_skip_reason']);
    }

    public function testNormalizeRowSalePricing(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Sale price'] = '24.99';
        $row['Date sale price starts'] = '2025-12-01';
        $row['Date sale price ends'] = '2025-12-31';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEqualsWithDelta(24.99, $normalized['variant']['sale_price'], 0.001);
        $this->assertNotNull($normalized['variant']['sale_start']);
        $this->assertNotNull($normalized['variant']['sale_end']);
    }

    public function testNormalizeRowEmptySalePrice(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Sale price'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNull($normalized['variant']['sale_price']);
    }

    public function testNormalizeRowCategories(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Categories'] = 'Clothing > T-Shirts, Apparel > Casual';

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should extract leaf categories (T-Shirts, Casual)
        $this->assertContains('T-Shirts', $normalized['product']['categories']);
        $this->assertContains('Casual', $normalized['product']['categories']);
    }

    public function testNormalizeRowAttributes(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Attribute 1 name'] = 'Color';
        $row['Attribute 1 value(s)'] = 'Black';
        $row['Attribute 2 name'] = 'Size';
        $row['Attribute 2 value(s)'] = 'S';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertCount(2, $normalized['variant']['options']);
        $this->assertEquals(['name' => 'Color', 'value' => 'Black'], $normalized['variant']['options'][0]);
        $this->assertEquals(['name' => 'Size', 'value' => 'S'], $normalized['variant']['options'][1]);
    }

    public function testNormalizeRowAttributeWithMultipleValues(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Attribute 1 name'] = 'Color';
        $row['Attribute 1 value(s)'] = 'Black|White|Red'; // Pipe-separated
        $row['Attribute 2 name'] = '';
        $row['Attribute 2 value(s)'] = '';
        $row['Attribute 3 name'] = '';
        $row['Attribute 3 value(s)'] = '';

        $normalized = $this->adapter->normalizeRow($row, []);

        // Should take only the first value from pipe-separated values
        $this->assertCount(1, $normalized['variant']['options']);
        $this->assertEquals('Black', $normalized['variant']['options'][0]['value']);
    }

    public function testNormalizeRowDigitalProduct(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Download 1 URL'] = 'https://example.com/file.pdf';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
        $this->assertTrue($normalized['variant']['is_digital']);
    }

    public function testNormalizeRowVirtualProduct(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Virtual'] = '1';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
    }

    public function testNormalizeRowDownloadableProduct(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Downloadable'] = '1';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals('digital', $normalized['product']['product_type']);
    }

    public function testNormalizeRowUnpublishedProduct(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Published'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertFalse($normalized['product']['active']);
        $this->assertFalse($normalized['variant']['active']);
    }

    public function testNormalizeRowInStockFlagOnly(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Stock'] = '';
        $row['In stock?'] = '1';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(999, $normalized['variant']['stock']);
    }

    public function testNormalizeRowOutOfStockFlagOnly(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Stock'] = '';
        $row['In stock?'] = '0';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals(0, $normalized['variant']['stock']);
    }

    public function testNormalizeRowImages(): void
    {
        $row = $this->createSimpleProductRow();
        $row['Images'] = 'https://example.com/img1.jpg,https://example.com/img2.jpg';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertEquals([
            'https://example.com/img1.jpg',
            'https://example.com/img2.jpg',
        ], $normalized['product']['images']);
    }

    public function testNormalizeRowGeneratesSkuWhenMissing(): void
    {
        $row = $this->createSimpleProductRow();
        $row['SKU'] = '';
        $row['Attribute 1 name'] = 'Color';
        $row['Attribute 1 value(s)'] = 'Black';

        $normalized = $this->adapter->normalizeRow($row, []);

        $this->assertNotEmpty($normalized['variant']['sku']);
        $this->assertStringContainsString('black', strtolower($normalized['variant']['sku']));
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function createSimpleProductRow(): array
    {
        return [
            'ID'                       => '101',
            'Type'                     => 'simple',
            'SKU'                      => 'TSHIRT-BLK-S',
            'Name'                     => 'Classic Cotton T-Shirt - Black Small',
            'Published'                => '1',
            'Is featured?'             => '1',
            'Short description'        => 'Premium organic cotton t-shirt',
            'Description'              => '<p>Our classic t-shirt is made from 100% organic cotton.</p>',
            'Sale price'               => '',
            'Date sale price starts'   => '',
            'Date sale price ends'     => '',
            'Regular price'            => '29.99',
            'Categories'               => 'Clothing > T-Shirts',
            'Images'                   => 'https://store.example.com/images/tshirt-black-s.jpg',
            'Stock'                    => '45',
            'In stock?'                => '1',
            'Weight (kg)'              => '0.18',
            'Attribute 1 name'         => 'Color',
            'Attribute 1 value(s)'     => 'Black',
            'Attribute 2 name'         => 'Size',
            'Attribute 2 value(s)'     => 'S',
            'Attribute 3 name'         => '',
            'Attribute 3 value(s)'     => '',
            'Parent'                   => '',
            'Download 1 URL'           => '',
            'Downloadable'             => '0',
            'Virtual'                  => '0',
        ];
    }
}
