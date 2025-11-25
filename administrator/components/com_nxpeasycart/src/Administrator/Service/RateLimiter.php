<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheController;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

/**
 * Lightweight, cache-backed rate limiter.
 */
class RateLimiter
{
    private ?CacheController $cache = null;

    /**
     * In-memory fallback when cache storage is unavailable.
     *
     * @var array<string, array{count:int, expires_at:int}>
     */
    private static array $memory = [];

    public function __construct(CacheControllerFactoryInterface $factory)
    {
        try {
            $app     = Factory::getApplication();
            $handler = method_exists($app, 'get') ? (string) $app->get('cache_handler', 'file') : 'file';

            $options = [
                'defaultgroup' => 'com_nxpeasycart.rate',
                'caching'      => true,
                'storage'      => $handler !== '' ? $handler : 'file',
                'lifetime'     => 60, // minutes; we still store per-key expiry in the payload.
            ];

            // Use the output controller for simple key/value storage.
            $this->cache = $factory->createCacheController('output', $options);

            // Ensure caching is enabled even if global cache is off.
            if (isset($this->cache->cache)) {
                $this->cache->cache->setCaching(true);
                $this->cache->cache->setLifeTime((int) $options['lifetime']);
            }
        } catch (\Throwable $exception) {
            $this->cache = null;
        }
    }

    /**
     * Increment the counter for a given key; returns true when under limit.
     */
    public function hit(string $key, int $limit, int $windowSeconds): bool
    {
        $now   = time();
        $entry = $this->load($key);

        if ($entry === null || $entry['expires_at'] <= $now) {
            $entry = [
                'count'      => 0,
                'expires_at' => $now + $windowSeconds,
            ];
        }

        if ($entry['count'] >= $limit) {
            $this->store($key, $entry, $windowSeconds);

            return false;
        }

        $entry['count']++;
        $this->store($key, $entry, $windowSeconds);

        return true;
    }

    /**
     * Read the current counter for a key.
     *
     * @return array{count:int, expires_at:int}|null
     */
    public function load(string $key): ?array
    {
        $sanitised = $this->normaliseKey($key);

        if ($this->cache) {
            $cached = $this->cache->get($sanitised);

            if (\is_array($cached) && isset($cached['count'], $cached['expires_at'])) {
                return [
                    'count'      => (int) $cached['count'],
                    'expires_at' => (int) $cached['expires_at'],
                ];
            }
        }

        if (isset(self::$memory[$sanitised])) {
            return self::$memory[$sanitised];
        }

        return null;
    }

    private function store(string $key, array $value, int $windowSeconds): void
    {
        $sanitised = $this->normaliseKey($key);

        if ($this->cache) {
            try {
                $lifetimeMinutes = max(1, (int) ceil($windowSeconds / 60));

                if (isset($this->cache->cache)) {
                    $this->cache->cache->setLifeTime($lifetimeMinutes);
                }

                $this->cache->store($value, $sanitised);
            } catch (\Throwable $exception) {
                // Fall back to memory store if cache backend fails.
            }
        }

        self::$memory[$sanitised] = $value;
    }

    private function normaliseKey(string $key): string
    {
        $trimmed = strtolower(trim($key));

        return preg_replace('/[^a-z0-9:_-]/', '-', $trimmed) ?? $trimmed;
    }
}
