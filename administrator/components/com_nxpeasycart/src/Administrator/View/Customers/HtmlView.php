<?php

namespace Nxp\EasyCart\Admin\Administrator\View\Customers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\CustomerService;

/**
 * Customers listing view placeholder.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $customers = [
        'items' => [],
        'pagination' => [],
    ];

    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART_MENU_CUSTOMERS'));
        $this->customers = $this->fetchCustomers();

        parent::display($tpl);
    }

    private function fetchCustomers(): array
    {
        $container = Factory::getContainer();

        if (!$container->has(CustomerService::class)) {
            $container->set(
                CustomerService::class,
                static fn ($container) => new CustomerService($container->get(DatabaseInterface::class))
            );
        }

        /** @var CustomerService $service */
        $service = $container->get(CustomerService::class);

        return $service->paginate([], 20, 0);
    }
}
