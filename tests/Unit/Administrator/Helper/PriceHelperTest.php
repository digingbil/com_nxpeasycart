<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  Tests
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Tests\Unit\Administrator\Helper;

use PHPUnit\Framework\TestCase;
use Joomla\Component\Nxpeasycart\Administrator\Helper\PriceHelper;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Unit tests for PriceHelper class.
 *
 * @since 0.2.0
 */
class PriceHelperTest extends TestCase
{
    /**
     * Test resolve() with no sale price set.
     */
    public function testResolveNoSalePrice(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertEquals(1000, $result['regular_price_cents']);
        $this->assertNull($result['sale_price_cents']);
        $this->assertFalse($result['is_on_sale']);
        $this->assertFalse($result['sale_active']);
        $this->assertNull($result['discount_percent']);
    }

    /**
     * Test resolve() with sale price during active sale period.
     */
    public function testResolveSaleActiveDuringPeriod(): void
    {
        $now = new DateTimeImmutable('2025-01-15 12:00:00', new DateTimeZone('UTC'));

        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => '2025-01-01 00:00:00',
            'sale_end'         => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(750, $result['effective_price_cents']);
        $this->assertEquals(1000, $result['regular_price_cents']);
        $this->assertEquals(750, $result['sale_price_cents']);
        $this->assertTrue($result['is_on_sale']);
        $this->assertTrue($result['sale_active']);
        $this->assertEquals(25.0, $result['discount_percent']);
    }

    /**
     * Test resolve() when sale has not yet started.
     */
    public function testResolveSaleNotYetStarted(): void
    {
        $now = new DateTimeImmutable('2024-12-15 12:00:00', new DateTimeZone('UTC'));

        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => '2025-01-01 00:00:00',
            'sale_end'         => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertEquals(1000, $result['regular_price_cents']);
        $this->assertEquals(750, $result['sale_price_cents']);
        $this->assertTrue($result['is_on_sale']); // Sale exists
        $this->assertFalse($result['sale_active']); // But not active yet
        $this->assertNull($result['discount_percent']);
    }

    /**
     * Test resolve() when sale has expired.
     */
    public function testResolveSaleExpired(): void
    {
        $now = new DateTimeImmutable('2025-02-15 12:00:00', new DateTimeZone('UTC'));

        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => '2025-01-01 00:00:00',
            'sale_end'         => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertEquals(1000, $result['regular_price_cents']);
        $this->assertEquals(750, $result['sale_price_cents']);
        $this->assertTrue($result['is_on_sale']); // Sale exists
        $this->assertFalse($result['sale_active']); // But expired
        $this->assertNull($result['discount_percent']);
    }

    /**
     * Test resolve() with sale price but no start date (immediate activation).
     */
    public function testResolveSaleNoStartDate(): void
    {
        $now = new DateTimeImmutable('2025-01-15 12:00:00', new DateTimeZone('UTC'));

        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => null,
            'sale_end'         => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(750, $result['effective_price_cents']);
        $this->assertTrue($result['sale_active']);
    }

    /**
     * Test resolve() with sale price but no end date (never expires).
     */
    public function testResolveSaleNoEndDate(): void
    {
        $now = new DateTimeImmutable('2025-12-15 12:00:00', new DateTimeZone('UTC'));

        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => '2025-01-01 00:00:00',
            'sale_end'         => null,
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(750, $result['effective_price_cents']);
        $this->assertTrue($result['sale_active']);
    }

    /**
     * Test resolve() with evergreen sale (no start or end dates).
     */
    public function testResolveSaleEvergreen(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 750,
            'sale_start'       => null,
            'sale_end'         => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(750, $result['effective_price_cents']);
        $this->assertTrue($result['sale_active']);
    }

