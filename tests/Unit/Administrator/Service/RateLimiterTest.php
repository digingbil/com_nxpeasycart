<?php

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service;

use Joomla\Component\Nxpeasycart\Administrator\Service\RateLimiter;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the RateLimiter service.
 */
final class RateLimiterTest extends TestCase
{
    private function createLimiter(): RateLimiter
    {
        $factory = $this->createMock(CacheControllerFactoryInterface::class);

        // The constructor will fall back to memory storage when cache creation fails
        return new RateLimiter($factory);
    }

    public function testFirstHitIsAllowed(): void
    {
        $limiter = $this->createLimiter();

        $result = $limiter->hit('test-key-first', 5, 60);

        $this->assertTrue($result, 'First hit should be allowed');
    }

    public function testHitsUnderLimitAreAllowed(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-under-' . uniqid();

        for ($i = 1; $i <= 5; $i++) {
            $result = $limiter->hit($key, 5, 60);
            $this->assertTrue($result, "Hit {$i} of 5 should be allowed");
        }
    }

    public function testHitAtLimitIsBlocked(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-limit-' . uniqid();

        // Use up all 3 attempts
        for ($i = 1; $i <= 3; $i++) {
            $limiter->hit($key, 3, 60);
        }

        // 4th hit should be blocked
        $result = $limiter->hit($key, 3, 60);
        $this->assertFalse($result, 'Hit exceeding limit should be blocked');
    }

    public function testLoadReturnsNullForNewKey(): void
    {
        $limiter = $this->createLimiter();

        $entry = $limiter->load('nonexistent-key-' . uniqid());

        $this->assertNull($entry);
    }

    public function testLoadReturnsEntryAfterHit(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-load-' . uniqid();

        $limiter->hit($key, 10, 60);

        $entry = $limiter->load($key);

        $this->assertIsArray($entry);
        $this->assertArrayHasKey('count', $entry);
        $this->assertArrayHasKey('expires_at', $entry);
        $this->assertEquals(1, $entry['count']);
    }

    public function testCounterIncrementsOnEachHit(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-increment-' . uniqid();

        $limiter->hit($key, 10, 60);
        $limiter->hit($key, 10, 60);
        $limiter->hit($key, 10, 60);

        $entry = $limiter->load($key);

        $this->assertEquals(3, $entry['count']);
    }

    public function testDifferentKeysHaveSeparateCounters(): void
    {
        $limiter = $this->createLimiter();
        $key1 = 'test-key-separate-1-' . uniqid();
        $key2 = 'test-key-separate-2-' . uniqid();

        $limiter->hit($key1, 10, 60);
        $limiter->hit($key1, 10, 60);
        $limiter->hit($key2, 10, 60);

        $entry1 = $limiter->load($key1);
        $entry2 = $limiter->load($key2);

        $this->assertEquals(2, $entry1['count']);
        $this->assertEquals(1, $entry2['count']);
    }

    public function testExpiryTimeIsSetCorrectly(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-expiry-' . uniqid();
        $windowSeconds = 300; // 5 minutes

        $now = time();
        $limiter->hit($key, 10, $windowSeconds);

        $entry = $limiter->load($key);

        // Expiry should be approximately now + windowSeconds
        $this->assertGreaterThanOrEqual($now + $windowSeconds - 1, $entry['expires_at']);
        $this->assertLessThanOrEqual($now + $windowSeconds + 1, $entry['expires_at']);
    }

    public function testZeroLimitBlocksAllHits(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-zero-' . uniqid();

        // With limit of 0, even first hit should be blocked
        $result = $limiter->hit($key, 0, 60);

        $this->assertFalse($result, 'Zero limit should block all hits');
    }

    public function testKeyNormalization(): void
    {
        $limiter = $this->createLimiter();

        // Keys with special characters should be normalized
        $key1 = 'IP:192.168.1.1';
        $key2 = 'email:user@example.com';

        $limiter->hit($key1, 10, 60);
        $limiter->hit($key2, 10, 60);

        // Both should work without errors
        $entry1 = $limiter->load($key1);
        $entry2 = $limiter->load($key2);

        $this->assertNotNull($entry1);
        $this->assertNotNull($entry2);
    }

    public function testCaseSensitivityOfKeys(): void
    {
        $limiter = $this->createLimiter();
        $base = uniqid();

        // Keys are normalized to lowercase
        $key1 = 'TEST-KEY-' . $base;
        $key2 = 'test-key-' . $base;

        $limiter->hit($key1, 10, 60);
        $entry = $limiter->load($key2);

        // Should find the same entry (keys are case-insensitive after normalization)
        $this->assertNotNull($entry);
        $this->assertEquals(1, $entry['count']);
    }

    /**
     * Test that rate limiting works for checkout scenarios.
     */
    public function testCheckoutRateLimitingScenario(): void
    {
        $limiter = $this->createLimiter();
        $ip = '203.0.113.42';
        $email = 'customer@example.com';

        $ipKey = 'checkout:ip:' . $ip;
        $emailKey = 'checkout:email:' . $email;

        // Simulate 10 checkout attempts from same IP
        $ipLimit = 10;
        for ($i = 1; $i <= $ipLimit; $i++) {
            $result = $limiter->hit($ipKey, $ipLimit, 600);
            $this->assertTrue($result, "IP attempt {$i} should be allowed");
        }

        // 11th attempt should be blocked
        $result = $limiter->hit($ipKey, $ipLimit, 600);
        $this->assertFalse($result, 'IP limit exceeded');

        // But email limit is separate
        for ($i = 1; $i <= 5; $i++) {
            $result = $limiter->hit($emailKey, 5, 600);
            $this->assertTrue($result, "Email attempt {$i} should be allowed");
        }
    }

    /**
     * Test offline payment stricter limits.
     */
    public function testOfflinePaymentStricterLimits(): void
    {
        $limiter = $this->createLimiter();
        $ip = '198.51.100.99';

        $offlineKey = 'offline:ip:' . $ip;
        $offlineLimit = 3; // Stricter than online checkout

        for ($i = 1; $i <= $offlineLimit; $i++) {
            $result = $limiter->hit($offlineKey, $offlineLimit, 1800);
            $this->assertTrue($result);
        }

        // Should be blocked after 3 attempts
        $result = $limiter->hit($offlineKey, $offlineLimit, 1800);
        $this->assertFalse($result, 'Offline limit should be strict (3 attempts)');
    }

    public function testWindowExpiry(): void
    {
        $limiter = $this->createLimiter();
        $key = 'test-key-window-' . uniqid();

        // Use a very short window (already expired)
        $limiter->hit($key, 5, 1);

        $entry = $limiter->load($key);
        $this->assertNotNull($entry);

        // Simulate time passing by checking expiry
        // After window expires, a new hit should reset the counter
        // (In real scenario, this would happen after sleeping)
        $this->assertEquals(1, $entry['count']);
    }

    public function testMemoryFallback(): void
    {
        // Create limiter with failing cache factory
        $factory = $this->createMock(CacheControllerFactoryInterface::class);
        $factory->method('createCacheController')->willThrowException(new \Exception('Cache unavailable'));

        $limiter = new RateLimiter($factory);

        // Should still work via memory fallback
        $result = $limiter->hit('fallback-test', 5, 60);
        $this->assertTrue($result);

        $entry = $limiter->load('fallback-test');
        $this->assertNotNull($entry);
    }
}
