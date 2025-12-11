<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;

/**
 * Site router registered through Joomla's router factory.
 *
 * @since 0.1.5
 */
class Router extends RouterView
{
    /**
     * @var DatabaseInterface|null
     *
     * @since 0.1.5
     */
    private ?DatabaseInterface $db = null;

    /**
     * Constructor aligning with Joomla's router factory expectations.
     *
     * @since 0.1.5
     */
    public function __construct(
        CMSApplicationInterface $app,
        AbstractMenu $menu,
        ?CategoryFactoryInterface $categoryFactory = null,
        ?DatabaseInterface $db = null
    ) {
        $landing = new RouterViewConfiguration('landing');
        $this->registerView($landing);

        $category = new RouterViewConfiguration('category');
        $category->setKey('slug');
        $this->registerView($category);

        $product = new RouterViewConfiguration('product');
        $product->setKey('slug');
        $this->registerView($product);

        $cart = new RouterViewConfiguration('cart');
        $this->registerView($cart);

        $checkout = new RouterViewConfiguration('checkout');
        $this->registerView($checkout);

        $order = new RouterViewConfiguration('order');
        $order->setKey('no');
        $this->registerView($order);

        $this->db = $db ?: $this->resolveDatabase();
        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Ensure menu-aligned links do not add redundant view parameters.
     *
     * @since 0.1.5
     */
    public function build(&$query)
    {
        $segments = [];

        if (isset($query['view'])) {
            $view = (string) $query['view'];

            switch ($view) {
                case 'product':
                    $productSlug = isset($query['slug']) ? (string) $query['slug'] : '';
                    $categoryPath = $this->extractCategoryPath($query);

                    if (empty($categoryPath) && $productSlug !== '') {
                        $categoryPath = $this->resolveProductPathSegments($productSlug);
                    }

                    if (empty($categoryPath)) {
                        $segments[] = 'product';
                    } else {
                        $segments[] = 'category';

                        foreach ($categoryPath as $segment) {
                            $segments[] = rawurlencode($segment);
                        }
                    }

                    if ($productSlug !== '') {
                        $segments[] = rawurlencode($productSlug);
                        unset($query['slug']);
                    }

                    unset($query['view']);
                    break;

                case 'category':
                    $segments[] = 'category';

                    $slug       = isset($query['slug']) ? (string) $query['slug'] : '';
                    $categoryId = isset($query['id']) ? (int) $query['id'] : 0;
                    $categoryPath = $this->extractCategoryPath($query);

                    if (empty($categoryPath)) {
                        if ($categoryId > 0) {
                            $categoryPath = $this->resolveCategoryPathById($categoryId);
                        } elseif ($slug !== '') {
                            $categoryPath = $this->resolveCategoryPathBySlug($slug);
                        }
                    }

                    if (!empty($categoryPath)) {
                        foreach ($categoryPath as $segment) {
                            $segments[] = rawurlencode($segment);
                        }
                    }

                    unset($query['slug'], $query['id']);
                    unset($query['view']);
                    break;

                case 'cart':
                case 'checkout':
                    $segments[] = $view;
                    unset($query['view']);
                    break;

                case 'order':
                    $segments[] = 'order';

                    if (!empty($query['no'])) {
                        $segments[] = rawurlencode((string) $query['no']);
                        unset($query['no']);
                    }

                    unset($query['view']);
                    break;
            }
        }

        $parentSegments = parent::build($query);

        if (!empty($parentSegments)) {
            $segments = array_merge($segments, $parentSegments);
        }

        if (isset($query['Itemid'], $query['view'])) {
            $menuItem = $this->menu->getItem((int) $query['Itemid']);

            if ($menuItem && isset($menuItem->query['view']) && $menuItem->query['view'] === $query['view']) {
                unset($query['view']);
            }
        }

        return $segments;
    }

    /**
     * Get the segment for a category view.
     *
     * Required by Joomla's MenuRules to properly resolve Itemid for views with keys.
     * Without this method, getPath() returns boolean true for keyed views,
     * causing MenuRules to assign the lookup array as Itemid instead of an integer.
     *
     * @param string $slug   The category slug
     * @param array  $query  The request query
     *
     * @return array  Segment array keyed by ID
     *
     * @since 0.1.5
     */
    public function getCategorySegment($slug, $query): array
    {
        return !empty($slug) ? [$slug => $slug] : [];
    }

    /**
     * Get the segment for a product view.
     *
     * @param string $slug   The product slug
     * @param array  $query  The request query
     *
     * @return array  Segment array keyed by ID
     *
     * @since 0.1.5
     */
    public function getProductSegment($slug, $query): array
    {
        return !empty($slug) ? [$slug => $slug] : [];
    }

    /**
     * Get the segment for an order view.
     *
     * @param string $no     The order number
     * @param array  $query  The request query
     *
     * @return array  Segment array keyed by ID
     *
     * @since 0.1.5
     */
    public function getOrderSegment($no, $query): array
    {
        return !empty($no) ? [$no => $no] : [];
    }

    /**
     * Map SEF segments back to Joomla query parameters.
     *
     * @since 0.1.5
     */
    public function parse(&$segments)
    {
        if (empty($segments)) {
            return parent::parse($segments);
        }

        $vars  = [];
        $first = array_shift($segments);

        switch ($first) {
            case 'product':
                $vars['view'] = 'product';

                if (!empty($segments)) {
                    $vars['slug'] = urldecode((string) array_shift($segments));
                }

                // Clear remaining segments to prevent Joomla from further parsing
                $segments = [];

                return $vars;

            case 'category':
                // Copy segments before clearing so we can process them
                $pathSegments = array_map(
                    static fn ($segment) => urldecode((string) $segment),
                    $segments
                );

                // Clear segments immediately - we're consuming them all
                $segments = [];

                if (empty($pathSegments)) {
                    $vars['view'] = 'category';

                    return $vars;
                }

                if (\count($pathSegments) === 1 && strcasecmp($pathSegments[0], 'all') === 0) {
                    $vars['view'] = 'category';

                    return $vars;
                }

                $productSlug  = (string) array_pop($pathSegments);
                $productMatch = $this->resolveProductPrimaryPath($productSlug);

                if ($productMatch !== null) {
                    $vars['view'] = 'product';
                    $vars['slug'] = $productSlug;

                    $path = $productMatch['path'] ?? [];

                    if (!empty($path)) {
                        $vars['category_path'] = implode('/', $path);
                        $vars['category_slug'] = end($path);
                    }

                    return $vars;
                }

                $fullPath = array_merge($pathSegments, [$productSlug]);
                $category = $this->resolveCategoryFromPath($fullPath);

                $vars['view'] = 'category';

                if ($category !== null) {
                    $vars['slug'] = $category['slug'];
                    $vars['id']   = $category['id'];
                    $vars['category_path'] = implode('/', $category['path']);
                } else {
                    $vars['slug'] = $productSlug;
                    $vars['category_path'] = implode('/', $fullPath);
                }

                return $vars;

            case 'cart':
                $vars['view'] = 'cart';
                $segments = [];
                return $vars;

            case 'checkout':
                $vars['view'] = 'checkout';
                $segments = [];
                return $vars;

            case 'order':
                $vars['view'] = 'order';

                if (!empty($segments)) {
                    $vars['no'] = urldecode((string) array_shift($segments));
                }

                // Clear remaining segments
                $segments = [];
                return $vars;

            default:
                array_unshift($segments, $first);

                return parent::parse($segments);
        }
    }

    /**
     * Extract an explicit category path from the query.
     *
     * @param array<string, mixed> $query
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    private function extractCategoryPath(array &$query): array
    {
        if (isset($query['category_path'])) {
            $path = CategoryPathHelper::normalisePathSegments($query['category_path']);
            unset($query['category_path']);

            return $path;
        }

        if (isset($query['category_slug'])) {
            $slug = (string) $query['category_slug'];
            unset($query['category_slug']);

            return $this->resolveCategoryPathBySlug($slug);
        }

        if (isset($query['category_id'])) {
            $categoryId = (int) $query['category_id'];
            unset($query['category_id']);

            return $this->resolveCategoryPathById($categoryId);
        }

        return [];
    }

    /**
     * Resolve a product's category path segments.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    private function resolveProductPathSegments(string $productSlug): array
    {
        $resolved = $this->resolveProductPrimaryPath($productSlug);

        return $resolved['path'] ?? [];
    }

    /**
     * Resolve a category path by ID.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    private function resolveCategoryPathById(int $categoryId): array
    {
        if ($categoryId <= 0 || !$this->db) {
            return [];
        }

        return CategoryPathHelper::getPath($this->db, $categoryId);
    }

    /**
     * Resolve a category path by slug.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    private function resolveCategoryPathBySlug(string $slug): array
    {
        if ($slug === '' || !$this->db) {
            return [];
        }

        return CategoryPathHelper::getPathForSlug($this->db, $slug);
    }

    /**
     * Resolve a category from a slug path.
     *
     * @param array<int, string> $segments
     *
     * @return array{id: int, slug: string, path: array<int, string>}|null
     *
     * @since 0.1.5
     */
    private function resolveCategoryFromPath(array $segments): ?array
    {
        if (!$this->db) {
            return null;
        }

        return CategoryPathHelper::resolveByPath($this->db, $segments);
    }

    /**
     * Resolve the primary category path for a product (if defined).
     *
     * @return array{category_id?: int|null, path?: array<int, string>}|null
     *
     * @since 0.1.5
     */
    private function resolveProductPrimaryPath(string $productSlug): ?array
    {
        if ($productSlug === '' || !$this->db) {
            return null;
        }

        $resolved = CategoryPathHelper::getPrimaryPathForProduct($this->db, $productSlug);

        if ($resolved !== null) {
            return $resolved;
        }

        // Allow routing for products without a primary category yet.
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__nxp_easycart_products'))
                ->where($this->db->quoteName('slug') . ' = :productSlug')
                ->bind(':productSlug', $productSlug, \Joomla\Database\ParameterType::STRING)
                ->setLimit(1);

            $this->db->setQuery($query);

            $exists = (int) $this->db->loadResult();

            return $exists > 0 ? ['path' => []] : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Resolve database from the container when not injected.
     *
     * @since 0.1.5
     */
    private function resolveDatabase(): ?DatabaseInterface
    {
        try {
            return Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
