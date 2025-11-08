<?php

declare(strict_types=1);

namespace Tests\Stubs;

/**
 * Extremely small CMS application stub for routing and language access.
 */
class StubApplication
{
    public function getName(): string
    {
        return 'site';
    }

    public function bootComponent($option)
    {
        return null;
    }

    public static function getRouter($client)
    {
        return new StubRouter();
    }

    public function getMenu(): object
    {
        return new class {
            public function getActive()
            {
                return null;
            }
        };
    }

    public function get($key, $default = null)
    {
        return $default;
    }

    public function getLanguage(): StubLanguage
    {
        return new StubLanguage();
    }
}
