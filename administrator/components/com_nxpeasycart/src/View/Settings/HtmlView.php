<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\View\Settings;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;

/**
 * Settings view placeholder.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $settingsData = [];

    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $taxRates = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $shippingRules = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_SETTINGS'));
        $this->loadSettingsPayloads();

        parent::display($tpl);
    }

    private function loadSettingsPayloads(): void
    {
        $container = Factory::getContainer();

        if (!$container->has(SettingsService::class)) {
            $container->set(
                SettingsService::class,
                static fn ($container) => new SettingsService($container->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(TaxService::class)) {
            $container->set(
                TaxService::class,
                static fn ($container) => new TaxService($container->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(ShippingRuleService::class)) {
            $container->set(
                ShippingRuleService::class,
                static fn ($container) => new ShippingRuleService($container->get(DatabaseInterface::class))
            );
        }

        /** @var SettingsService $settings */
        $settings = $container->get(SettingsService::class);

        $this->settingsData = [
            'store' => [
                'name'  => (string) $settings->get('store.name', ''),
                'email' => (string) $settings->get('store.email', ''),
                'phone' => (string) $settings->get('store.phone', ''),
            ],
            'payments' => [
                'configured' => (bool) $settings->get('payments.configured', false),
            ],
            'base_currency' => ConfigHelper::getBaseCurrency(),
        ];

        $this->taxRates      = $container->get(TaxService::class)->paginate([], 50, 0);
        $this->shippingRules = $container->get(ShippingRuleService::class)->paginate([], 50, 0);
    }
}
