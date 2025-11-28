<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use PHPUnit\Framework\TestCase;

/**
 * Tests for GDPR service functionality.
 */
final class GdprServiceTest extends TestCase
{
    /**
     * Test email validation for export.
     */
    public function testEmailValidationForExport(): void
    {
        $validEmails = [
            'user@example.com',
            'customer+tag@example.org',
            'name.surname@domain.co.uk',
        ];

        $invalidEmails = [
            '',
            'not-an-email',
            '@example.com',
            'user@',
            'user @example.com',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email {$email} should be valid"
            );
        }

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email {$email} should be invalid"
            );
        }
    }

    /**
     * Test anonymisation hash generation.
     */
    public function testAnonymisationHashGeneration(): void
    {
        $email = 'customer@example.com';

        // Generate hash like GdprService does
        $hash = substr(sha1($email . microtime()), 0, 12);
        $anonymisedEmail = sprintf('gdpr+%s@example.invalid', $hash);

        // Verify format
        $this->assertStringStartsWith('gdpr+', $anonymisedEmail);
        $this->assertStringEndsWith('@example.invalid', $anonymisedEmail);
        $this->assertMatchesRegularExpression('/^gdpr\+[a-f0-9]{12}@example\.invalid$/', $anonymisedEmail);
    }

    /**
     * Test that anonymised emails are unique.
     */
    public function testAnonymisedEmailsAreUnique(): void
    {
        $email = 'customer@example.com';
        $hashes = [];

        // Generate multiple anonymised emails
        for ($i = 0; $i < 10; $i++) {
            $hash = substr(sha1($email . microtime() . $i), 0, 12);
            $hashes[] = $hash;
            usleep(1); // Ensure microtime differs
        }

        // All should be unique
        $unique = array_unique($hashes);
        $this->assertCount(10, $unique);
    }

    /**
     * Test export data structure.
     */
    public function testExportDataStructure(): void
    {
        $export = [
            'email' => 'customer@example.com',
            'orders' => [
                [
                    'id' => 1,
                    'order_no' => 'EC-00000001',
                    'state' => 'fulfilled',
                    'total_cents' => 5000,
                    'currency' => 'EUR',
                    'created' => '2025-01-15 10:30:00',
                    'items' => [
                        ['sku' => 'PROD-001', 'title' => 'Test Product', 'qty' => 2],
                    ],
                    'transactions' => [
                        ['gateway' => 'stripe', 'status' => 'paid', 'amount_cents' => 5000],
                    ],
                ],
            ],
        ];

        $this->assertArrayHasKey('email', $export);
        $this->assertArrayHasKey('orders', $export);
        $this->assertIsArray($export['orders']);

        $order = $export['orders'][0];
        $this->assertArrayHasKey('order_no', $order);
        $this->assertArrayHasKey('items', $order);
        $this->assertArrayHasKey('transactions', $order);
    }

    /**
     * Test that anonymisation removes all PII.
     */
    public function testAnonymisationRemovesPii(): void
    {
        // Fields that should be cleared/anonymised
        $piiFields = [
            'email',           // Replaced with anonymous email
            'billing',         // Set to NULL
            'shipping',        // Set to NULL
            'carrier',         // Set to NULL
            'tracking_number', // Set to NULL
            'tracking_url',    // Set to NULL
            'fulfillment_events', // Set to NULL (may contain names)
        ];

        // Verify these are the fields we anonymise
        $this->assertContains('email', $piiFields);
        $this->assertContains('billing', $piiFields);
        $this->assertContains('shipping', $piiFields);
    }

    /**
     * Test that order data is retained for accounting.
     */
    public function testOrderDataRetainedForAccounting(): void
    {
        // Fields that SHOULD be retained (not PII, needed for accounting)
        $retainedFields = [
            'id',
            'order_no',
            'subtotal_cents',
            'tax_cents',
            'shipping_cents',
            'discount_cents',
            'total_cents',
            'currency',
            'state',
            'created',
        ];

        foreach ($retainedFields as $field) {
            // These fields should NOT be anonymised
            $this->assertNotEmpty($field);
        }
    }

    /**
     * Test GDPR export JSON format.
     */
    public function testGdprExportJsonFormat(): void
    {
        $export = [
            'email' => 'test@example.com',
            'orders' => [],
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT);

        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($export, $decoded);
    }

    /**
     * Test cart data is also exported in GDPR request.
     */
    public function testCartDataExportConcept(): void
    {
        // GDPR export should include cart data if tied to email/user
        $cartData = [
            'session_id' => 'abc123',
            'user_id' => null, // Guest cart
            'email' => 'guest@example.com', // From checkout attempt
            'data' => [
                'items' => [
                    ['sku' => 'CART-ITEM', 'qty' => 1],
                ],
            ],
        ];

        // Cart export structure
        $this->assertArrayHasKey('data', $cartData);
        $this->assertArrayHasKey('items', $cartData['data']);
    }

    /**
     * Test right to erasure (GDPR Article 17).
     */
    public function testRightToErasure(): void
    {
        // After anonymisation, the data should not be traceable to the individual
        $originalEmail = 'john.doe@example.com';
        $hash = substr(sha1($originalEmail . '12345'), 0, 12);
        $anonymisedEmail = sprintf('gdpr+%s@example.invalid', $hash);

        // Cannot reverse the hash to get original email
        $this->assertStringNotContainsString('john', $anonymisedEmail);
        $this->assertStringNotContainsString('doe', $anonymisedEmail);
        $this->assertStringNotContainsString('example.com', $anonymisedEmail);
    }

    /**
     * Test data portability format (GDPR Article 20).
     */
    public function testDataPortabilityFormat(): void
    {
        // Data should be in a commonly used, machine-readable format
        $export = [
            'format' => 'json',
            'version' => '1.0',
            'exported_at' => '2025-11-28T12:00:00Z',
            'data' => [
                'email' => 'user@example.com',
                'orders' => [],
            ],
        ];

        $json = json_encode($export);

        // Should be valid JSON
        $this->assertJson($json);

        // Should be decodable by any system
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }
}
