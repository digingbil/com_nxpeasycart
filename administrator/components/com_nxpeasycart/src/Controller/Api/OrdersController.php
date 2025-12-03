<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\InvoiceService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use RuntimeException;

/**
 * Orders API controller.
 *
 * @since 0.1.5
 */
class OrdersController extends AbstractJsonController
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
     * @since 0.1.5
     */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'list', 'browse' => $this->list(),
            'store', 'create' => $this->store(),
            'show', 'detail' => $this->show(),
            'transition', 'state' => $this->transition(),
            'bulktransition', 'bulk' => $this->bulkTransition(),
            'note'  => $this->note(),
            'tracking' => $this->tracking(),
            'sendemail' => $this->sendEmail(),
            'invoice' => $this->invoice(),
            'export' => $this->export(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * List orders.
     *
     * @return JsonResponse Paginated list of orders
     *
     * @since 0.1.5
     */
    protected function list(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit  = $this->input->getInt('limit', 20);
        $start  = $this->input->getInt('start', 0);
        $search = $this->input->getString('search', '');
        $state  = $this->input->getCmd('state', '');

        $service = $this->getOrderService();
        $result  = $service->paginate(
            [
                'search' => $search,
                'state'  => $state,
            ],
            $limit,
            $start
        );

        return $this->respond($result);
    }

    /**
     * Create a new order.
     *
     * @return JsonResponse Newly created order
     * @throws \Throwable
     *
     * @since 0.1.5
     */
    protected function store(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $payload = $this->decodePayload();
        $actorId = $this->app?->getIdentity()?->id ?? null;

        $service = $this->getOrderService();
        $order   = $service->create($payload, $actorId);

        return $this->respond(['order' => $order], 201);
    }

    /**
     * Show a single order by id or order number.
     *
     * @return JsonResponse Order details
     *
     * @since 0.1.5
     */
    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $id      = $this->input->getInt('id');
        $orderNo = $this->input->getString('order_no', '');

        $service = $this->getOrderService();
        $order   = null;

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
     *
     * @return JsonResponse Updated order details
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function transition(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $id      = $this->requireId();
        $payload = $this->decodePayload();

        $state = isset($payload['state']) ? (string) $payload['state'] : '';

        if ($state === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'), 400);
        }

        $service = $this->getOrderService();
        $actorId = $this->app?->getIdentity()?->id ?? null;

        try {
            $order = $service->transitionState($id, $state, $actorId);
        } catch (RuntimeException $e) {
            // Check if this is an invalid state transition error
            if (str_contains($e->getMessage(), 'Invalid state transition')) {
                return $this->respond([
                    'error'   => true,
                    'message' => $e->getMessage(),
                ], 400);
            }

            throw $e;
        }

        return $this->respond(['order' => $order]);
    }

    /**
     * Transition (change state) multiple orders.
     *
     * @return JsonResponse Updated order details
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function bulkTransition(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $ids = isset($payload['ids']) && \is_array($payload['ids'])
            ? array_map('intval', $payload['ids'])
            : [];

        if (empty($ids)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        $state = isset($payload['state']) ? (string) $payload['state'] : '';

        if (trim($state) === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'), 400);
        }

        $service = $this->getOrderService();
        $actorId = $this->app?->getIdentity()?->id ?? null;
        $result  = $service->bulkTransition($ids, $state, $actorId);

        return $this->respond([
            'updated' => $result['updated'],
            'failed'  => $result['failed'],
        ]);
    }

    /**
     * Record a note against an order.
     *
     * @return JsonResponse Updated order details
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function note(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        $message = isset($payload['message']) ? (string) $payload['message'] : '';

        if (trim($message) === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOTE_REQUIRED'), 400);
        }

        $actorId = $this->app?->getIdentity()?->id ?? null;
        $service = $this->getOrderService();
        $order   = $service->addNote($id, $message, $actorId);

        return $this->respond(['order' => $order]);
    }

    /**
     * Generate and return an invoice PDF (base64) for an order.
     *
     * @return JsonResponse Invoice PDF (base64)
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function invoice(): JsonResponse
    {
        $this->assertCan('core.manage');
        $this->assertToken();

        $payload = $this->decodePayload();

        $id      = isset($payload['id']) ? (int) $payload['id'] : 0;
        $orderNo = isset($payload['order_no']) ? (string) $payload['order_no'] : '';

        if ($id <= 0 && $orderNo === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        $orders  = $this->getOrderService();
        $order   = $id > 0 ? $orders->get($id) : $orders->getByNumber($orderNo);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'), 404);
        }

        $invoiceService = $this->getInvoiceService();
        $invoice        = $invoiceService->generateInvoice($order);

        $filename = $invoice['filename'] ?? ('invoice-' . ($order['order_no'] ?? 'order') . '.pdf');
        $content  = isset($invoice['content']) ? base64_encode((string) $invoice['content']) : '';

        if ($content === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_SERIALISE_FAILED'), 500);
        }

        return $this->respond([
            'invoice' => [
                'filename' => $filename,
                'content'  => $content,
            ],
        ]);
    }

    /**
     * Export orders to CSV (Excel-compatible).
     *
     * @return JsonResponse Export details (filename and content)
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function export(): JsonResponse
    {
        $this->assertCan('core.manage');

        $filters = [
            'search'    => $this->input->getString('search', ''),
            'state'     => $this->input->getCmd('state', ''),
            'date_from' => $this->input->getString('date_from', ''),
            'date_to'   => $this->input->getString('date_to', ''),
        ];

        $service = $this->getOrderService();
        $export  = $service->exportToCsv($filters);

        return $this->respond([
            'export' => [
                'filename' => $export['filename'],
                'content'  => base64_encode($export['content']),
            ],
        ]);
    }

    /**
     * Update order tracking metadata.
     *
     * @return JsonResponse Updated order details
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function tracking(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        $tracking = [
            'carrier'         => $payload['carrier'] ?? '',
            'tracking_number' => $payload['tracking_number'] ?? '',
            'tracking_url'    => $payload['tracking_url'] ?? '',
            'mark_fulfilled'  => !empty($payload['mark_fulfilled']),
        ];

        $actorId = $this->app?->getIdentity()?->id ?? null;
        $service = $this->getOrderService();
        $order   = $service->updateTracking($id, $tracking, $actorId);

        return $this->respond(['order' => $order]);
    }

    /**
     * Manually send an order notification email.
     *
     * @return JsonResponse Updated order details
     * @throws RuntimeException
     *
     * @since 0.1.5
     */
    protected function sendEmail(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $type = isset($payload['type']) ? trim((string) $payload['type']) : '';

        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_ID'), 400);
        }

        if (!\in_array($type, ['shipped', 'refunded'], true)) { //Possibly add other email types in the future
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_EMAIL_TYPE'), 400);
        }

        $service = $this->getOrderService();
        $order = $service->get($id);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'), 404);
        }

        $mailService = $this->getMailService();
        $actorId = $this->app?->getIdentity()?->id ?? null;

        try {
            if ($type === 'shipped') {
                $mailService->sendOrderShipped($order, [
                    'carrier'         => $order['carrier'] ?? null,
                    'tracking_number' => $order['tracking_number'] ?? null,
                    'tracking_url'    => $order['tracking_url'] ?? null,
                ]);
            } elseif ($type === 'refunded') {
                $mailService->sendOrderRefunded($order);
            }

            // Log the email sent event
            $container = Factory::getContainer();
            $auditService = $container->has(AuditService::class)
                ? $container->get(AuditService::class)
                : null;

            if ($auditService) {
                $auditService->record(
                    'order',
                    $id,
                    'order.email.sent',
                    ['type' => $type, 'manual' => true],
                    $actorId
                );
            }

            // Refresh order to get updated timeline
            $order = $service->get($id);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                Text::sprintf('COM_NXPEASYCART_ERROR_SEND_EMAIL_FAILED', $e->getMessage()),
                500,
                $e
            );
        }

        return $this->respond(['order' => $order]);
    }

    /**
     * Decode the JSON request body.
     *
     * @return array Decoded JSON payload
     * @throws RuntimeException
     *
     * @since 0.1.5
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
     *
     * @return OrderService Resolved order service
     *
     * @since 0.1.5
     */
    private function getOrderService(): OrderService
    {
        $container = Factory::getContainer();

        if (!$container->has(OrderService::class)) {
            $container->set(
                OrderService::class,
                static function ($container): OrderService {
                    if (!$container->has(AuditService::class)) {
                        $container->set(
                            AuditService::class,
                            static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
                        );
                    }

                    return new OrderService(
                        $container->get(DatabaseInterface::class),
                        $container->get(AuditService::class)
                    );
                }
            );
        }

        return $container->get(OrderService::class);
    }

    /**
     * Resolve the invoice service from the DI container.
     *
     * @return InvoiceService
     *
     * @since 0.1.5
     */
    private function getInvoiceService(): InvoiceService
    {
        $container = Factory::getContainer();

        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if (
            (!$container->has(\Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService::class)
            || !$container->has(\Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService::class)
            || !$container->has(InvoiceService::class))
            && is_file($providerPath)
        ) {
            $container->registerServiceProvider(require $providerPath);
        }

        // Fallback: create InvoiceService manually then
        if (!$container->has(InvoiceService::class)) {
            $container->set(
                InvoiceService::class,
                static function ($container): InvoiceService {
                    return new InvoiceService(
                        $container->get(\Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService::class),
                        $container->get(\Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService::class)
                    );
                }
            );
        }

        return $container->get(InvoiceService::class);
    }

    /**
     * Resolve the mail service from the Container.
     *
     * @return MailService
     *
     * @since 0.1.5
     */
    private function getMailService(): MailService
    {
        $container = Factory::getContainer();

        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if (!$container->has(MailService::class) && is_file($providerPath)) {
            $container->registerServiceProvider(require $providerPath);
        }

        if (!$container->has(MailService::class)) {
            // Fallback: create MailService manually
            $container->set(
                MailService::class,
                static function ($container): MailService {

                    //New J! 6+ way to create a mailer
                    $mailer = $container->get(MailerFactoryInterface::class)->createMailer();
                    return new MailService($mailer);
                }
            );
        }

        return $container->get(MailService::class);
    }
}
