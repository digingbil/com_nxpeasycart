<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\View\Tax;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;

/**
 * Tax rates listing view.
 *
 * @since 0.1.11
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     *
     * @since 0.1.11
     */
    protected array $taxRates = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.11
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_TAX'));
        $this->taxRates = $this->fetchTaxRates();

        parent::display($tpl);
    }

    private function fetchTaxRates(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(TaxService::class)) {
            $container->set(
                TaxService::class,
                static fn ($container) => new TaxService($container->get(DatabaseInterface::class))
            );
        }

        /** @var TaxService $service */
        $service = $container->get(TaxService::class);

        return $service->paginate([], 50, 0);
    }
}
