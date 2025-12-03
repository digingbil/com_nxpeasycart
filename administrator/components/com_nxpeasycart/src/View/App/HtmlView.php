<?php

namespace Joomla\Component\Nxpeasycart\Administrator\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\DashboardService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;

/**
 * Basic HTML view for the admin dashboard wrapper.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * View display method.
     *
     * @param string|null $tpl Template file to use
     *
     * @return void
     *
     * @since 0.1.5
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART'));

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

        /** @var SettingsService $settings */
        $settings = $container->get(SettingsService::class);

        $this->dashboardSummary   = $dashboard->getSummary();
        $this->dashboardChecklist = $dashboard->getChecklist();

        // Preload settings for the frontend app
        $this->settingsData = [
            'store' => [
                'name'  => $settings->get('store.name', ''),
                'email' => $settings->get('store.email', ''),
                'phone' => $settings->get('store.phone', ''),
            ],
            'payments' => [
                'configured' => (bool) $settings->get('payments.configured', false),
            ],
            'base_currency'             => ConfigHelper::getBaseCurrency(),
            'checkout_phone_required'   => ConfigHelper::isCheckoutPhoneRequired(),
            'category_page_size'        => ConfigHelper::getCategoryPageSize(),
            'category_pagination_mode'  => ConfigHelper::getCategoryPaginationMode(),
            'auto_send_order_emails'    => ConfigHelper::isAutoSendOrderEmails(),
            'stale_order_cleanup_enabled' => ConfigHelper::isStaleOrderCleanupEnabled(),
            'stale_order_hours'         => ConfigHelper::getStaleOrderHours(),
            'show_advanced_mode'        => ConfigHelper::isShowAdvancedMode(),
        ];

        parent::display($tpl);
    }
}
