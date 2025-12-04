<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use NumberFormatter;

/**
 * Money formatting utilities.
 *
 * @since 0.1.5
 */
class MoneyHelper
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF',
        'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Cached resolved locale value for the current request.
     *
     * @since 0.1.13
     */
    private static ?string $resolvedLocale = null;

    /**
     * Format cents into a currency string respecting locale + currency decimals.
     *
     * When $locale is null, the locale is auto-resolved from:
     * 1. Store display_locale override (if configured)
     * 2. Joomla site language
     * 3. Fallback to 'en_US'
     *
     * @since 0.1.5
     */
    public static function format(int $cents, string $currency, ?string $locale = null): string
    {
        $code      = strtoupper(preg_replace('/[^A-Za-z]/', '', $currency ?? ''));
        $decimals  = self::getDecimals($code);
        $divisor   = $decimals > 0 ? 10 ** $decimals : 1;
        $amount    = $cents / $divisor;

        // Resolve locale if not explicitly provided
        $resolvedLocale = $locale ?? self::resolveLocale();

        if (\class_exists(NumberFormatter::class, false)) {
            try {
                $formatter = new NumberFormatter($resolvedLocale, NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $code);
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

                $formatted = $formatter->formatCurrency($amount, $code);

                if ($formatted !== false) {
                    return (string) $formatted;
                }
            } catch (\Throwable $exception) {
                // Fallback below.
            }
        }

        $formattedAmount = number_format($amount, $decimals, '.', ',');

        return sprintf('%s %s', $code, $formattedAmount);
    }

    /**
     * Resolve the display locale for price formatting.
     *
     * Resolution order:
     * 1. Store-level override (display_locale setting)
     * 2. Joomla site/admin language
     * 3. Fallback to 'en_US'
     *
     * Result is cached per request for performance.
     *
     * @since 0.1.13
     */
    public static function resolveLocale(): string
    {
        if (self::$resolvedLocale !== null) {
            return self::$resolvedLocale;
        }

        // 1. Check for store-level override
        $override = ConfigHelper::getDisplayLocale();

        if ($override !== '') {
            self::$resolvedLocale = self::normaliseLocale($override);

            return self::$resolvedLocale;
        }

        // 2. Get from Joomla language
        try {
            $app = Factory::getApplication();
            $language = $app->getLanguage();
            $locales = $language->getLocale();

            if (!empty($locales[0])) {
                // Normalize: "mk_MK.utf8" → "mk_MK"
                self::$resolvedLocale = self::normaliseLocale($locales[0]);

                return self::$resolvedLocale;
            }

            // Fallback to language tag if getLocale() is empty
            $tag = $language->getTag();

            if ($tag !== '') {
                // Convert "mk-MK" to "mk_MK"
                self::$resolvedLocale = str_replace('-', '_', $tag);

                return self::$resolvedLocale;
            }
        } catch (\Throwable $exception) {
            // Joomla app not available (CLI context, etc.)
        }

        // 3. Fallback
        self::$resolvedLocale = 'en_US';

        return self::$resolvedLocale;
    }

    /**
     * Normalise a locale string to ICU format.
     *
     * Handles variations like "mk_MK.utf8", "mk-MK", "mk_MK.UTF-8" etc.
     *
     * @since 0.1.13
     */
    private static function normaliseLocale(string $locale): string
    {
        // Strip encoding suffix (.utf8, .UTF-8, etc.)
        $normalized = preg_replace('/\..*$/', '', trim($locale));

        // Convert hyphens to underscores (en-GB → en_GB)
        $normalized = str_replace('-', '_', $normalized);

        // Ensure we have a valid locale format
        if ($normalized === '' || !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/i', $normalized)) {
            return 'en_US';
        }

        return $normalized;
    }

    /**
     * Clear cached locale (useful for testing or when settings change).
     *
     * @since 0.1.13
     */
    public static function clearLocaleCache(): void
    {
        self::$resolvedLocale = null;
    }

    /**
     * Resolve currency decimal places (0 for zero-decimal currencies).
     *
     * @since 0.1.5
     */
    public static function getDecimals(string $currency): int
    {
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($currency ?? '')));

        if ($code !== '' && \in_array($code, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        return 2;
    }
}
