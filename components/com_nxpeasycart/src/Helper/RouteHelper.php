<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Router\Route;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;
use Joomla\Database\DatabaseInterface;

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
     * Cached primary category paths keyed by product slug.
     *
     * @var array<string, array<int, string>|null>
     */
    private static array $productPathCache = [];

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
    public static function getProductRoute(string $slug, ?string $categoryPath = null, bool $xhtml = true): string
    {
        // Products should be routed via the landing or category menu item
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('category');

        $pathSegments = [];
        $db = self::getDatabase();

        if ($categoryPath !== null && $categoryPath !== '') {
            $pathSegments = CategoryPathHelper::normalisePathSegments($categoryPath);

            if (empty($pathSegments) && $db) {
                $pathSegments = CategoryPathHelper::getPathForSlug($db, $categoryPath);
            }
        } elseif ($db) {
            $pathSegments = self::getPrimaryPathForProduct($db, $slug);
        }

        $url = 'index.php?option=com_nxpeasycart&view=product&slug=' . rawurlencode($slug);

        if (!empty($pathSegments)) {
            $url .= '&category_path=' . implode('/', array_map('rawurlencode', $pathSegments));
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
    public static function getCategoryRoute(?string $slug = null, ?int $categoryId = null, bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('category') ?? self::findMenuItemId('landing');
        $db     = self::getDatabase();
        $path   = [];

        if ($categoryId !== null && $categoryId > 0 && $db) {
            $path = CategoryPathHelper::getPath($db, $categoryId);
        } elseif ($slug !== null && $slug !== '' && $db) {
            if (strcasecmp($slug, 'all') !== 0) {
                $path = CategoryPathHelper::getPathForSlug($db, $slug);
            }
        }

        $url = 'index.php?option=com_nxpeasycart&view=category';

        if ($slug !== null && $slug !== '') {
            $url .= '&slug=' . rawurlencode($slug);
        }

        if (!empty($path)) {
            $url .= '&category_path=' . implode('/', array_map('rawurlencode', $path));
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
    public static function getOrderRoute(string $orderNo, bool $xhtml = true, ?string $publicToken = null): string
    {
        $itemId = self::findMenuItemId('landing') ?? self::findMenuItemId('order');

        $url = 'index.php?option=com_nxpeasycart&view=order';

        if ($publicToken !== null && $publicToken !== '') {
            $url .= '&ref=' . rawurlencode($publicToken);
        } else {
            $url .= '&no=' . rawurlencode($orderNo);
        }

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Get a SEF URL for the authenticated orders list.
     */
    public static function getOrdersRoute(bool $xhtml = true): string
    {
        $itemId = self::findMenuItemId('orders') ?? self::findMenuItemId('landing');

        $url = 'index.php?option=com_nxpeasycart&view=orders';

        if ($itemId) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::_($url, $xhtml);
    }

    /**
     * Resolve and cache the primary category path for a product.
     *
     * @return array<int, string>
     */
    private static function getPrimaryPathForProduct(DatabaseInterface $db, string $slug): array
    {
        $cacheKey = strtolower($slug);

        if (\array_key_exists($cacheKey, self::$productPathCache)) {
            return self::$productPathCache[$cacheKey] ?? [];
        }

        $resolved = CategoryPathHelper::getPrimaryPathForProduct($db, $slug);

        if ($resolved === null) {
            self::$productPathCache[$cacheKey] = null;

            return [];
        }

        self::$productPathCache[$cacheKey] = $resolved['path'] ?? [];

        return self::$productPathCache[$cacheKey];
    }

    /**
     * Fetch the database connection when available.
     */
    private static function getDatabase(): ?DatabaseInterface
    {
        try {
            return Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $exception) {
            return null;
        }
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
        self::$productPathCache = [];
        CategoryPathHelper::reset();
    }
}
