<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Joomla\CMS\Uri\Uri;

/**
 * Router stub that simply returns the provided URI.
 */
class StubRouter
{
    public function build($url): Uri
    {
        return new Uri((string) $url);
    }
}
