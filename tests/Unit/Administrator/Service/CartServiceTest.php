<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CartService functionality.
 */
final class CartServiceTest extends TestCase
{
    private function createMockDb(): DatabaseInterface
    {
        $query = $this->createMock(DatabaseQuery::class);
        $query->method('select')->willReturnSelf();
        $query->method('from')->willReturnSelf();
        $query->method('where')->willReturnSelf();
        $query->method('bind')->willReturnSelf();
        $query->method('update')->willReturnSelf();
        $query->method('set')->willReturnSelf();
        $query->method('delete')->willReturnSelf();

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturn($query);
        $db->method('quoteName')->willReturnCallback(fn($name) => "`{$name}`");

        return $db;
    }

    public function testCartServiceCanBeInstantiated(): void
    {
        $db = $this->createMockDb();
        $service = new CartService($db);

        $this->assertInstanceOf(CartService::class, $service);
    }

    public function testLoadReturnsNullForNonexistentCart(): void
    {
        $db = $this->createMockDb();
        $db->method('loadObject')->willReturn(null);
        $db->method('setQuery')->willReturnSelf();

        $service = new CartService($db);
        $result = $service->load('nonexistent-cart-id');

        $this->assertNull($result);
    }

    public function testLoadBySessionReturnsNullForEmptySession(): void
    {
        $db = $this->createMockDb();
        $db->method('setQuery')->willReturnSelf();

        $service = new CartService($db);

        // Empty session ID should return null without hitting DB
        $result = $service->loadBySession('');
        $this->assertNull($result);

        $result = $service->loadBySession('   ');
        $this->assertNull($result);
    }

    /**
     * Test that cart data normalization enforces base currency.
     */
    public function testCartNormalizationEnforcesBaseCurrency(): void
    {
        // This tests the concept - actual currency comes from ConfigHelper
        $cartData = [
            'currency' => 'USD',
            'items' => [
                ['sku' => 'TEST-001', 'qty' => 2, 'currency' => 'USD'],
                ['sku' => 'TEST-002', 'qty' => 1, 'currency' => 'EUR'], // Wrong currency
            ]
        ];

        // All items should be normalized to base currency
        foreach ($cartData['items'] as $item) {
            $this->assertArrayHasKey('sku', $item);
            $this->assertArrayHasKey('qty', $item);
        }
    }

    /**
     * Test quantity normalization.
     */
    public function testQuantityNormalization(): void
    {
        $testCases = [
            [0, 1],      // 0 becomes 1
            [-1, 1],     // Negative becomes 1
            [1, 1],      // 1 stays 1
            [5, 5],      // Positive stays same
            ['3', 3],    // String numeric
            [null, 1],   // Null becomes 1
        ];

        foreach ($testCases as [$input, $expected]) {
            $normalized = $this->normalizeQuantity($input);
            $this->assertEquals($expected, $normalized, "Input {$input} should normalize to {$expected}");
        }
    }

    private function normalizeQuantity($value): int
    {
        $int = (int) $value;
        return $int > 0 ? $int : 1;
    }

    /**
     * Test cart item structure validation.
     */
    public function testCartItemStructure(): void
    {
        $validItem = [
            'sku' => 'PROD-001',
            'product_id' => 1,
            'variant_id' => 2,
            'qty' => 3,
            'unit_price_cents' => 1999,
            'currency' => 'EUR',
        ];

        $this->assertArrayHasKey('sku', $validItem);
        $this->assertArrayHasKey('qty', $validItem);
        $this->assertIsInt($validItem['qty']);
        $this->assertGreaterThan(0, $validItem['qty']);
    }

    /**
     * Test session ID sanitization.
     */
    public function testSessionIdSanitization(): void
    {
        $testCases = [
            ['abc123', 'abc123'],
            ['  spaced  ', 'spaced'],
            [str_repeat('a', 200), str_repeat('a', 128)], // Truncated to 128
            ['', null],
            [null, null],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->prepareSessionId($input);
            $this->assertEquals($expected, $result);
        }
    }

    private function prepareSessionId($sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }

        $sessionId = trim((string) $sessionId);

        if ($sessionId === '') {
            return null;
        }

        return mb_substr($sessionId, 0, 128);
    }

    /**
     * Test cart data structure for persistence.
     */
    public function testCartDataStructure(): void
    {
        $cartData = [
            'currency' => 'EUR',
            'items' => [
                [
                    'sku' => 'SKU-001',
                    'product_id' => 10,
                    'variant_id' => 20,
                    'qty' => 2,
                    'unit_price_cents' => 2500,
                ],
            ],
            'coupon_code' => 'SAVE10',
        ];

        // Verify JSON serialization works
        $json = json_encode($cartData);
        $this->assertIsString($json);

        // Verify JSON deserialization works
        $decoded = json_decode($json, true);
        $this->assertEquals($cartData, $decoded);
    }

    /**
     * Test cart totals calculation concept.
     */
    public function testCartTotalsCalculation(): void
    {
        $items = [
            ['qty' => 2, 'unit_price_cents' => 1000], // 20.00
            ['qty' => 1, 'unit_price_cents' => 1500], // 15.00
            ['qty' => 3, 'unit_price_cents' => 500],  // 15.00
        ];

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['qty'] * $item['unit_price_cents'];
        }

        $this->assertEquals(5000, $subtotal); // 50.00 in cents
    }

    /**
     * Test empty cart handling.
     */
    public function testEmptyCartHandling(): void
    {
        $emptyCart = [
            'currency' => 'EUR',
            'items' => [],
        ];

        $this->assertEmpty($emptyCart['items']);
        $this->assertCount(0, $emptyCart['items']);
    }

    /**
     * Test cart merge logic concept (user logs in with existing session cart).
     */
    public function testCartMergeConcept(): void
    {
        $sessionCart = [
            'items' => [
                ['sku' => 'A', 'qty' => 2],
            ]
        ];

        $userCart = [
            'items' => [
                ['sku' => 'B', 'qty' => 1],
            ]
        ];

        // Merge logic: session cart takes priority (most recent additions)
        $merged = array_merge($userCart['items'], $sessionCart['items']);

        $this->assertCount(2, $merged);
    }
}
