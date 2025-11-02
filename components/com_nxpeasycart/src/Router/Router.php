<?php

namespace Nxp\EasyCart\Site\Router;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

class Router extends RouterView
{
    public function __construct(CMSApplicationInterface $app, AbstractMenu $menu)
    {
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
}
