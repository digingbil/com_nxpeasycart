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
        $segments = parent::build($query);

        if (isset($query['Itemid'], $query['view'])) {
            $menuItem = $this->menu->getItem((int) $query['Itemid']);

            if ($menuItem && isset($menuItem->query['view']) && $menuItem->query['view'] === $query['view']) {
                unset($query['view']);
            }
        }

        return $segments;
    }
}
