<?php

namespace Joomla\Component\Nxpeasycart\Site\Router;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;

/**
 * Router rule ensuring landing view menu aliases resolve even when template routers bypass Joomla's menu lookup.
 */
class LandingAliasRule
{
    /**
     * Menu instance used to locate component menu items.
     */
    private AbstractMenu $menu;

    /**
     * Cached lookup keyed by lowercased route/alias.
     *
     * @var array<string, object>|null
     */
    private ?array $landingMenuMap = null;

    public function __construct(AbstractMenu $menu)
    {
        $this->menu = $menu;
    }

    /**
     * Router parse hook registered during service provider bootstrap.
     */
    public function __invoke(SiteRouter $router, Uri $uri): void
    {
        if ($uri->getVar('option')) {
            return;
        }

        $path = trim($uri->getPath(), '/');

        if ($path === '') {
            return;
        }

        \Joomla\CMS\Log\Log::add(
            'LandingAliasRule invoked for path ' . $path,
            \Joomla\CMS\Log\Log::DEBUG,
            'com_nxpeasycart'
        );

        $menuItem = $this->lookupLandingMenu($path);

        if ($menuItem === null) {
            return;
        }

        $uri->setVar('option', 'com_nxpeasycart');
        $uri->setVar('Itemid', $menuItem->id);
        $uri->setVar('view', 'landing');
        $uri->setPath('');

    }

    /**
     * Locate a landing menu whose alias or stored route matches the provided path.
     */
    private function lookupLandingMenu(string $path): ?object
    {
        if ($this->landingMenuMap === null) {
            $this->primeLandingMenuMap();
        }

        if ($this->landingMenuMap === null) {
            return null;
        }

        $normalized = strtolower($path);

        return $this->landingMenuMap[$normalized] ?? null;
    }

    /**
     * Build a lookup of landing menu aliases/routes for quick comparison.
     */
    private function primeLandingMenuMap(): void
    {
        $this->landingMenuMap = [];

        try {
            $component = ComponentHelper::getComponent('com_nxpeasycart');
        } catch (\Throwable $exception) {
            $this->landingMenuMap = null;

            return;
        }

        $items = $this->menu->getItems('component_id', $component->id) ?? [];

        foreach ($items as $item) {
            $view = (string) ($item->query['view'] ?? '');

            if ($view !== 'landing') {
                continue;
            }

            $candidates = [
                strtolower(trim((string) ($item->route ?? ''), '/')),
                strtolower(trim((string) ($item->alias ?? ''), '/')),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                $this->landingMenuMap[$candidate] = $item;
            }
        }

        if ($this->landingMenuMap === []) {
            $this->landingMenuMap = null;
        }
    }
}
