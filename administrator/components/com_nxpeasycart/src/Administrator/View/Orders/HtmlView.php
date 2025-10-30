<?php

namespace Nxp\EasyCart\Admin\Administrator\View\Orders;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\OrderService;

/**
 * Orders listing view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $orders = [
        'items' => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART_MENU_ORDERS'));
        $this->orders = $this->fetchOrders();

        parent::display($tpl);
    }

    /**
     * Retrieve orders for the fallback server-rendered table.
     */
    private function fetchOrders(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(OrderService::class)) {
            $container->set(
                OrderService::class,
                static fn ($container): OrderService => new OrderService($container->get(DatabaseInterface::class))
            );
        }

        /** @var OrderService $service */
        $service = $container->get(OrderService::class);

        return $service->paginate([], 20, 0);
    }
}
