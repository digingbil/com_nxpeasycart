<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;

/**
 * Lightweight caching facade for expensive queries.
 *
 * @since 0.1.5
 */
class CacheService
{
    private CacheControllerFactoryInterface $factory;

    public function __construct(CacheControllerFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Remember the result of a callback for the provided TTL.
     *
     * @template T
     * @param string   $key
     * @param callable():T $callback
     * @param int      $ttlSeconds
     * @param string   $group
     *
     * @return T
     *
     * @since 0.1.5
     */
    public function remember(string $key, callable $callback, int $ttlSeconds = 300, string $group = 'com_nxpeasycart')
    {
        $controller = $this->factory->createCacheController('callback', ['defaultgroup' => $group]);
        $controller->setLifeTime($ttlSeconds);

        // If the returned controller does not support ->call (misconfigured factories),
        // bypass caching rather than fatalling.
        if (!\is_object($controller) || !method_exists($controller, 'call')) {
            return $callback();
        }

        return $controller->call(static function () use ($callback) {
            return $callback();
        }, [], $key);
    }
}
