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
 * Product status helper for mapping active/inactive/out-of-stock values.
 *
 * @since 0.1.5
 */
final class ProductStatus
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;
    public const OUT_OF_STOCK = -1;

    /**
     * Normalise an incoming status value into one of the supported constants.
     *
     * @param mixed $value Raw status input
     *
     * @since 0.1.5
     */
    public static function normalise($value): int
    {
        if (\is_string($value)) {
            $trimmed = strtolower(trim($value));

            if ($trimmed === 'out_of_stock' || $trimmed === 'out-of-stock' || $trimmed === '-1') {
                return self::OUT_OF_STOCK;
            }

            if ($trimmed === 'inactive' || $trimmed === '0') {
                return self::INACTIVE;
            }

            if ($trimmed === 'active' || $trimmed === '1') {
                return self::ACTIVE;
            }
        }

        $status = (int) $value;

        if ($status === self::OUT_OF_STOCK || $status === self::INACTIVE) {
            return $status;
        }

        return self::ACTIVE;
    }

    /**
     * Check if a product status is purchasable.
     *
     * @since 0.1.5
     */
    public static function isPurchasable(int $status): bool
    {
        return $status === self::ACTIVE;
    }

    /**
     * Check if a product status is out of stock.
     *
     * @since 0.1.5
     */
    public static function isOutOfStock(int $status): bool
    {
        return $status === self::OUT_OF_STOCK;
    }

    /**
     * Check if a product status is visible.
     *
     * @since 0.1.5
     */
    public static function isVisible(int $status): bool
    {
        return $status !== self::INACTIVE;
    }
}
