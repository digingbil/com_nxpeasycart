<?php

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
use Joomla\Database\DatabaseInterface;

/**
 * Site router registered through Joomla's router factory.
 */
class Router extends RouterView
{
    /**
     * Constructor aligning with Joomla's router factory expectations.
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

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Ensure menu-aligned links do not add redundant view parameters.
     */
    public function build(&$query)
    {
        $segments = [];

        if (isset($query['view'])) {
            $view = (string) $query['view'];

            switch ($view) {
                case 'product':
                    $hasCategory = !empty($query['category_slug']);

                    if ($hasCategory) {
                        $segments[] = 'category';
                        $segments[] = rawurlencode((string) $query['category_slug']);
                        unset($query['category_slug']);
                    } else {
                        $segments[] = 'product';
                    }

                    if (!empty($query['slug'])) {
                        $segments[] = rawurlencode((string) $query['slug']);
                        unset($query['slug']);
                    }

                    unset($query['view']);
                    break;

                case 'category':
                    $segments[] = 'category';

                    if (!empty($query['slug'])) {
                        $segments[] = rawurlencode((string) $query['slug']);
                        unset($query['slug']);
                    }

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
     */
    public function getOrderSegment($no, $query): array
    {
        return !empty($no) ? [$no => $no] : [];
    }

    /**
     * Map SEF segments back to Joomla query parameters.
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
                break;

            case 'category':
                if (!empty($segments)) {
                    $categorySlug = urldecode((string) array_shift($segments));

                    if (!empty($segments)) {
                        $vars['view']          = 'product';
                        $vars['category_slug'] = $categorySlug;
                        $vars['slug']          = urldecode((string) array_shift($segments));
                    } else {
                        $vars['view'] = 'category';
                        $vars['slug'] = $categorySlug;
                    }
                } else {
                    $vars['view'] = 'category';
                }
                break;

            case 'cart':
                $vars['view'] = 'cart';
                break;

            case 'checkout':
                $vars['view'] = 'checkout';
                break;

            case 'order':
                $vars['view'] = 'order';

                if (!empty($segments)) {
                    $vars['no'] = urldecode((string) array_shift($segments));
                }
                break;

            default:
                array_unshift($segments, $first);

                return parent::parse($segments);
        }

        return $vars;
    }
}
