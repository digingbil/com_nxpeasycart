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

    public function load($extension = null, $basePath = null): bool
    {
        return true;
    }

    /**
     * Transliterate the string to URL-safe ASCII.
     *
     * @param string $string The string to transliterate
     *
     * @return string
     */
    public function transliterate(string $string): string
    {
        // Simple transliteration for testing
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9\s\-]/', '', $string);
        $string = preg_replace('/[\s\-]+/', '-', $string);
        $string = trim($string, '-');

        return $string;
    }
}
