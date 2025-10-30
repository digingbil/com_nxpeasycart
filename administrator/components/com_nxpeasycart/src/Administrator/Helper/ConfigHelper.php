<?php

namespace Nxp\EasyCart\Admin\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;

/**
 * Component configuration helper utilities.
 */
class ConfigHelper
{
    /**
     * Cached base currency value.
     */
    private static ?string $baseCurrency = null;

    /**
     * Resolve the store's base currency (ISO 4217, uppercase).
     */
    public static function getBaseCurrency(): string
    {
        if (self::$baseCurrency !== null) {
            return self::$baseCurrency;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        $raw = strtoupper((string) $params->get('base_currency', ''));
        $filtered = preg_replace('/[^A-Z]/', '', $raw);

        if ($filtered === null || strlen($filtered) !== 3) {
            $filtered = 'USD';
        }

        self::$baseCurrency = $filtered;

        return self::$baseCurrency;
    }

    /**
     * Reset cached configuration values.
     */
    public static function clearCache(): void
    {
        self::$baseCurrency = null;
    }
}
