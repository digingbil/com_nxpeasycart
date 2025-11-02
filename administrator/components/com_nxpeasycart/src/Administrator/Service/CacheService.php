<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;

/**
 * Lightweight caching facade for expensive queries.
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
     */
    public function remember(string $key, callable $callback, int $ttlSeconds = 300, string $group = 'com_nxpeasycart')
    {
        $controller = $this->factory->createCacheController('callback', ['defaultgroup' => $group]);
        $controller->setLifeTime($ttlSeconds);

        return $controller->call(static function () use ($callback) {
            return $callback();
        }, [], $key);
    }
}
