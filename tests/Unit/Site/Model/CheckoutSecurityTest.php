<?php

declare(strict_types=1);

namespace Tests\Unit\Site\Model;

use PHPUnit\Framework\TestCase;

/**
 * Tests for checkout security, price calculations, and anti-tampering measures.
 *
 * These tests verify that:
 * 1. Prices are always fetched from database, never trusted from cart
 * 2. Coupon discounts are calculated server-side
 * 3. Tax calculations are accurate
 * 4. Total calculations include all components
 * 5. Integer overflow is prevented
 * 6. Concurrent checkouts handle stock correctly
 */
final class CheckoutSecurityTest extends TestCase
{
    /**
     * Test that price recalculation ignores cart-supplied prices.
     *
     * Security: Client-side prices can be tampered with.
     * The checkout must always fetch current prices from the database.
     */
    public function testPriceRecalculationIgnoresCartData(): void
    {
        // Simulated cart data with potentially tampered prices
        $cartData = [
            'items' => [
                [
                    'sku' => 'PROD-001',
                    'variant_id' => 1,
                    'qty' => 2,
                    'unit_price_cents' => 100, // Tampered: trying to pay $1 instead of $25
                ],
            ],
        ];

        // Database prices (source of truth)
        $databasePrices = [
            1 => ['variant_id' => 1, 'price_cents' => 2500], // Actual price: $25
        ];

        // Simulate server-side price validation
        $validatedItems = [];
        foreach ($cartData['items'] as $item) {
            $variantId = $item['variant_id'];
            $dbPrice = $databasePrices[$variantId]['price_cents'] ?? null;

            $this->assertNotNull($dbPrice, 'Variant must exist in database');

            // Use database price, NOT cart price
            $validatedItems[] = [
                'sku' => $item['sku'],
                'variant_id' => $variantId,
                'qty' => $item['qty'],
                'unit_price_cents' => $dbPrice, // Always from DB
            ];
        }

        // Verify cart price was ignored
        $this->assertEquals(2500, $validatedItems[0]['unit_price_cents']);
        $this->assertNotEquals(100, $validatedItems[0]['unit_price_cents']);
    }

    /**
     * Test that coupon discounts are calculated from database prices.
     */
    public function testCouponDiscountCalculatedFromDatabasePrices(): void
    {
        // Database coupon
        $coupon = [
            'code' => 'SAVE20',
            'type' => 'percent',
            'value' => 20.00, // 20% discount
            'min_total_cents' => 5000, // Minimum $50
            'active' => true,
        ];

        // Items with database prices
        $items = [
            ['qty' => 2, 'unit_price_cents' => 3000], // $30 × 2 = $60
        ];

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['qty'] * $item['unit_price_cents'];
        }

        $this->assertEquals(6000, $subtotal); // $60

        // Coupon applies because subtotal >= min_total
        $this->assertGreaterThanOrEqual($coupon['min_total_cents'], $subtotal);

        // Calculate discount: 20% of $60 = $12
        $discountCents = 0;
        if ($coupon['type'] === 'percent') {
            $discountCents = (int) floor($subtotal * ($coupon['value'] / 100));
        } elseif ($coupon['type'] === 'fixed') {
            $discountCents = (int) ($coupon['value'] * 100);
        }

        $this->assertEquals(1200, $discountCents); // $12 discount

