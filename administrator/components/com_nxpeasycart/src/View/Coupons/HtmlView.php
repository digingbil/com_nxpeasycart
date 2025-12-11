<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\View\Coupons;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CouponService;

/**
 * Coupons listing view placeholder.
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
    protected array $coupons = [
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
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_COUPONS'));
        $this->coupons = $this->fetchCoupons();

        parent::display($tpl);
    }

    private function fetchCoupons(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(CouponService::class)) {
            $container->set(
                CouponService::class,
                static fn ($container) => new CouponService($container->get(DatabaseInterface::class))
            );
        }

        /** @var CouponService $service */
        $service = $container->get(CouponService::class);

        return $service->paginate([], 20, 0);
    }
}
