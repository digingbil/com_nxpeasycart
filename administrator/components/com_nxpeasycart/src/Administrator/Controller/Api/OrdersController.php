<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Nxp\EasyCart\Admin\Administrator\Service\OrderService;

/**
 * Orders API controller.
 */
class OrdersController extends AbstractJsonController
{
    /**
     * Constructor.
     *
     * @param array                        $config  Controller configuration
     * @param MVCFactoryInterface|null     $factory MVC factory
     * @param CMSApplicationInterface|null $app     Application instance
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
            'list', 'browse'          => $this->list(),
            'store', 'create'         => $this->store(),
            'show', 'detail'          => $this->show(),
            'transition', 'state'     => $this->transition(),
            default                   => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * List orders.
     */
    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit = $this->input->getInt('limit', 20);
        $start = $this->input->getInt('start', 0);
        $search = $this->input->getString('search', '');
        $state = $this->input->getCmd('state', '');

        $service = $this->getOrderService();
        $result = $service->paginate(
            [
                'search' => $search,
                'state' => $state,
            ],
            $limit,
            $start
        );

        return $this->respond($result);
    }

    /**
     * Create a new order.
     */
    protected function store(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $payload = $this->decodePayload();

        $service = $this->getOrderService();
        $order = $service->create($payload);

        return $this->respond(['order' => $order], 201);
    }

    /**
     * Show a single order by id or order number.
     */
    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $id = $this->input->getInt('id');
        $orderNo = $this->input->getString('order_no', '');

        $service = $this->getOrderService();
        $order = null;

        if ($id > 0) {
            $order = $service->get($id);
        } elseif ($orderNo !== '') {
            $order = $service->getByNumber($orderNo);
        }

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'), 404);
        }

        return $this->respond(['order' => $order]);
    }

    /**
     * Transition an order to a new state.
     */
    protected function transition(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id = $this->requireId();
        $payload = $this->decodePayload();

        $state = isset($payload['state']) ? (string) $payload['state'] : '';

        if ($state === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'), 400);
        }

        $service = $this->getOrderService();
        $order = $service->transitionState($id, $state);

        return $this->respond(['order' => $order]);
    }

    /**
     * Decode the JSON request body.
     */
    private function decodePayload(): array
    {
        $raw = $this->input->json->getRaw();

        if ($raw === null || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400);
        }

        return (array) $data;
    }

    /**
     * Resolve the order service from the DI container.
     */
    private function getOrderService(): OrderService
    {
        $container = Factory::getContainer();

        if (!$container->has(OrderService::class)) {
            $container->set(
                OrderService::class,
                static fn ($container): OrderService => new OrderService($container->get(DatabaseInterface::class))
            );
        }

        return $container->get(OrderService::class);
    }
}
