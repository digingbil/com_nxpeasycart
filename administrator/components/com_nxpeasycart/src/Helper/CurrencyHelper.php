<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Currency helper with ISO 4217 currency codes.
 *
 * @since 0.1.14
 */
class CurrencyHelper
{
    /**
     * ISO 4217 currency codes with names.
     *
     * Includes major world currencies commonly used in e-commerce.
     *
     * @var array<string, string>
     *
     * @since 0.1.14
     */
    private static array $currencies = [
        'ALL' => 'Albanian Lek',
        'AED' => 'UAE Dirham',
        'ARS' => 'Argentine Peso',
        'AUD' => 'Australian Dollar',
        'BAM' => 'Bosnia-Herzegovina Convertible Mark',
        'BYN' => 'Belarusian Ruble',
        'BGN' => 'Bulgarian Lev',
        'BRL' => 'Brazilian Real',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'CLP' => 'Chilean Peso',
        'CNY' => 'Chinese Yuan',
        'COP' => 'Colombian Peso',
        'CZK' => 'Czech Koruna',
        'DKK' => 'Danish Krone',
        'EGP' => 'Egyptian Pound',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'GEL' => 'Georgian Lari',
        'HKD' => 'Hong Kong Dollar',
        'HRK' => 'Croatian Kuna',
        'HUF' => 'Hungarian Forint',
        'IDR' => 'Indonesian Rupiah',
        'ILS' => 'Israeli New Shekel',
        'INR' => 'Indian Rupee',
        'ISK' => 'Icelandic Króna',
        'JPY' => 'Japanese Yen',
        'KRW' => 'South Korean Won',
        'MKD' => 'Macedonian Denar',
        'MXN' => 'Mexican Peso',
        'MYR' => 'Malaysian Ringgit',
        'NOK' => 'Norwegian Krone',
        'NZD' => 'New Zealand Dollar',
        'MDL' => 'Moldovan Leu',
        'PEN' => 'Peruvian Sol',
        'PHP' => 'Philippine Peso',
        'PLN' => 'Polish Złoty',
        'RON' => 'Romanian Leu',
        'RSD' => 'Serbian Dinar',
        'RUB' => 'Russian Ruble',
        'SAR' => 'Saudi Riyal',
        'SEK' => 'Swedish Krona',
        'SGD' => 'Singapore Dollar',
        'THB' => 'Thai Baht',
        'TRY' => 'Turkish Lira',
        'TWD' => 'Taiwan Dollar',
        'UAH' => 'Ukrainian Hryvnia',
        'USD' => 'US Dollar',
        'UYU' => 'Uruguayan Peso',
        'VND' => 'Vietnamese Đồng',
        'ZAR' => 'South African Rand',
    ];

    /**
     * Get all available currencies as code => name array.
     *
     * @return array<string, string>
     *
     * @since 0.1.14
     */
    public static function getAll(): array
    {
        return self::$currencies;
    }

    /**
     * Get currencies formatted for dropdown (array of objects with code and name).
     *
     * @return array<int, array{code: string, name: string, label: string}>
     *
     * @since 0.1.14
     */
    public static function getForDropdown(): array
    {
        $result = [];

        foreach (self::$currencies as $code => $name) {
            $result[] = [
                'code'  => $code,
                'name'  => $name,
                'label' => $code . ' - ' . $name,
            ];
        }

        return $result;
    }

    /**
     * Check if a currency code is valid.
     *
     * @param string $code  The currency code to validate.
     *
     * @return bool
     *
     * @since 0.1.14
     */
    public static function isValid(string $code): bool
    {
        $code = strtoupper(trim($code));

        return isset(self::$currencies[$code]);
    }

    /**
     * Get the name for a currency code.
     *
     * @param string $code  The currency code.
     *
     * @return string|null  The currency name or null if not found.
     *
     * @since 0.1.14
     */
    public static function getName(string $code): ?string
    {
        $code = strtoupper(trim($code));

        return self::$currencies[$code] ?? null;
    }
}
