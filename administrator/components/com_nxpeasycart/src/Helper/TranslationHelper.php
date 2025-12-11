<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Translation helper for loading language strings into JavaScript.
 *
 * Joomla's Text::script() must be called for each language constant that
 * needs to be available via Joomla.Text._() in JavaScript. This helper
 * loads all component language strings at once to avoid missing translations
 * in the Vue admin SPA.
 *
 * @since 0.1.12
 */
class TranslationHelper
{
    /**
     * Flag to prevent double-loading.
     *
     * @since 0.1.12
     */
    private static bool $loaded = false;

    /**
     * Register all component language strings for JavaScript access.
     *
     * Reads the component's language file and registers every
     * COM_NXPEASYCART_* key with Text::script() so they're available
     * to the Vue admin SPA via Joomla.Text._().
     *
     * @since 0.1.12
     */
    public static function loadForScript(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $keys = self::getComponentLanguageKeys();

        foreach ($keys as $key) {
            Text::script($key);
        }
    }

    /**
     * Get all language keys from the component's language file.
     *
     * Parses the INI file directly to extract all keys, ensuring we catch
     * every translation even if Joomla hasn't loaded it yet.
     *
     * @return array<string> List of language keys
     *
     * @since 0.1.12
     */
    private static function getComponentLanguageKeys(): array
    {
        $keys = [];
        $language = Factory::getApplication()->getLanguage();
        $tag = $language->getTag();

        // Try current language first, then fallback to en-GB
        $paths = [
            JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/language/' . $tag . '/com_nxpeasycart.ini',
            JPATH_ADMINISTRATOR . '/language/' . $tag . '/com_nxpeasycart.ini',
            JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/language/en-GB/com_nxpeasycart.ini',
            JPATH_ADMINISTRATOR . '/language/en-GB/com_nxpeasycart.ini',
        ];

        $filePath = null;

        foreach ($paths as $path) {
            if (is_file($path)) {
                $filePath = $path;
                break;
            }
        }

        if ($filePath === null) {
            return $keys;
        }

        // Parse INI file - Joomla language files use KEY="value" format
        $contents = @file_get_contents($filePath);

        if ($contents === false) {
            return $keys;
        }

        // Match all lines with KEY="..." pattern
        if (preg_match_all('/^(COM_NXPEASYCART_[A-Z0-9_]+)\s*=/m', $contents, $matches)) {
            $keys = $matches[1];
        }

        return array_unique($keys);
    }

    /**
     * Reset the loaded flag (useful for testing).
     *
     * @since 0.1.12
     */
    public static function reset(): void
    {
        self::$loaded = false;
    }
}
