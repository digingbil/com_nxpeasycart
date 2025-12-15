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

use DateTimeImmutable;
use DateTimeZone;

/**
 * Price resolution utilities for regular vs sale prices.
 *
 * @since 0.2.0
 */
class PriceHelper
{
    /**
     * Resolve the effective price for a variant considering sale pricing.
     *
     * @param object|array         $variant Variant row with price_cents, sale_price_cents, sale_start, sale_end
     * @param DateTimeImmutable|null $now    Optional timestamp for testing (defaults to current time)
     *
     * @return array{
     *   effective_price_cents: int,
     *   regular_price_cents: int,
     *   sale_price_cents: ?int,
     *   is_on_sale: bool,
     *   sale_active: bool,
     *   discount_percent: ?float
     * }
     *
     * @since 0.2.0
     */
    public static function resolve($variant, ?DateTimeImmutable $now = null): array
    {
        $isArray      = \is_array($variant);
        $regularPrice = (int) ($isArray ? ($variant['price_cents'] ?? 0) : ($variant->price_cents ?? 0));
        $salePrice    = $isArray ? ($variant['sale_price_cents'] ?? null) : ($variant->sale_price_cents ?? null);
        $saleStart    = $isArray ? ($variant['sale_start'] ?? null) : ($variant->sale_start ?? null);
        $saleEnd      = $isArray ? ($variant['sale_end'] ?? null) : ($variant->sale_end ?? null);

        // If no sale price is set, return regular price
        if ($salePrice === null || $salePrice === '') {
            return [
                'effective_price_cents' => $regularPrice,
                'regular_price_cents'   => $regularPrice,
                'sale_price_cents'      => null,
                'is_on_sale'            => false,
                'sale_active'           => false,
                'discount_percent'      => null,
            ];
        }

        $salePriceCents = (int) $salePrice;

        // Check if sale is currently active
        $now      = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $isActive = self::isSaleActive($saleStart, $saleEnd, $now);

        $effectivePrice = $isActive ? $salePriceCents : $regularPrice;

        // Calculate discount percentage if sale is active
        $discountPercent = null;

        if ($isActive && $regularPrice > 0 && $salePriceCents < $regularPrice) {
            $discountPercent = round((($regularPrice - $salePriceCents) / $regularPrice) * 100, 1);
        }

        return [
            'effective_price_cents' => $effectivePrice,
            'regular_price_cents'   => $regularPrice,
            'sale_price_cents'      => $salePriceCents,
            'is_on_sale'            => $salePriceCents > 0,
            'sale_active'           => $isActive,
            'discount_percent'      => $discountPercent,
        ];
    }