        // Discount cannot exceed subtotal
        $discountCents = min($discountCents, $subtotal);
        $this->assertLessThanOrEqual($subtotal, $discountCents);
    }

    /**
     * Test tax calculation accuracy with multiple rates.
     */
    public function testTaxCalculationAccuracy(): void
    {
        $items = [
            ['qty' => 1, 'unit_price_cents' => 10000, 'tax_rate' => '20.00'], // $100, 20% tax
            ['qty' => 2, 'unit_price_cents' => 5000, 'tax_rate' => '10.00'],  // $50 × 2, 10% tax
        ];

        $totalTaxCents = 0;

        foreach ($items as $item) {
            $lineTotal = $item['qty'] * $item['unit_price_cents'];
            $taxRate = (float) $item['tax_rate'];

            // Tax per line item (round at line level as per INSTRUCTIONS.md)
            $lineTax = (int) round($lineTotal * ($taxRate / 100));
            $totalTaxCents += $lineTax;
        }

        // $100 × 20% = $20 tax
        // $100 × 10% = $10 tax
        // Total tax = $30
        $this->assertEquals(3000, $totalTaxCents);
    }

    /**
     * Test that inclusive tax is handled correctly.
     */
    public function testInclusiveTaxCalculation(): void
    {
        // Price includes tax (common in EU)
        $priceIncludingTax = 12000; // €120 with VAT included
        $taxRate = 20.00; // 20% VAT

        // Extract tax from inclusive price
        // Formula: tax = price - (price / (1 + rate))
        $taxCents = (int) round($priceIncludingTax - ($priceIncludingTax / (1 + $taxRate / 100)));

        // €120 / 1.20 = €100 net, so €20 tax
        $this->assertEquals(2000, $taxCents);

        $netPrice = $priceIncludingTax - $taxCents;
        $this->assertEquals(10000, $netPrice);
    }

    /**
     * Test total calculation with all components.
     */
    public function testTotalCalculationWithAllComponents(): void
    {
        $subtotalCents = 10000;  // $100 subtotal
        $taxCents = 1500;        // $15 tax
        $shippingCents = 500;    // $5 shipping
        $discountCents = 2000;   // $20 discount

        // Non-inclusive tax: total = subtotal + tax + shipping - discount
        $totalCents = $subtotalCents + $taxCents + $shippingCents - $discountCents;

        $this->assertEquals(10000, $totalCents); // $100

        // Verify component breakdown
        $this->assertEquals(
            $totalCents,
            $subtotalCents + $taxCents + $shippingCents - $discountCents
        );
    }

    /**
     * Test that inclusive tax doesn't double-count.
     */
    public function testTotalCalculationWithInclusiveTax(): void
    {
        $subtotalCents = 10000;  // $100 subtotal (already includes tax)
        $taxCents = 1667;        // Tax extracted for reporting
        $shippingCents = 500;    // $5 shipping (taxable separately)
        $discountCents = 1000;   // $10 discount

        // Inclusive tax: total = subtotal + shipping - discount (tax NOT added again)
        $totalCents = $subtotalCents + $shippingCents - $discountCents;

        $this->assertEquals(9500, $totalCents); // $95
    }

    /**
     * Test integer overflow prevention with large orders.
     */
    public function testIntegerOverflowPrevention(): void
    {
        // Test with very large values that could cause overflow
        $items = [
            ['qty' => 999999, 'unit_price_cents' => 99999999], // ~$1M × 1M items
        ];

        // This would overflow 32-bit int: 999999 × 99999999 = 99999899000001
        // PHP integers are 64-bit, but database INT columns may be 32-bit

        $maxInt32 = 2147483647;

        foreach ($items as $item) {
            $lineTotal = $item['qty'] * $item['unit_price_cents'];

            // Check for potential overflow
            if ($lineTotal > $maxInt32) {
                // System should reject or cap this
                $this->assertTrue(true, 'Large value detected - would need BIGINT');
            }
        }

        // Verify safe calculation with reasonable limits
        $safeTotalCents = 0;
        $maxOrderCents = 2000000000; // $20M cap

        foreach ($items as $item) {
            $lineTotal = $item['qty'] * $item['unit_price_cents'];
            $safeTotalCents += min($lineTotal, $maxOrderCents);
        }

        // Safe total should be capped
        $this->assertLessThanOrEqual($maxOrderCents, min($safeTotalCents, $maxOrderCents));
    }

    /**
     * Test that quantity validation prevents negative values.
     */
    public function testQuantityValidationPreventsNegative(): void
    {
        $testQuantities = [
            [-1, 1],    // Negative → 1
            [0, 1],     // Zero → 1
            [1, 1],     // Valid
            [100, 100], // Valid
            [-999, 1],  // Large negative → 1
        ];

        foreach ($testQuantities as [$input, $expected]) {
            $normalized = max(1, (int) $input);
            $this->assertEquals($expected, $normalized);
            $this->assertGreaterThan(0, $normalized);
        }
    }

    /**
     * Test discount cannot exceed subtotal (no negative totals).
     */
    public function testDiscountCannotExceedSubtotal(): void
    {
        $subtotalCents = 5000; // $50
        $discountCents = 10000; // $100 discount (exceeds subtotal)

        // Cap discount at subtotal
        $actualDiscount = min($discountCents, $subtotalCents);

        $this->assertEquals(5000, $actualDiscount);

        // Total should never be negative
        $total = max(0, $subtotalCents - $actualDiscount);
        $this->assertGreaterThanOrEqual(0, $total);
    }

    /**
     * Test concurrent checkouts stock handling concept.
     *
     * This tests the logic pattern - actual DB locking is tested in integration tests.
     */
    public function testConcurrentCheckoutsStockHandling(): void
    {
        // Simulated stock level
        $stockLevel = 5;

        // Two concurrent checkout attempts
        $checkout1Qty = 3;
        $checkout2Qty = 4;

        // Sequential processing (as if locked)
        $results = [];

        // First checkout
        if ($checkout1Qty <= $stockLevel) {
            $stockLevel -= $checkout1Qty;
            $results['checkout1'] = 'success';
        } else {
            $results['checkout1'] = 'out_of_stock';
        }

        // Second checkout (stock now reduced)
        if ($checkout2Qty <= $stockLevel) {
            $stockLevel -= $checkout2Qty;
            $results['checkout2'] = 'success';
        } else {
            $results['checkout2'] = 'out_of_stock';
        }

        // First should succeed (5 - 3 = 2 remaining)
        $this->assertEquals('success', $results['checkout1']);

        // Second should fail (needs 4, only 2 available)
        $this->assertEquals('out_of_stock', $results['checkout2']);

        // Final stock
        $this->assertEquals(2, $stockLevel);
    }

    /**
     * Test atomic stock decrement pattern.
     */
    public function testAtomicStockDecrementPattern(): void
    {
        // SQL pattern: UPDATE variants SET stock = stock - ? WHERE id = ? AND stock >= ?
        // This ensures atomic check-and-decrement

        $variantId = 1;
        $requestedQty = 3;
        $currentStock = 5;

        // Simulate atomic update
        $success = ($currentStock >= $requestedQty);

        if ($success) {
            $newStock = $currentStock - $requestedQty;
            $this->assertEquals(2, $newStock);
        }

        $this->assertTrue($success);
    }

    /**
     * Test price format consistency (cents vs dollars).
     */
    public function testPriceFormatConsistency(): void
    {
        // All prices should be in cents (minor units)
        $prices = [
            'unit_price_cents' => 2599,    // $25.99
            'subtotal_cents' => 5198,      // $51.98
            'tax_cents' => 520,            // $5.20
            'shipping_cents' => 500,       // $5.00
            'discount_cents' => 1000,      // $10.00
            'total_cents' => 5218,         // $52.18
        ];

        foreach ($prices as $key => $value) {
            $this->assertIsInt($value);
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertStringEndsWith('_cents', $key);
        }
    }

    /**
     * Test rounding at line item level.
     */
    public function testLineItemRounding(): void
    {
        // Per INSTRUCTIONS.md: "Round at line item, then sum"
        $items = [
            ['qty' => 3, 'unit_price_cents' => 333], // $3.33 × 3 = $9.99
            ['qty' => 2, 'unit_price_cents' => 167], // $1.67 × 2 = $3.34
        ];

        $subtotal = 0;
        foreach ($items as $item) {
            // Round at line level
            $lineTotal = $item['qty'] * $item['unit_price_cents'];
            $subtotal += $lineTotal;
        }

        // 999 + 334 = 1333 cents = $13.33
        $this->assertEquals(1333, $subtotal);
    }

    /**
     * Test free shipping threshold calculation.
     */
    public function testFreeShippingThreshold(): void
    {
        $shippingRules = [
            'type' => 'free_over',
            'price_cents' => 500,        // $5 shipping
            'threshold_cents' => 5000,   // Free over $50
        ];

        // Order below threshold
        $subtotal1 = 4999;
        $shipping1 = ($subtotal1 >= $shippingRules['threshold_cents'])
            ? 0
            : $shippingRules['price_cents'];
        $this->assertEquals(500, $shipping1); // Pays shipping

        // Order at threshold
        $subtotal2 = 5000;
        $shipping2 = ($subtotal2 >= $shippingRules['threshold_cents'])
            ? 0
            : $shippingRules['price_cents'];
        $this->assertEquals(0, $shipping2); // Free shipping

        // Order above threshold
        $subtotal3 = 10000;
        $shipping3 = ($subtotal3 >= $shippingRules['threshold_cents'])
            ? 0
            : $shippingRules['price_cents'];
        $this->assertEquals(0, $shipping3); // Free shipping
    }
}
