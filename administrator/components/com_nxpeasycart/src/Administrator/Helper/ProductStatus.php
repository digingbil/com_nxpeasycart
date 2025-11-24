<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Product status helper for mapping active/inactive/out-of-stock values.
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

    public static function isPurchasable(int $status): bool
    {
        return $status === self::ACTIVE;
    }

    public static function isOutOfStock(int $status): bool
    {
        return $status === self::OUT_OF_STOCK;
    }

    public static function isVisible(int $status): bool
    {
        return $status !== self::INACTIVE;
    }
}
