<?php

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Uri\Uri;

/**
 * Routing helpers for storefront links.
 */
class RouteHelper
{
    /**
     * Resolve the cleanest available base route for the category listing.
     */
    public static function getCategoryBaseRoute(): string
    {
        $app  = Factory::getApplication();
        $menu = $app->getMenu();

        $itemId = self::findCategoryMenuId($menu);

        if ($itemId) {
            return 'index.php?Itemid=' . $itemId;
        }

        $root = rtrim(Uri::root(true), '/');

        return ($root === '' ? '' : $root) . '/component/nxpeasycart/category';
    }

    private static function findCategoryMenuId(AbstractMenu $menu): ?int
    {
        $active = $menu->getActive();

        if (
            $active
            && $active->component === 'com_nxpeasycart'
            && ($active->query['view'] ?? '') === 'category'
        ) {
            return (int) $active->id;
        }

        $items = $menu->getItems('component', 'com_nxpeasycart') ?: [];

        foreach ($items as $item) {
            if (($item->query['view'] ?? '') === 'category') {
                return (int) $item->id;
            }
        }

        return null;
    }
}
