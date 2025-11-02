<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\CustomerService;
use RuntimeException;

/**
 * Customers API controller placeholder.
 */
class CustomersController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array                     $config  Controller configuration
     * @param MVCFactoryInterface|null  $factory MVC factory
     * @param CMSApplicationInterface|null $app  Application instance
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    /**
     * {@inheritDoc}
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