    /**
     * Test resolve() with object input instead of array.
     */
    public function testResolveWithObjectInput(): void
    {
        $variant = (object) [
            'price_cents'      => 2000,
            'sale_price_cents' => 1500,
            'sale_start'       => null,
            'sale_end'         => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(1500, $result['effective_price_cents']);
        $this->assertTrue($result['sale_active']);
    }

    /**
     * Test resolve() with empty string sale price.
     */
    public function testResolveWithEmptyStringSalePrice(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => '',
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertFalse($result['is_on_sale']);
        $this->assertFalse($result['sale_active']);
    }

    /**
     * Test isSaleActive() static method.
     */
    public function testIsSaleActiveStatic(): void
    {
        $now = new DateTimeImmutable('2025-01-15 12:00:00', new DateTimeZone('UTC'));

        // Active window
        $this->assertTrue(
            PriceHelper::isSaleActive('2025-01-01 00:00:00', '2025-01-31 23:59:59', $now)
        );

        // Not started yet
        $this->assertFalse(
            PriceHelper::isSaleActive('2025-02-01 00:00:00', '2025-02-28 23:59:59', $now)
        );

        // Already expired
        $this->assertFalse(
            PriceHelper::isSaleActive('2024-12-01 00:00:00', '2024-12-31 23:59:59', $now)
        );

        // No restrictions (both null)
        $this->assertTrue(
            PriceHelper::isSaleActive(null, null, $now)
        );
    }

    /**
     * Test computePriceRange() with mixed variants.
     */
    public function testComputePriceRangeMixedVariants(): void
    {
        $now = new DateTimeImmutable('2025-01-15 12:00:00', new DateTimeZone('UTC'));

        $variants = [
            [
                'price_cents'      => 1000,
                'sale_price_cents' => 800,
                'sale_start'       => null,
                'sale_end'         => null,
            ],
            [
                'price_cents'      => 1500,
                'sale_price_cents' => null,
                'sale_start'       => null,
                'sale_end'         => null,
            ],
            [
                'price_cents'      => 2000,
                'sale_price_cents' => 1200,
                'sale_start'       => '2025-02-01 00:00:00', // Not active yet
                'sale_end'         => '2025-02-28 23:59:59',
            ],
        ];

        // Note: We can't easily pass $now to computePriceRange without modification
        // For this test, we assume current time makes the third variant not active
        $result = PriceHelper::computePriceRange($variants, 'USD');

        $this->assertTrue($result['has_sale']);
        $this->assertEquals('USD', $result['currency']);
    }

    /**
     * Test computePriceRange() with empty variants.
     */
    public function testComputePriceRangeEmpty(): void
    {
        $result = PriceHelper::computePriceRange([], 'EUR');

        $this->assertEquals(0, $result['min_cents']);
        $this->assertEquals(0, $result['max_cents']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertFalse($result['has_sale']);
    }

    /**
     * Test hasActiveSale() with no active sales.
     */
    public function testHasActiveSaleNone(): void
    {
        $variants = [
            [
                'price_cents'      => 1000,
                'sale_price_cents' => null,
            ],
            [
                'price_cents'      => 1500,
                'sale_price_cents' => null,
            ],
        ];

        $this->assertFalse(PriceHelper::hasActiveSale($variants));
    }

    /**
     * Test hasActiveSale() with active sale.
     */
    public function testHasActiveSaleWithActive(): void
    {
        $variants = [
            [
                'price_cents'      => 1000,
                'sale_price_cents' => null,
            ],
            [
                'price_cents'      => 1500,
                'sale_price_cents' => 1200,
                'sale_start'       => null,
                'sale_end'         => null,
            ],
        ];

        $this->assertTrue(PriceHelper::hasActiveSale($variants));
    }

    /**
     * Test getSaleStatus() with no sale.
     */
    public function testGetSaleStatusNone(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => null,
        ];

        $result = PriceHelper::getSaleStatus($variant);

        $this->assertEquals('none', $result['status']);
        $this->assertFalse($result['ends_soon']);
    }

    /**
     * Test getSaleStatus() with active sale.
     */
    public function testGetSaleStatusActive(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 800,
            'sale_start'       => null,
            'sale_end'         => null,
        ];

        $result = PriceHelper::getSaleStatus($variant);

        $this->assertEquals('active', $result['status']);
    }

    /**
     * Test discount percent calculation with 50% discount.
     */
    public function testDiscountPercentCalculation(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 500,
            'sale_start'       => null,
            'sale_end'         => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(50.0, $result['discount_percent']);
    }

    /**
     * Test discount percent is null when sale price equals regular price.
     */
    public function testDiscountPercentNullWhenEqual(): void
    {
        $variant = [
            'price_cents'      => 1000,
            'sale_price_cents' => 1000,
            'sale_start'       => null,
            'sale_end'         => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertNull($result['discount_percent']);
    }
}
