<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Router\Route;

/**
 * Routing helpers for storefront links.
 *
 * This helper ensures URLs are built with the correct Itemid regardless
 * of which menu item is currently active. Joomla's default routing uses
 * the active menu item as the base, which causes issues like:
 * - /shop/all-products/cart instead of /shop/cart
 * - Non-SEF product URLs when no product menu exists
 */
class RouteHelper
{
    /**
     * Cached menu item IDs by view.
     *
     * @var array<string, int|null>
     */
    private static array $menuCache = [];

    /**
     * Get a SEF URL for the cart view.
     *
     * @param bool $xhtml Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getCartRoute(bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('cart');

        $url = 'index.php?option=com_nxpeasycart&view=cart';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for the checkout view.
     *
     * @param bool $xhtml Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getCheckoutRoute(bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('checkout');

        $url = 'index.php?option=com_nxpeasycart&view=checkout';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for the landing view.
     *
     * @param bool $xhtml Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getLandingRoute(bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('landing');

        $url = 'index.php?option=com_nxpeasycart&view=landing';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for a product.
     *
     * @param string      $slug         The product slug
     * @param string|null $categorySlug Optional category slug for nested URLs
     * @param bool        $xhtml        Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getProductRoute(string $slug, ?string $categorySlug = null, bool $xhtml = true): string
    {
        // Products should be routed via the landing or category menu item
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('category');

        $url = 'index.php?option=com_nxpeasycart&view=product&slug=' . rawurlencode($slug);

        if ($categorySlug !== null && $categorySlug !== '') {
            $url .= '&category_slug=' . rawurlencode($categorySlug);
        }

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for a category.
     *
     * @param string|null $slug  The category slug (null for all products)
     * @param bool        $xhtml Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getCategoryRoute(?string $slug = null, bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('category') ?? self::findMenuItemId('landing');

        $url = 'index.php?option=com_nxpeasycart&view=category';

        if ($slug !== null && $slug !== '') {
            $url .= '&slug=' . rawurlencode($slug);
        }

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for an order.
     *
     * @param string $orderNo The order number
     * @param bool   $xhtml   Whether to encode ampersands for XHTML
     *
     * @return string
     */
    public static function getOrderRoute(string $orderNo, bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('order');

        $url = 'index.php?option=com_nxpeasycart&view=order&no=' . rawurlencode($orderNo);

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Find a menu item ID for the given view.
     *
     * @param string $view The view name
     *
     * @return int|null
     */
    public static function findMenuItemId(string $view): ?int
    {
        if (isset(self::$menuCache[$view])) {
            return self::$menuCache[$view];
        }

        try {
            $app  = Factory::getApplication();
            $menu = $app->getMenu();
        } catch (\Throwable $e) {
            return null;
        }

        $items = $menu->getItems('component', 'com_nxpeasycart') ?: [];

        foreach ($items as $item) {
            if (($item->query['view'] ?? '') === $view) {
                self::$menuCache[$view] = (int) $item->id;

                return self::$menuCache[$view];
            }
        }

        self::$menuCache[$view] = null;

        return null;
    }

    /**
     * Clear the menu cache (useful in tests or after menu changes).
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$menuCache = [];
    }
}
