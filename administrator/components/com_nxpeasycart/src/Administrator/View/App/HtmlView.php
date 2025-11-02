<?php

namespace Nxp\EasyCart\Admin\Administrator\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\DashboardService;
use Nxp\EasyCart\Admin\Administrator\Service\SettingsService;

/**
 * Basic HTML view for the admin dashboard wrapper.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * View display method.
     *
     * @param string|null $tpl Template file to use
     *
     * @return void
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART'));

        $container = Factory::getContainer();

        if (!$container->has(SettingsService::class)) {
            $container->set(
                SettingsService::class,
                static fn ($container) => new SettingsService($container->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(DashboardService::class)) {
            $container->set(
                DashboardService::class,
                static fn ($container) => new DashboardService(
                    $container->get(DatabaseInterface::class),
                    $container->get(SettingsService::class)
                )
            );
        }

        /** @var DashboardService $dashboard */
        $dashboard = $container->get(DashboardService::class);

        $this->dashboardSummary   = $dashboard->getSummary();
        $this->dashboardChecklist = $dashboard->getChecklist();

        parent::display($tpl);
    }
}
