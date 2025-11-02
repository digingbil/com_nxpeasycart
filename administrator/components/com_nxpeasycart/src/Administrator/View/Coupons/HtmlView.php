<?php

namespace Nxp\EasyCart\Admin\Administrator\View\Coupons;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\CouponService;

/**
 * Coupons listing view placeholder.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $coupons = [
        'items'      => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART_MENU_COUPONS'));
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
