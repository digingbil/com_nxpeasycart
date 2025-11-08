<?php

declare(strict_types=1);

namespace Tests\Stubs;

/**
 * Minimal language stub returning raw translation keys.
 */
class StubLanguage
{
    public function _($string, $jsSafe = false, $interpretBackSlashes = true)
    {
        return (string) $string;
    }

    public function hasKey($key): bool
    {
        return false;
    }

    public function getPluralSuffixes(int $count): array
    {
        return [];
    }

    public function getTag(): string
    {
        return 'en-GB';
    }
}