    /**
     * Check if a sale period is currently active.
     *
     * @param string|null            $start Sale start datetime
     * @param string|null            $end   Sale end datetime
     * @param DateTimeImmutable|null $now   Current datetime (defaults to now)
     *
     * @return bool True if sale is currently active
     *
     * @since 0.2.0
     */
    public static function isSaleActive(?string $start, ?string $end, ?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // No start date = active immediately
        // No end date = never expires
        // If start > now OR end < now = inactive

        if ($start !== null && $start !== '') {
            try {
                $startDt = new DateTimeImmutable($start, new DateTimeZone('UTC'));

                if ($now < $startDt) {
                    return false; // Not started yet
                }
            } catch (\Throwable $e) {
                return false; // Invalid date = treat as inactive
            }
        }

        if ($end !== null && $end !== '') {
            try {
                $endDt = new DateTimeImmutable($end, new DateTimeZone('UTC'));

                if ($now > $endDt) {
                    return false; // Already ended
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true; // No restrictions or within valid window
    }

    /**
     * Compute price range (min/max) across an array of variants.
     * Returns the effective sale prices when active.
     *
     * @param array  $variants        Array of variant rows
     * @param string $defaultCurrency Default currency code
     *
     * @return array{min_cents: int, max_cents: int, currency: string, has_sale: bool, any_sale_active: bool}
     *
     * @since 0.2.0
     */
    public static function computePriceRange(array $variants, string $defaultCurrency = 'USD'): array
    {
        if (empty($variants)) {
            return [
                'min_cents'       => 0,
                'max_cents'       => 0,
                'currency'        => $defaultCurrency,
                'has_sale'        => false,
                'any_sale_active' => false,
            ];
        }

        $prices         = [];
        $hasSale        = false;
        $anySaleActive  = false;

        foreach ($variants as $variant) {
            $resolved = self::resolve($variant);
            $prices[] = $resolved['effective_price_cents'];

            if ($resolved['is_on_sale']) {
                $hasSale = true;
            }

            if ($resolved['sale_active']) {
                $anySaleActive = true;
            }
        }

        $prices = array_filter($prices, static fn ($p) => $p > 0);

        if (empty($prices)) {
            return [
                'min_cents'       => 0,
                'max_cents'       => 0,
                'currency'        => $defaultCurrency,
                'has_sale'        => $hasSale,
                'any_sale_active' => $anySaleActive,
            ];
        }

        return [
            'min_cents'       => min($prices),
            'max_cents'       => max($prices),
            'currency'        => $defaultCurrency,
            'has_sale'        => $hasSale,
            'any_sale_active' => $anySaleActive,
        ];
    }

    /**
     * Check if any variant in the collection has an active sale.
     *
     * @param array $variants Array of variant rows
     *
     * @return bool True if any variant has an active sale
     *
     * @since 0.2.0
     */
    public static function hasActiveSale(array $variants): bool
    {
        foreach ($variants as $variant) {
            $resolved = self::resolve($variant);

            if ($resolved['sale_active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get sale status information for display purposes.
     *
     * @param object|array $variant Variant row
     *
     * @return array{status: string, ends_soon: bool, starts_in: ?string, ends_in: ?string}
     *
     * @since 0.2.0
     */
    public static function getSaleStatus($variant): array
    {
        $isArray   = \is_array($variant);
        $salePrice = $isArray ? ($variant['sale_price_cents'] ?? null) : ($variant->sale_price_cents ?? null);
        $saleStart = $isArray ? ($variant['sale_start'] ?? null) : ($variant->sale_start ?? null);
        $saleEnd   = $isArray ? ($variant['sale_end'] ?? null) : ($variant->sale_end ?? null);

        if ($salePrice === null || $salePrice === '') {
            return [
                'status'     => 'none',
                'ends_soon'  => false,
                'starts_in'  => null,
                'ends_in'    => null,
            ];
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // Check if not yet started
        if ($saleStart !== null && $saleStart !== '') {
            try {
                $startDt = new DateTimeImmutable($saleStart, new DateTimeZone('UTC'));

                if ($now < $startDt) {
                    $diff = $now->diff($startDt);

                    return [
                        'status'     => 'scheduled',
                        'ends_soon'  => false,
                        'starts_in'  => self::formatInterval($diff),
                        'ends_in'    => null,
                    ];
                }
            } catch (\Throwable $e) {
                // Invalid date
            }
        }

        // Check if expired
        if ($saleEnd !== null && $saleEnd !== '') {
            try {
                $endDt = new DateTimeImmutable($saleEnd, new DateTimeZone('UTC'));

                if ($now > $endDt) {
                    return [
                        'status'     => 'expired',
                        'ends_soon'  => false,
                        'starts_in'  => null,
                        'ends_in'    => null,
                    ];
                }

                // Check if ending soon (within 24 hours)
                $diff     = $now->diff($endDt);
                $endsSoon = $diff->days === 0 && $diff->h < 24;

                return [
                    'status'     => 'active',
                    'ends_soon'  => $endsSoon,
                    'starts_in'  => null,
                    'ends_in'    => self::formatInterval($diff),
                ];
            } catch (\Throwable $e) {
                // Invalid date
            }
        }

        // Active with no end date
        return [
            'status'     => 'active',
            'ends_soon'  => false,
            'starts_in'  => null,
            'ends_in'    => null,
        ];
    }

    /**
     * Format a DateInterval as a human-readable string.
     *
     * @param \DateInterval $interval Interval to format
     *
     * @return string Formatted interval
     *
     * @since 0.2.0
     */
    private static function formatInterval(\DateInterval $interval): string
    {
        if ($interval->y > 0) {
            return $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
        }

        if ($interval->m > 0) {
            return $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
        }

        if ($interval->d > 0) {
            return $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
        }

        if ($interval->h > 0) {
            return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
        }

        if ($interval->i > 0) {
            return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
        }

        return 'less than a minute';
    }
}
