<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

use NumberFormatter;

/**
 * Money formatting utilities.
 */
class MoneyHelper
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF',
        'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Format cents into a currency string respecting locale + currency decimals.
     */
    public static function format(int $cents, string $currency, string $locale = 'en_GB'): string
    {
        $code      = strtoupper(preg_replace('/[^A-Za-z]/', '', $currency ?? ''));
        $decimals  = self::getDecimals($code);
        $divisor   = $decimals > 0 ? 10 ** $decimals : 1;
        $amount    = $cents / $divisor;

        if (\class_exists(NumberFormatter::class, false)) {
            try {
                $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
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
     * Resolve currency decimal places (0 for zero-decimal currencies).
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
