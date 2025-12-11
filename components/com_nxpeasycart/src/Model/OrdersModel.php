<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Database\DatabaseInterface;

/**
 * Orders list model for authenticated storefront users.
 *
 * @since 0.1.5
 */
class OrdersModel extends BaseDatabaseModel
{
    /**
     * @var array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private array $orders = [];

    /**
     * @var array<string, int>
     *
     * @since 0.1.5
     */
    private array $pagination = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $limit = $input->getInt('limit', (int) $app->get('list_limit', 20));
        $start = $input->getInt('start', 0);

        $this->setState('list.limit', $limit > 0 ? $limit : 20);
        $this->setState('list.start', $start >= 0 ? $start : 0);
    }

    /**
     * Return the current user's orders.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    public function getItems(): array
    {
        if (!empty($this->orders)) {
            return $this->orders;
        }

        $userId = $this->getUserId();

        if ($userId === null) {
            return [];
        }

        $container = Factory::getContainer();
        $this->bootstrapContainer($container);

        $service = $container->get(OrderService::class);

        $limit = (int) $this->getState('list.limit', 20);
        $start = (int) $this->getState('list.start', 0);

        $result = $service->paginate(['user_id' => $userId], $limit, $start);

        $this->orders     = $result['items'] ?? [];
        $this->pagination = $result['pagination'] ?? [];

        return $this->orders;
    }

    /**
     * Pagination metadata for the orders list.
     *
     * @return array<string, int>
     *
     * @since 0.1.5
     */
    public function getPagination(): array
    {
        return $this->pagination;
    }

    private function getUserId(): ?int
    {
        try {
            $identity = Factory::getApplication()->getIdentity();
        } catch (\Throwable $exception) {
            return null;
        }

        if (!$identity || $identity->guest) {
            return null;
        }

        return isset($identity->id) ? (int) $identity->id : null;
    }

    private function bootstrapContainer($container): void
    {
        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if (!$container->has(OrderService::class) && is_file($providerPath)) {
            $container->registerServiceProvider(require $providerPath);
        }

        if (!$container->has(OrderService::class) && $container->has(DatabaseInterface::class)) {
            $container->set(
                OrderService::class,
                static fn ($c) => new OrderService($c->get(DatabaseInterface::class))
            );
        }
    }
}
