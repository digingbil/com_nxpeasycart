<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Joomla\Registry\Registry;

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

            public function getItems()
            {
                return [];
            }
        };
    }

    public function getConfig(): Registry
    {
        return new Registry(['sitename' => 'Test Site']);
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
