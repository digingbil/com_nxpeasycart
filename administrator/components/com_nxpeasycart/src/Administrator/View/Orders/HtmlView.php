<?php

namespace Joomla\Component\Nxpeasycart\Administrator\View\Orders;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;

/**
 * Orders listing view.
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
    protected array $orders = [
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
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_ORDERS'));
        $this->orders = $this->fetchOrders();

        parent::display($tpl);
    }

    /**
     * Retrieve orders for the fallback server-rendered table.
     *
     * @since 0.1.5
     */
    private function fetchOrders(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(AuditService::class)) {
            $container->set(
                AuditService::class,
                static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(OrderService::class)) {
            $container->set(
                OrderService::class,
                static fn ($container): OrderService => new OrderService(
                    $container->get(DatabaseInterface::class),
                    $container->get(AuditService::class)
                )
            );
        }

        /** @var OrderService $service */
        $service = $container->get(OrderService::class);

        return $service->paginate([], 20, 0);
    }
}
