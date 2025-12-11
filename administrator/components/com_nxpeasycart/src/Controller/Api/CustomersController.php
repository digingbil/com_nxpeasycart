<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CustomerService;
use RuntimeException;

/**
 * Customers API controller placeholder.
 *
 * @since 0.1.5
 */
class CustomersController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array                        $config  Controller configuration
     * @param MVCFactoryInterface|null     $factory MVC factory
     * @param CMSApplicationInterface|null $app     Application instance
     *
     * @since 0.1.5
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $task The task name
     * @return JsonResponse
     *
     * @since 0.1.5
     */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'list', 'browse' => $this->list(),
            'show', 'detail' => $this->show(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * List customers.
     *
     * @return JsonResponse
     *
     * @since 0.1.5
     */
    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);
        $search = $this->input->getString('search', '');

        $service = $this->getCustomerService();
        $result  = $service->paginate([
            'search' => $search,
        ], $limit, $start);

        return $this->respond($result);
    }

    /**
     * Show a single customer by email.
     *
     * @return JsonResponse
     * @throws RuntimeException When email is missing or customer not found
     *
     * @since 0.1.5
     */
    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $email = $this->input->getString('email', '');

        if (trim($email) === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CUSTOMER_EMAIL_REQUIRED'), 400);
        }

        $service  = $this->getCustomerService();
        $customer = $service->get($email);

        if (!$customer) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CUSTOMER_NOT_FOUND'), 404);
        }

        return $this->respond(['customer' => $customer]);
    }

    private function getCustomerService(): CustomerService
    {
        $container = Factory::getContainer();

        if (!$container->has(CustomerService::class)) {
            $container->set(
                CustomerService::class,
                static fn ($container) => new CustomerService($container->get(DatabaseInterface::class))
            );
        }

        return $container->get(CustomerService::class);
    }
}
