<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Joomla\CMS\Language\Language;

/**
 * Minimal language stub returning raw translation keys.
 */
class StubLanguage extends Language
{
    public function __construct()
    {
        // Skip parent constructor to avoid dependencies
    }

    public function _($string, $jsSafe = false, $interpretBackSlashes = true)
    {
        return (string) $string;
    }

    public function hasKey($key): bool
    {
        return false;
    }

    public function getPluralSuffixes($count)
    {
        return [];
    }

    public function getTag(): string
    {
        return 'en-GB';
    }

    public function load($extension = null, $basePath = null, $lang = null, $reload = false, $default = true): bool
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
    public function transliterate($string)
    {
        // Simple transliteration for testing
        $string = strtolower((string) $string);
        $string = preg_replace('/[^a-z0-9\s\-]/', '', $string);
        $string = preg_replace('/[\s\-]+/', '-', $string);
        $string = trim($string, '-');

        return $string;
    }
}
