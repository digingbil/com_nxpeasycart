<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;

/**
 * Order confirmation model powering storefront order summary.
 */
class OrderModel extends BaseDatabaseModel
{
    private ?array $order = null;

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('order.id', $input->getInt('id'));
        $this->setState('order.number', $input->getCmd('no', ''));
    }

    /**
     * Retrieve the order payload for confirmation display.
     */
    public function getItem(): ?array
    {
        if ($this->order !== null) {
            return $this->order;
        }

        $container = Factory::getContainer();

        if (!$container->has(OrderService::class)) {
            $container->set(
                OrderService::class,
                static fn ($container) => new OrderService($container->get(\Joomla\Database\DatabaseInterface::class))
            );
        }

        $service = $container->get(OrderService::class);

        $id     = (int) $this->getState('order.id');
        $number = (string) $this->getState('order.number');

        $order = null;

        if ($id > 0) {
            $order = $service->get($id);
        } elseif ($number !== '') {
            $order = $service->getByNumber($number);
        }

        $this->order = $order;

        return $this->order;
    }
}
