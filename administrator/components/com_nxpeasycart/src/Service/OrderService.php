<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use JsonException;
use Joomla\Component\Nxpeasycart\Administrator\Event\EasycartEventDispatcher;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Order persistence and aggregation service.
 *
 * @since 0.1.5
 */
class OrderService
{
    private const ORDER_STATES = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

    /**
     * Valid state transitions for the order state machine.
     * Format: 'from_state' => ['allowed_to_state_1', 'allowed_to_state_2', ...]
     *
     * @since 0.1.9
     */
    private const VALID_TRANSITIONS = [
        'cart'      => ['pending', 'canceled'],
        'pending'   => ['paid', 'canceled'],
        'paid'      => ['fulfilled', 'refunded', 'canceled'],
        'fulfilled' => ['refunded'],
        'refunded'  => [],  // terminal state
        'canceled'  => [],  // terminal state
    ];

    /**
     * @var DatabaseInterface
     *
     * @since 0.1.5
     */
    private DatabaseInterface $db;

    private ?AuditService $audit = null;

    private function getAuditService(): AuditService
    {
        if ($this->audit === null) {
            $container = Factory::getContainer();

            if (!$container->has(AuditService::class)) {
                $container->set(
                    AuditService::class,
                    static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
                );
            }

            $this->audit = $container->get(AuditService::class);
        }

        return $this->audit;
    }

    /**
     * OrderService constructor.
     *
     * @param DatabaseInterface $db Database connector
     *
     * @since 0.1.5
     */
    public function __construct(DatabaseInterface $db, ?AuditService $audit = null)
    {
        $this->db    = $db;
        $this->audit = $audit;
    }

    /**
     * Create a new order with items and return the stored record.
     *
     * Expected payload structure:
     * [
     *   'email' => 'customer@example.com',
     *   'billing' => [...],
     *   'shipping' => [...],
     *   'items' => [
     *     ['sku' => 'ABC', 'title' => 'Item', 'qty' => 1, 'unit_price_cents' => 1299, 'tax_rate' => '0.00', 'product_id' => 1, 'variant_id' => 2]
     *   ],
     *   'user_id' => 123,
     *   'locale' => 'en-GB',
     *   'state' => 'pending',
     *   'currency' => 'USD',
     *   'tax_cents' => 0,
     *   'shipping_cents' => 0,
     *   'discount_cents' => 0,
     *   'order_no' => 'EC-12345678'
     * ]
     *
     * @since 0.1.5
     */
    public function create(array $payload, ?int $actorId = null): array
    {
        $normalised = $this->normaliseOrderPayload($payload);
        $items      = $this->normaliseOrderItems($payload['items'] ?? []);

        if (empty($items)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEMS_REQUIRED'));
        }

        $totals = $this->computeTotals($items, $normalised);
        $statusTimestamp   = $this->currentTimestamp();
        $publicToken       = $this->ensureUniquePublicToken();
        $fulfillmentEvents = [$this->buildStatusEvent($normalised['state'], $statusTimestamp, $actorId)];

        $this->db->transactionStart();

        try {
            // Lock and reserve stock to prevent overselling.
            $this->reserveStockForItems($items);

            $order = (object) [
                'order_no'       => $normalised['order_no'],
                'user_id'        => $normalised['user_id'],
                'email'          => $normalised['email'],
                'billing'        => $this->encodeJson($normalised['billing']),
                'shipping'       => $normalised['shipping'] === null ? null : $this->encodeJson($normalised['shipping']),
                'subtotal_cents' => $totals['subtotal_cents'],
                'tax_cents'      => $totals['tax_cents'],
                'tax_rate'       => $normalised['tax_rate'],
                'tax_inclusive'  => $normalised['tax_inclusive'] ? 1 : 0,
                'shipping_cents' => $totals['shipping_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents'    => $totals['total_cents'],
                'currency'       => $normalised['currency'],
                'state'          => $normalised['state'],
                'payment_method' => $normalised['payment_method'],
                'locale'         => $normalised['locale'],
                'public_token'   => $publicToken,
                'status_updated_at' => $statusTimestamp,
                'carrier'        => null,
                'tracking_number'=> null,
                'tracking_url'   => null,
                'fulfillment_events' => $this->encodeJson($fulfillmentEvents),
            ];

            $this->db->insertObject('#__nxp_easycart_orders', $order);

            $orderId = (int) $this->db->insertid();

            foreach ($items as $item) {
                $itemObject = (object) [
                    'order_id'         => $orderId,
                    'product_id'       => $item['product_id'],
                    'variant_id'       => $item['variant_id'],
                    'sku'              => $item['sku'],
                    'title'            => $item['title'],
                    'qty'              => $item['qty'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'tax_rate'         => $item['tax_rate'],
                    'total_cents'      => $item['total_cents'],
                ];

                $this->db->insertObject('#__nxp_easycart_order_items', $itemObject);
            }

            $this->db->transactionCommit();
        } catch (Throwable $exception) {
            $this->db->transactionRollback();
            // Surface the original failure message so callers can render it.
            throw $exception;
        }

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.created',
            [
                'order_no'    => $normalised['order_no'],
                'state'       => $normalised['state'],
                'total_cents' => $totals['total_cents'],
            ],
            $actorId
        );

        $order = $this->getByNumber($normalised['order_no']);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        // Dispatch plugin event: onNxpEasycartAfterOrderCreate
        EasycartEventDispatcher::afterOrderCreate($order);

        return $order;
    }

    /**
     * Fetch an order by internal identifier.
     *
     * @since 0.1.5
     */
    public function get(int $orderId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        return $this->mapOrderRow($row, null, true);
    }

    /**
     * Fetch an order by order number.
     *
     * @since 0.1.5
     */
    public function getByNumber(string $orderNo): ?array
    {
        $orderNo = trim($orderNo);

        if ($orderNo === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('order_no') . ' = :orderNo')
            ->bind(':orderNo', $orderNo, ParameterType::STRING);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        return $this->mapOrderRow($row, null, true);
    }

    /**
     * Fetch an order by public token.
     *
     * @since 0.1.5
     */
    public function getByPublicToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('public_token') . ' = :token')
            ->bind(':token', $token, ParameterType::STRING);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        return $this->mapOrderRow($row, null, true);
    }

    /**
     * Update an order state and return the updated representation.
     *
     * @since 0.1.5
     */
    public function transitionState(int $orderId, string $state, ?int $actorId = null): array
    {
        $state = strtolower(trim($state));

        if (!\in_array($state, self::ORDER_STATES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'));
        }

        $current = $this->get($orderId);

        if (!$current) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        if ($current['state'] === $state) {
            return $current;
        }

        // Validate state transition using state machine guards
        if (!$this->isValidTransition($current['state'], $state)) {
            throw new RuntimeException(
                Text::sprintf(
                    'COM_NXPEASYCART_ERROR_ORDER_STATE_TRANSITION_INVALID',
                    $current['state'],
                    $state
                )
            );
        }

        $timestamp = $this->currentTimestamp();
        $events    = $this->normaliseFulfillmentEvents($current['fulfillment_events'] ?? []);
        $events[]  = $this->buildStatusEvent($state, $timestamp, $actorId, $current['state']);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->set($this->db->quoteName('status_updated_at') . ' = :statusUpdatedAt')
            ->set($this->db->quoteName('fulfillment_events') . ' = :fulfillmentEvents')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':state', $state, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER)
            ->bind(':statusUpdatedAt', $timestamp, ParameterType::STRING)
            ->bind(':fulfillmentEvents', $this->encodeJson($events), ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.state.transitioned',
            [
                'from' => $current['state'],
                'to'   => $state,
            ],
            $actorId
        );

        $updated = $this->get($orderId);

        if (!$updated) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        // Dispatch plugin event: onNxpEasycartAfterOrderStateChange
        EasycartEventDispatcher::afterOrderStateChange(
            $updated,
            $current['state'],
            $state,
            $actorId
        );

        // Send transactional emails based on state transition
        $this->sendStateTransitionEmail($updated, $current['state'], $state);

        return $updated;
    }

    /**
     * Send appropriate email notification based on state transition.
     *
     * @since 0.1.5
     */
    private function sendStateTransitionEmail(array $order, string $fromState, string $toState): void
    {
        // Check if auto-send is enabled in settings
        if (!ConfigHelper::isAutoSendOrderEmails()) {
            return;
        }

        // Only send emails for specific state transitions
        if ($toState === 'fulfilled' && $fromState !== 'fulfilled') {
            $this->sendShippedEmail($order);
        } elseif ($toState === 'refunded' && $fromState !== 'refunded') {
            $this->sendRefundedEmail($order);
        }
    }

    /**
     * Send order shipped notification email.
     *
     * @since 0.1.5
     */
    private function sendShippedEmail(array $order): void
    {
        try {
            $mailService = $this->getMailService();

            if ($mailService === null) {
                return;
            }

            $mailService->sendOrderShipped($order, [
                'carrier'         => $order['carrier'] ?? null,
                'tracking_number' => $order['tracking_number'] ?? null,
                'tracking_url'    => $order['tracking_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail the state transition
            $this->getAuditService()->record(
                'order',
                (int) $order['id'],
                'order.email.failed',
                ['type' => 'shipped', 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Send order refunded notification email.
     *
     * @since 0.1.5
     */
    private function sendRefundedEmail(array $order): void
    {
        try {
            $mailService = $this->getMailService();

            if ($mailService === null) {
                return;
            }

            $mailService->sendOrderRefunded($order, [
                'amount_cents' => $order['total_cents'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail the state transition
            $this->getAuditService()->record(
                'order',
                (int) $order['id'],
                'order.email.failed',
                ['type' => 'refunded', 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Resolve the MailService, ensuring the service provider is registered.
     *
     * @return MailService|null
     *
     * @since 0.1.9
     */
    private function getMailService(): ?MailService
    {
        $container = Factory::getContainer();

        // Ensure service provider is loaded if MailService isn't registered yet
        if (!$container->has(MailService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                try {
                    $container->registerServiceProvider(require $providerPath);
                } catch (\Throwable $e) {
                    // Provider already registered or failed - continue
                }
            }
        }

        // Still not available? Try creating manually as fallback
        if (!$container->has(MailService::class)) {
            if (!$container->has(\Joomla\CMS\Mail\MailerFactoryInterface::class)) {
                return null;
            }

            try {
                $container->set(
                    MailService::class,
                    static fn ($container) => new MailService(
                        $container->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer()
                    )
                );
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return $container->get(MailService::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Update tracking metadata and append a fulfilment event.
     *
     * @since 0.1.5
     */
    public function updateTracking(int $orderId, array $tracking, ?int $actorId = null): array
    {
        $order = $this->get($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $carrier        = substr(trim((string) ($tracking['carrier'] ?? '')), 0, 50);
        $trackingNumber = substr(trim((string) ($tracking['tracking_number'] ?? '')), 0, 64);
        $trackingUrl    = substr(trim((string) ($tracking['tracking_url'] ?? '')), 0, 255);
        $markFulfilled  = !empty($tracking['mark_fulfilled']);

        $timestamp = $this->currentTimestamp();
        $events    = $this->normaliseFulfillmentEvents($order['fulfillment_events'] ?? []);
        $events[]  = [
            'type'     => 'tracking',
            'state'    => null,
            'message'  => Text::_('COM_NXPEASYCART_ORDER_TRACKING_EVENT'),
            'meta'     => array_filter([
                'carrier'         => $carrier,
                'tracking_number' => $trackingNumber,
                'tracking_url'    => $trackingUrl,
            ]),
            'at'       => $timestamp,
            'actor_id' => $actorId,
        ];

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('carrier') . ' = ' . ($carrier !== '' ? ':carrier' : 'NULL'))
            ->set($this->db->quoteName('tracking_number') . ' = ' . ($trackingNumber !== '' ? ':trackingNumber' : 'NULL'))
            ->set($this->db->quoteName('tracking_url') . ' = ' . ($trackingUrl !== '' ? ':trackingUrl' : 'NULL'))
            ->set($this->db->quoteName('fulfillment_events') . ' = :events')
            ->set($this->db->quoteName('status_updated_at') . ' = :statusUpdatedAt')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':events', $this->encodeJson($events), ParameterType::STRING)
            ->bind(':statusUpdatedAt', $timestamp, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        if ($carrier !== '') {
            $query->bind(':carrier', $carrier, ParameterType::STRING);
        }

        if ($trackingNumber !== '') {
            $query->bind(':trackingNumber', $trackingNumber, ParameterType::STRING);
        }

        if ($trackingUrl !== '') {
            $query->bind(':trackingUrl', $trackingUrl, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.tracking.updated',
            [
                'carrier'         => $carrier,
                'tracking_number' => $trackingNumber,
            ],
            $actorId
        );

        if ($markFulfilled && ($order['state'] ?? '') !== 'fulfilled') {
            return $this->transitionState($orderId, 'fulfilled', $actorId);
        }

        return $this->get($orderId) ?? $order;
    }

    /**
     * Transition multiple orders and return updated representations.
     *
     * @return array{updated: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>}
     *
     * @since 0.1.5
     */
    public function bulkTransition(array $orderIds, string $state, ?int $actorId = null): array
    {
        $unique  = array_unique(array_map('intval', $orderIds));
        $updated = [];
        $failed  = [];

        foreach ($unique as $orderId) {
            if ($orderId <= 0) {
                continue;
            }

            try {
                $updated[] = $this->transitionState($orderId, $state, $actorId);
            } catch (RuntimeException $exception) {
                $failed[] = [
                    'id'      => $orderId,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'updated' => $updated,
            'failed'  => $failed,
        ];
    }

    /**
     * Append an audit note for fulfilment context.
     *
     * @since 0.1.5
     */
    public function addNote(int $orderId, string $message, ?int $actorId = null): array
    {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOTE_REQUIRED'));
        }

        $order = $this->get($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.note',
            ['message' => $message],
            $actorId
        );

        return $this->get($orderId);
    }

    /**
     * Paginate orders optionally filtering by state or search query.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     *
     * @since 0.1.5
     */
    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit = $limit > 0 ? $limit : 20;
        $start = $start >= 0 ? $start : 0;

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('#__nxp_easycart_orders') . '.*',
                '(SELECT COUNT(*) FROM ' . $this->db->quoteName('#__nxp_easycart_order_items') . ' oi WHERE oi.order_id = ' . $this->db->quoteName('#__nxp_easycart_orders.id') . ') AS ' . $this->db->quoteName('items_count'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'));

        $state = isset($filters['state']) ? strtolower(trim((string) $filters['state'])) : '';
        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : 0;

        if ($userId > 0) {
            $query->where($this->db->quoteName('user_id') . ' = :filterUserId');
            $query->bind(':filterUserId', $userId, ParameterType::INTEGER);
        }

        if ($state !== '' && \in_array($state, self::ORDER_STATES, true)) {
            $query->where($this->db->quoteName('state') . ' = :stateFilter');
            $query->bind(':stateFilter', $state, ParameterType::STRING);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $query->where(
                '(' . $this->db->quoteName('order_no') . ' LIKE :search '
                . 'OR ' . $this->db->quoteName('email') . ' LIKE :search)'
            );
            $query->bind(':search', $searchParam, ParameterType::STRING);
        }

        $query->order($this->db->quoteName('created') . ' DESC');

        $countQuery = clone $query;
        $countQuery->clear('select')->select('COUNT(*)');
        $countQuery->clear('order');

        $this->db->setQuery($countQuery);
        $total = (int) $this->db->loadResult();

        $query->setLimit($limit, $start);
        $this->db->setQuery($query);

        $rows  = $this->db->loadObjectList() ?: [];
        $items = $this->mapOrderRows($rows);

        $pages   = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $current = $limit > 0 ? (int) floor($start / $limit) + 1 : 1;

        return [
            'items'      => $items,
            'pagination' => [
                'total'   => $total,
                'limit'   => $limit,
                'start'   => $start,
                'pages'   => max(1, $pages),
                'current' => max(1, $current),
            ],
        ];
    }

    /**
     * Export orders to CSV format for Excel compatibility.
     *
     * @param array $filters Optional filters (state, search, date_from, date_to)
     * @return array{filename: string, content: string}
     *
     * @since 0.1.5
     */
    public function exportToCsv(array $filters = []): array
    {
        $query = $this->db->getQuery(true)
            ->select('o.*')
            ->from($this->db->quoteName('#__nxp_easycart_orders', 'o'))
            ->order($this->db->quoteName('o.created') . ' DESC');

        $state = isset($filters['state']) ? strtolower(trim((string) $filters['state'])) : '';

        if ($state !== '' && \in_array($state, self::ORDER_STATES, true)) {
            $query->where($this->db->quoteName('o.state') . ' = :stateFilter');
            $query->bind(':stateFilter', $state, ParameterType::STRING);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $query->where(
                '(' . $this->db->quoteName('o.order_no') . ' LIKE :search '
                . 'OR ' . $this->db->quoteName('o.email') . ' LIKE :search)'
            );
            $query->bind(':search', $searchParam, ParameterType::STRING);
        }

        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        $dateTo   = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';

        if ($dateFrom !== '') {
            $query->where($this->db->quoteName('o.created') . ' >= :dateFrom');
            $query->bind(':dateFrom', $dateFrom, ParameterType::STRING);
        }

        if ($dateTo !== '') {
            $dateToEnd = $dateTo . ' 23:59:59';
            $query->where($this->db->quoteName('o.created') . ' <= :dateTo');
            $query->bind(':dateTo', $dateToEnd, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        // Fetch all order items in one query
        $orderIds = array_map(static fn ($row) => (int) $row->id, $rows);
        $itemsMap = !empty($orderIds) ? $this->getOrderItemsMap($orderIds) : [];

        // Build CSV with BOM for Excel UTF-8 compatibility
        $output = chr(0xEF) . chr(0xBB) . chr(0xBF);

        // CSV header row
        $headers = [
            'Order Number',
            'Date',
            'Status',
            'Customer Email',
            'Billing Name',
            'Billing Address',
            'Billing City',
            'Billing Postcode',
            'Billing Country',
            'Shipping Name',
            'Shipping Address',
            'Shipping City',
            'Shipping Postcode',
            'Shipping Country',
            'Items',
            'Subtotal',
            'Tax',
            'Shipping Cost',
            'Discount',
            'Total',
            'Currency',
            'Carrier',
            'Tracking Number',
        ];

        $output .= $this->csvLine($headers);

        foreach ($rows as $row) {
            $billing  = $this->decodeJson($row->billing ?? '{}');
            $shipping = $row->shipping !== null ? $this->decodeJson($row->shipping) : [];
            $items    = $itemsMap[(int) $row->id] ?? [];

            // Format items as "SKU x Qty, SKU x Qty"
            $itemsSummary = implode(', ', array_map(
                static fn ($item) => ($item['sku'] ?? 'N/A') . ' x ' . ($item['qty'] ?? 1),
                $items
            ));

            $line = [
                $row->order_no,
                $row->created,
                ucfirst($row->state),
                $row->email,
                trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                $billing['address'] ?? '',
                $billing['city'] ?? '',
                $billing['postcode'] ?? '',
                $billing['country'] ?? '',
                $shipping ? trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')) : '',
                $shipping['address'] ?? '',
                $shipping['city'] ?? '',
                $shipping['postcode'] ?? '',
                $shipping['country'] ?? '',
                $itemsSummary,
                number_format((int) $row->subtotal_cents / 100, 2, '.', ''),
                number_format((int) $row->tax_cents / 100, 2, '.', ''),
                number_format((int) $row->shipping_cents / 100, 2, '.', ''),
                number_format((int) $row->discount_cents / 100, 2, '.', ''),
                number_format((int) $row->total_cents / 100, 2, '.', ''),
                $row->currency,
                $row->carrier ?? '',
                $row->tracking_number ?? '',
            ];

            $output .= $this->csvLine($line);
        }

        $filename = 'orders-export-' . date('Y-m-d-His') . '.csv';

        return [
            'filename' => $filename,
            'content'  => $output,
        ];
    }

    /**
     * Encode a row as CSV line with proper escaping.
     *
     * @param array $fields
     * @return string
     *
     * @since 0.1.5
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(static function ($value) {
            $value = (string) $value;
            // Escape double quotes and wrap in quotes if contains comma, quote, or newline
            if (strpos($value, '"') !== false || strpos($value, ',') !== false || strpos($value, "\n") !== false) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }

    /**
     * Normalise common order fields and enforce base currency rules.
     *
     * @since 0.1.5
     */
    private function normaliseOrderPayload(array $payload): array
    {
        $email = (string) ($payload['email'] ?? '');
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_EMAIL_REQUIRED'));
        }

        $billing = $payload['billing'] ?? null;

        if (!\is_array($billing) || empty($billing)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_BILLING_REQUIRED'));
        }

        $billing = $this->normaliseBilling($billing);

        $shipping = $payload['shipping'] ?? null;

        if ($shipping !== null && !\is_array($shipping)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_SHIPPING_INVALID'));
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();
        $currency     = strtoupper((string) ($payload['currency'] ?? $baseCurrency));

        if ($currency !== $baseCurrency) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_ORDER_CURRENCY_MISMATCH', $baseCurrency));
        }

        $orderNo = (string) ($payload['order_no'] ?? '');
        $orderNo = trim($orderNo);

        $orderNo = $this->ensureUniqueOrderNumber($orderNo === '' ? $this->generateOrderNumber() : $orderNo);

        $state = strtolower((string) ($payload['state'] ?? 'pending'));

        if (!\in_array($state, self::ORDER_STATES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'));
        }

        $locale = (string) ($payload['locale'] ?? 'en-GB');
        $locale = $locale !== '' ? $locale : 'en-GB';

        $paymentMethod = isset($payload['payment_method']) ? trim((string) $payload['payment_method']) : null;
        $paymentMethod = $paymentMethod !== '' ? strtolower($paymentMethod) : null;

        return [
            'order_no'       => $orderNo,
            'user_id'        => $this->toNullableInt($payload['user_id'] ?? null),
            'email'          => $email,
            'billing'        => $billing,
            'shipping'       => $shipping,
            'currency'       => $currency,
            'state'          => $state,
            'payment_method' => $paymentMethod,
            'locale'         => $locale,
            'tax_cents'      => $this->toNonNegativeInt($payload['tax_cents'] ?? 0),
            'tax_rate'       => $this->formatTaxRate((string) ($payload['tax_rate'] ?? '0.00')),
            'tax_inclusive'  => isset($payload['tax_inclusive']) ? (bool) $payload['tax_inclusive'] : false,
            'shipping_cents' => $this->toNonNegativeInt($payload['shipping_cents'] ?? 0),
            'discount_cents' => $this->toNonNegativeInt($payload['discount_cents'] ?? 0),
        ];
    }

    /**
     * Trim billing fields and enforce phone requirements.
     *
     * @param array<string, mixed> $billing
     *
     * @since 0.1.5
     */
    private function normaliseBilling(array $billing): array
    {
        $cleaned = [];

        foreach ($billing as $key => $value) {
            if (\is_string($value)) {
                $cleaned[$key] = trim($value);
                continue;
            }

            $cleaned[$key] = $value;
        }

        $rawPhone = $billing['phone'] ?? '';
        $phone    = \is_scalar($rawPhone) ? (string) $rawPhone : '';
        $phone = preg_replace('/\s+/', ' ', trim($phone));
        $phone = $phone ?? '';

        if ($phone !== '' && (strlen($phone) < 6 || strlen($phone) > 20)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_PHONE_INVALID'));
        }

        if (ConfigHelper::isCheckoutPhoneRequired() && $phone === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_PHONE_REQUIRED'));
        }

        if ($phone !== '') {
            $cleaned['phone'] = $phone;
        } else {
            unset($cleaned['phone']);
        }

        return $cleaned;
    }

    /**
     * Normalise order line items.
     *
     * @param array<int, mixed> $items
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function normaliseOrderItems(array $items): array
    {
        $baseCurrency = ConfigHelper::getBaseCurrency();
        $normalised   = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEM_INVALID'));
            }

            $sku   = (string) ($item['sku'] ?? '');
            $sku   = trim($sku);
            $title = (string) ($item['title'] ?? '');
            $title = trim($title);

            if ($sku === '' || $title === '') {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEM_INVALID'));
            }

            $qty       = $this->toPositiveInt($item['qty'] ?? 1);
            $unitPrice = $this->toNonNegativeInt($item['unit_price_cents'] ?? null);
            $taxRate   = isset($item['tax_rate']) ? (string) $item['tax_rate'] : '0.00';

            $lineCurrency = strtoupper((string) ($item['currency'] ?? $baseCurrency));

            if ($lineCurrency !== $baseCurrency) {
                throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_ORDER_CURRENCY_MISMATCH', $baseCurrency));
            }

            $total = isset($item['total_cents']) ? $this->toNonNegativeInt($item['total_cents']) : $qty * $unitPrice;

            $normalised[] = [
                'product_id'       => $this->toNullableInt($item['product_id'] ?? null),
                'variant_id'       => $this->toNullableInt($item['variant_id'] ?? null),
                'sku'              => $sku,
                'title'            => $title,
                'qty'              => $qty,
                'unit_price_cents' => $unitPrice,
                'tax_rate'         => $this->formatTaxRate($taxRate),
                'total_cents'      => $total,
            ];
        }

        return $normalised;
    }

    /**
     * Compute order totals.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>             $order
     *
     * @since 0.1.5
     */
    private function computeTotals(array $items, array $order): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['unit_price_cents'] * $item['qty'];
        }

        $tax        = $order['tax_cents'];
        $shipping   = $order['shipping_cents'];
        $discount   = $order['discount_cents'];
        $inclusive  = !empty($order['tax_inclusive']);

        $total = max(0, $subtotal + ($inclusive ? 0 : $tax) + $shipping - $discount);

        return [
            'subtotal_cents' => $subtotal,
            'tax_cents'      => $tax,
            'shipping_cents' => $shipping,
            'discount_cents' => $discount,
            'total_cents'    => $total,
        ];
    }

    /**
     * Convert DB row to array representation with decoded JSON.
     *
     * @since 0.1.5
     */
    private function mapOrderRow(object $row, ?array $items = null, bool $includeHistory = false): array
    {
        $items ??= $this->getOrderItems((int) $row->id);
        $orderId = (int) $row->id;

        $transactions = $includeHistory ? $this->getTransactions($orderId) : [];
        $timeline     = $includeHistory ? $this->getAuditTrail($orderId) : [];
        $itemsCount   = $row->items_count ?? null;
        $itemsCount   = $itemsCount !== null ? (int) $itemsCount : (\is_array($items) ? \count($items) : 0);
        $statusUpdatedAt = $row->status_updated_at !== null ? (string) $row->status_updated_at : (string) $row->created;

        return [
            'id'             => $orderId,
            'order_no'       => (string) $row->order_no,
            'public_token'   => isset($row->public_token) ? (string) $row->public_token : '',
            'user_id'        => $row->user_id !== null ? (int) $row->user_id : null,
            'email'          => (string) $row->email,
            'billing'        => $this->decodeJson($row->billing ?? '{}'),
            'shipping'       => $row->shipping !== null ? $this->decodeJson($row->shipping) : null,
            'subtotal_cents' => (int) $row->subtotal_cents,
            'tax_cents'      => (int) $row->tax_cents,
            'tax_rate'       => isset($row->tax_rate) ? (string) $row->tax_rate : '0.00',
            'tax_inclusive'  => (bool) ($row->tax_inclusive ?? 0),
            'shipping_cents' => (int) $row->shipping_cents,
            'discount_cents' => (int) $row->discount_cents,
            'total_cents'    => (int) $row->total_cents,
            'currency'       => (string) $row->currency,
            'state'          => (string) $row->state,
            'payment_method' => isset($row->payment_method) ? (string) $row->payment_method : null,
            'needs_review'   => (bool) ($row->needs_review ?? 0),
            'review_reason'  => isset($row->review_reason) ? (string) $row->review_reason : null,
            'status_updated_at' => $statusUpdatedAt,
            'locale'         => (string) $row->locale,
            'carrier'        => $row->carrier !== null ? (string) $row->carrier : null,
            'tracking_number'=> $row->tracking_number !== null ? (string) $row->tracking_number : null,
            'tracking_url'   => $row->tracking_url !== null ? (string) $row->tracking_url : null,
            'created'        => (string) $row->created,
            'modified'       => $row->modified !== null ? (string) $row->modified : null,
            'fulfillment_events' => $this->normaliseFulfillmentEvents($row->fulfillment_events ?? null),
            'items_count'    => $itemsCount,
            'items'          => $items,
            'transactions'   => $transactions,
            'timeline'       => $timeline,
        ];
    }

    /**
     * Map a list of DB rows to array payloads efficiently.
     *
     * @param array<int, object> $rows
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function mapOrderRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $orderIds = array_map(static fn ($row) => (int) $row->id, $rows);
        $itemsMap = $this->getOrderItemsMap($orderIds);

        $orders = [];

        foreach ($rows as $row) {
            $orders[] = $this->mapOrderRow($row, $itemsMap[(int) $row->id] ?? []);
        }

        return $orders;
    }

    /**
     * Retrieve order items for a specific order.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function getOrderItems(int $orderId): array
    {
        $map = $this->getOrderItemsMap([$orderId]);

        return $map[$orderId] ?? [];
    }

    /**
     * Retrieve order items for multiple orders.
     *
     * @param array<int, int> $orderIds
     * @return array<int, array<int, array<string, mixed>>>
     *
     * @since 0.1.5
     */
    private function getOrderItemsMap(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter($orderIds)));

        if (empty($orderIds)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->whereIn($this->db->quoteName('order_id'), $orderIds)
            ->order($this->db->quoteName('order_id') . ' ASC, ' . $this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList() ?: [];
        $map  = [];

        if (empty($rows)) {
            return $map;
        }

        $productIds = [];
        $variantIds = [];

        foreach ($rows as $row) {
            if ($row->product_id !== null) {
                $productIds[] = (int) $row->product_id;
            }

            if ($row->variant_id !== null) {
                $variantIds[] = (int) $row->variant_id;
            }
        }

        $products = $productIds ? $this->fetchProducts($productIds) : [];
        $variants = $variantIds ? $this->fetchVariants($variantIds) : [];

        foreach ($rows as $row) {
            $orderId = (int) $row->order_id;
            $sku     = (string) $row->sku;

            $product = $row->product_id !== null && isset($products[(int) $row->product_id])
                ? $products[(int) $row->product_id]
                : null;
            $variant = $row->variant_id !== null && isset($variants[(int) $row->variant_id])
                ? $variants[(int) $row->variant_id]
                : null;

            $productTitle = $product['title'] ?? '';
            $variantLabel = $this->buildVariantLabel($variant);
            $storedTitle  = trim((string) $row->title);

            $displayTitle = $storedTitle !== '' ? $storedTitle : $sku;

            if ($productTitle !== '' && ($storedTitle === '' || $storedTitle === $sku)) {
                $displayTitle = $variantLabel !== ''
                    ? $productTitle . ' (' . $variantLabel . ')'
                    : $productTitle;
            } elseif ($productTitle !== '' && $variantLabel !== '' && stripos($displayTitle, $productTitle) === false) {
                $displayTitle = $productTitle . ' (' . $variantLabel . ')';
            }

            $image = $variant['image'] ?? ($product['image'] ?? null);

            if (!isset($map[$orderId])) {
                $map[$orderId] = [];
            }

            $map[$orderId][] = [
                'id'               => (int) $row->id,
                'order_id'         => $orderId,
                'product_id'       => $row->product_id !== null ? (int) $row->product_id : null,
                'variant_id'       => $row->variant_id !== null ? (int) $row->variant_id : null,
                'sku'              => $sku,
                'title'            => $displayTitle,
                'product_title'    => $productTitle !== '' ? $productTitle : $displayTitle,
                'variant_label'    => $variantLabel !== '' ? $variantLabel : null,
                'qty'              => (int) $row->qty,
                'unit_price_cents' => (int) $row->unit_price_cents,
                'tax_rate'         => (string) $row->tax_rate,
                'total_cents'      => (int) $row->total_cents,
                'image'            => $image,
            ];
        }

        return $map;
    }

    /**
     * Retrieve gateway transactions for the order.
     *
     * @since 0.1.5
     */
    private function getTransactions(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_transactions'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList() ?: [];

        return array_map(function ($row) {
            $payload = [];

            if (!empty($row->payload)) {
                $decoded = json_decode($row->payload, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            return [
                'id'              => (int) $row->id,
                'gateway'         => (string) $row->gateway,
                'external_id'     => $row->ext_id !== null ? (string) $row->ext_id : null,
                'status'          => (string) $row->status,
                'amount_cents'    => (int) $row->amount_cents,
                'payload'         => $payload,
                'idempotency_key' => $row->event_idempotency_key !== null ? (string) $row->event_idempotency_key : null,
                'created'         => (string) $row->created,
            ];
        }, $rows);
    }

    /**
     * Retrieve audit trail entries for the order.
     *
     * @since 0.1.5
     */
    private function getAuditTrail(int $orderId): array
    {
        return $this->getAuditService()->forOrder($orderId);
    }

    /**
     * Fetch product metadata for order item presentation.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function fetchProducts(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([$this->db->quoteName('id'), $this->db->quoteName('title'), $this->db->quoteName('images')])
            ->from($this->db->quoteName('#__nxp_easycart_products'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $this->db->setQuery($query);

        $rows     = $this->db->loadObjectList() ?: [];
        $products = [];

        foreach ($rows as $row) {
            $products[(int) $row->id] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'image' => $this->resolvePrimaryImage($row->images ?? null),
            ];
        }

        return $products;
    }

    /**
     * Fetch variant metadata for order items.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function fetchVariants(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_variants'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $this->db->setQuery($query);

        $rows     = $this->db->loadObjectList() ?: [];
        $variants = [];

        foreach ($rows as $row) {
            $options = [];

            if (!empty($row->options)) {
                $decoded = json_decode($row->options, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                    $options = array_filter($decoded, static fn ($option) => \is_array($option));
                }
            }

            $variants[(int) $row->id] = [
                'id'         => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'title'      => (string) $row->sku,
                'sku'        => (string) $row->sku,
                'options'    => $options,
                'image'      => null,
            ];
        }

        return $variants;
    }

    /**
     * Build a variant label for display (options preferred, falls back to SKU).
     *
     * @since 0.1.5
     */
    private function buildVariantLabel(?array $variant): string
    {
        if (!$variant) {
            return '';
        }

        $labels = [];

        if (!empty($variant['options']) && \is_array($variant['options'])) {
            foreach ($variant['options'] as $option) {
                if (!\is_array($option)) {
                    continue;
                }

                $name  = isset($option['name']) ? trim((string) $option['name']) : '';
                $value = isset($option['value']) ? trim((string) $option['value']) : '';

                if ($name !== '' && $value !== '') {
                    $labels[] = $name . ': ' . $value;
                } elseif ($value !== '') {
                    $labels[] = $value;
                }
            }
        }

        if (!$labels && !empty($variant['sku'])) {
            return (string) $variant['sku'];
        }

        return implode(', ', $labels);
    }

    /**
     * Resolve the primary product image from JSON, normalising to an absolute URL.
     *
     * @since 0.1.5
     */
    private function resolvePrimaryImage($imagesJson): ?string
    {
        if (empty($imagesJson)) {
            return null;
        }

        $decoded = json_decode((string) $imagesJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded) || !isset($decoded[0])) {
            return null;
        }

        $candidate = $decoded[0];

        if (!\is_string($candidate)) {
            return null;
        }

        $image = trim($candidate);

        if ($image === '') {
            return null;
        }

        if (
            str_starts_with($image, 'http://')
            || str_starts_with($image, 'https://')
            || str_starts_with($image, '//')
        ) {
            return $image;
        }

        $base     = rtrim(Uri::root(true), '/');
        $relative = '/' . ltrim($image, '/');

        return ($base === '' ? '' : $base) . $relative;
    }

    /**
     * Record or update a payment transaction for an order.
     *
     * @param array<string, mixed> $transaction
     *
     * @since 0.1.5
     */
    public function recordTransaction(int $orderId, array $transaction): array
    {
        $order = $this->get($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $gateway = (string) ($transaction['gateway'] ?? '');

        if ($gateway === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_TRANSACTION_GATEWAY_REQUIRED'));
        }

        $idempotencyKey = isset($transaction['idempotency_key']) ? (string) $transaction['idempotency_key'] : '';

        if ($idempotencyKey !== '' && $this->transactionExistsByIdempotency($gateway, $idempotencyKey)) {
            return $this->get($orderId);
        }

        $externalId = isset($transaction['external_id']) ? (string) $transaction['external_id'] : null;

        if ($externalId !== null && $this->transactionExistsByExternalId($gateway, $externalId)) {
            return $this->get($orderId);
        }

        $transactionAmount   = (int) ($transaction['amount_cents'] ?? 0);
        $transactionCurrency = strtoupper((string) ($transaction['currency'] ?? ''));
        $orderCurrency       = strtoupper((string) ($order['currency'] ?? ''));
        $expectedAmount      = (int) ($order['total_cents'] ?? 0);

        $object = (object) [
            'order_id'     => $orderId,
            'gateway'      => $gateway,
            'ext_id'       => $externalId,
            'status'       => (string) ($transaction['status'] ?? 'pending'),
            'amount_cents' => (int) ($transaction['amount_cents'] ?? 0),
            'payload'      => !empty($transaction['payload'])
                ? json_encode($transaction['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'event_idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ];

        $statusNormalised = strtolower((string) $object->status);
        $shouldMarkPaid   = $statusNormalised === 'paid';
        $shouldCancel     = \in_array($statusNormalised, ['failed', 'canceled', 'cancelled', 'expired', 'denied'], true);

        if ($shouldMarkPaid) {
            $hasMismatch = false;

            if ($transactionCurrency !== '' && $orderCurrency !== '' && $transactionCurrency !== $orderCurrency) {
                $hasMismatch = true;
            }

            if ($transactionAmount > 0 && $expectedAmount > 0 && $transactionAmount !== $expectedAmount) {
                $hasMismatch = true;
            }

            if ($hasMismatch) {
                $object->status   = 'mismatch';
                $statusNormalised = 'mismatch';
                $shouldMarkPaid   = false;

                $this->getAuditService()->record(
                    'order',
                    $orderId,
                    'order.payment.mismatch',
                    [
                        'gateway'             => $gateway,
                        'order_currency'      => $orderCurrency,
                        'transaction_currency'=> $transactionCurrency,
                        'expected_cents'      => $expectedAmount,
                        'received_cents'      => $transactionAmount,
                    ]
                );
            }
        }

        $this->db->insertObject('#__nxp_easycart_transactions', $object);

        if ($shouldMarkPaid && $order['state'] !== 'paid' && $order['state'] !== 'fulfilled') {
            $this->transitionState($orderId, 'paid');
            // Inventory already reserved on creation; avoid double decrement.
        }

        if (
            $shouldCancel
            && !\in_array($order['state'], ['canceled', 'refunded', 'fulfilled', 'paid'], true)
        ) {
            $this->transitionState($orderId, 'canceled');
        }

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.payment.recorded',
            [
                'gateway'      => $gateway,
                'status'       => $object->status,
                'amount_cents' => (int) $object->amount_cents,
            ]
        );

        return $this->get($orderId) ?? $order;
    }

    private function transactionExistsByExternalId(string $gateway, string $externalId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_transactions'))
            ->where($this->db->quoteName('gateway') . ' = :gateway')
            ->where($this->db->quoteName('ext_id') . ' = :extId')
            ->setLimit(1)
            ->bind(':gateway', $gateway, ParameterType::STRING)
            ->bind(':extId', $externalId, ParameterType::STRING);

        $this->db->setQuery($query);

        return (bool) $this->db->loadResult();
    }

    private function transactionExistsByIdempotency(string $gateway, string $idempotencyKey): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_transactions'))
            ->where($this->db->quoteName('gateway') . ' = :gateway')
            ->where($this->db->quoteName('event_idempotency_key') . ' = :key')
            ->setLimit(1)
            ->bind(':gateway', $gateway, ParameterType::STRING)
            ->bind(':key', $idempotencyKey, ParameterType::STRING);

        $this->db->setQuery($query);

        return (bool) $this->db->loadResult();
    }

    private function decrementInventory(int $orderId): void
    {
        $affectedProducts = [];

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('variant_id'),
                $this->db->quoteName('product_id'),
                $this->db->quoteName('qty'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->where($this->db->quoteName('variant_id') . ' IS NOT NULL')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $variantId = (int) $row->variant_id;
            $qty       = max(0, (int) $row->qty);

            if ($variantId <= 0 || $qty <= 0) {
                continue;
            }

            $affectedProducts[] = (int) ($row->product_id ?? 0);

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = GREATEST(' . $this->db->quoteName('stock') . ' - :qty, 0)')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();
        }

        $this->autoDisableDepletedProducts(array_unique(array_filter($affectedProducts)));
    }

    /**
     * Disable products that have no remaining stock across active variants.
     *
     * @param array<int> $productIds
     *
     * @since 0.1.5
     */
    private function autoDisableDepletedProducts(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        foreach ($productIds as $productId) {
            if ($productId <= 0) {
                continue;
            }

            $stockQuery = $this->db->getQuery(true)
                ->select('SUM(' . $this->db->quoteName('stock') . ')')
                ->from($this->db->quoteName('#__nxp_easycart_variants'))
                ->where($this->db->quoteName('product_id') . ' = :productId')
                ->where($this->db->quoteName('active') . ' = 1')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $this->db->setQuery($stockQuery);

            $remaining = (int) $this->db->loadResult();

            if ($remaining > 0) {
                continue;
            }

            $disable = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_products'))
                ->set($this->db->quoteName('active') . ' = :outOfStock')
                ->where($this->db->quoteName('id') . ' = :productId')
                ->where($this->db->quoteName('active') . ' = :activeStatus')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $outOfStock = ProductStatus::OUT_OF_STOCK;
            $activeStatus = ProductStatus::ACTIVE;

            $disable->bind(':outOfStock', $outOfStock, ParameterType::INTEGER);
            $disable->bind(':activeStatus', $activeStatus, ParameterType::INTEGER);

            $this->db->setQuery($disable);
            $this->db->execute();
        }
    }

    /**
     * Lock variants, validate availability, decrement stock, and auto-disable depleted products.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @since 0.1.5
     */
    private function reserveStockForItems(array $items): void
    {
        $variantTotals = [];
        $productIds    = [];

        foreach ($items as $item) {
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty       = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;

            if ($variantId > 0) {
                $variantTotals[$variantId] = ($variantTotals[$variantId] ?? 0) + $qty;
            }

            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }

        if (empty($variantTotals)) {
            return;
        }

        // Atomic decrements with guard to prevent oversell (works across drivers).
        foreach ($variantTotals as $variantId => $requestedQty) {
            $qty        = (int) $requestedQty;
            $variantKey = (int) $variantId;

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = ' . $this->db->quoteName('stock') . ' - :qty')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->where($this->db->quoteName('active') . ' = 1')
                ->where($this->db->quoteName('stock') . ' >= :qty')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantKey, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();

            if ((int) $this->db->getAffectedRows() === 0) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'));
            }
        }

        $this->autoDisableDepletedProducts(array_unique($productIds));
    }

    /**
     * Generate a unique order number candidate.
     *
     * @since 0.1.5
     */
    private function generateOrderNumber(): string
    {
        $uuid = str_replace('-', '', Uuid::uuid4()->toString());

        return sprintf('EC-%s', strtoupper(substr($uuid, 0, 12)));
    }

    /**
     * Generate and ensure uniqueness of a public tracking token.
     *
     * @since 0.1.5
     */
    private function ensureUniquePublicToken(): string
    {
        $attempts = 0;
        $token    = $this->generatePublicToken();

        while ($attempts < 5 && $this->publicTokenExists($token)) {
            $token = $this->generatePublicToken();
            $attempts++;
        }

        if ($this->publicTokenExists($token)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_TOKEN_UNAVAILABLE'));
        }

        return $token;
    }

    /**
     * Ensure an order number is unique in the database.
     *
     * @since 0.1.5
     */
    private function ensureUniqueOrderNumber(string $candidate): string
    {
        $attempts = 0;

        while ($attempts < 5) {
            if (!$this->orderNumberExists($candidate)) {
                return $candidate;
            }

            $candidate = $this->generateOrderNumber();
            $attempts++;
        }

        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NUMBER_UNAVAILABLE'));
    }

    /**
     * Check if the provided order number already exists.
     *
     * @since 0.1.5
     */
    private function orderNumberExists(string $orderNo): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('order_no') . ' = :orderNo')
            ->bind(':orderNo', $orderNo, ParameterType::STRING);

        $this->db->setQuery($query, 0, 1);

        return (bool) $this->db->loadResult();
    }

    /**
     * Check if the provided public token already exists.
     *
     * @since 0.1.5
     */
    private function publicTokenExists(string $token): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('public_token') . ' = :token')
            ->bind(':token', $token, ParameterType::STRING);

        $this->db->setQuery($query, 0, 1);

        return (bool) $this->db->loadResult();
    }

    /**
     * Encode PHP structures as JSON.
     *
     * @since 0.1.5
     */
    private function encodeJson($data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_SERIALISE_FAILED'), 0, $exception);
        }
    }

    /**
     * Decode JSON to an array.
     *
     * @since 0.1.5
     */
    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Convert a value to a nullable integer.
     *
     * @since 0.1.5
     */
    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Cast value to a non-negative integer.
     *
     * @since 0.1.5
     */
    private function toNonNegativeInt($value): int
    {
        $int = (int) ($value ?? 0);

        return $int >= 0 ? $int : 0;
    }

    /**
     * Cast value to a positive integer (minimum 1).
     *
     * @since 0.1.5
     */
    private function toPositiveInt($value): int
    {
        $int = (int) ($value ?? 1);

        return $int > 0 ? $int : 1;
    }

    /**
     * Format the tax rate storage string.
     *
     * @since 0.1.5
     */
    private function formatTaxRate(string $rate): string
    {
        $rate = trim($rate);

        if ($rate === '') {
            return '0.00';
        }

        return sprintf('%.2f', (float) $rate);
    }

    /**
     * Generate a stable current timestamp string.
     *
     * @since 0.1.5
     */
    private function currentTimestamp(): string
    {
        return Factory::getDate()->toSql();
    }

    /**
     * Generate a random public token for guest order tracking.
     *
     * @since 0.1.5
     */
    private function generatePublicToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Exception $exception) {
            // Fallback for environments without cryptographically secure randomness.
            return hash('sha256', microtime(true) . (string) mt_rand());
        }
    }

    /**
     * Build a fulfilment event payload for storage.
     *
     * @since 0.1.5
     */
    private function buildStatusEvent(string $state, string $timestamp, ?int $actorId = null, ?string $fromState = null): array
    {
        $state = strtolower(trim($state));
        $meta  = [];

        if ($fromState !== null && $fromState !== '') {
            $meta = [
                'from' => $fromState,
                'to'   => $state,
            ];
        }

        return [
            'type'     => 'status',
            'state'    => $state !== '' ? $state : null,
            'message'  => '',
            'meta'     => $meta,
            'at'       => $timestamp,
            'actor_id' => $actorId,
        ];
    }

    /**
     * Normalise fulfilment events payload to a consistent array structure.
     *
     * @param mixed $events
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function normaliseFulfillmentEvents($events): array
    {
        if (\is_string($events) && $events !== '') {
            $decoded = json_decode($events, true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                $events = $decoded;
            }
        }

        if (!\is_array($events)) {
            return [];
        }

        $normalised = [];

        foreach ($events as $event) {
            if (!\is_array($event)) {
                continue;
            }

            $timestamp = isset($event['at']) ? trim((string) $event['at']) : '';
            $state     = isset($event['state']) ? strtolower((string) $event['state']) : null;

            $normalised[] = [
                'type'     => isset($event['type']) ? (string) $event['type'] : 'status',
                'state'    => $state !== '' ? $state : null,
                'message'  => isset($event['message']) ? trim((string) $event['message']) : '',
                'meta'     => isset($event['meta']) && \is_array($event['meta']) ? $event['meta'] : [],
                'at'       => $timestamp !== '' ? $timestamp : $this->currentTimestamp(),
                'actor_id' => $this->toNullableInt($event['actor_id'] ?? null),
            ];
        }

        return $normalised;
    }

    /**
     * Check if a state transition is valid according to the state machine.
     *
     * @param string $from Current state
     * @param string $to   Target state
     *
     * @return bool True if transition is allowed
     *
     * @since 0.1.9
     */
    private function isValidTransition(string $from, string $to): bool
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];

        return \in_array($to, $allowed, true);
    }

    /**
     * Flag an order for manual review with a reason.
     *
     * @param int         $orderId Order ID
     * @param string      $reason  Reason for flagging (e.g., 'payment_amount_mismatch')
     * @param array       $context Additional context data for audit log
     * @param int|null    $actorId Actor performing the action
     *
     * @return array Updated order record
     *
     * @since 0.1.9
     */
    public function flagForReview(int $orderId, string $reason, array $context = [], ?int $actorId = null): array
    {
        $order = $this->get($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $reason = substr(trim($reason), 0, 255);
        $needsReview = 1;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('needs_review') . ' = :needsReview')
            ->set($this->db->quoteName('review_reason') . ' = :reason')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':needsReview', $needsReview, ParameterType::INTEGER)
            ->bind(':reason', $reason, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        // Record in audit trail
        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.flagged_for_review',
            array_merge(['reason' => $reason], $context),
            $actorId
        );

        return $this->get($orderId);
    }

    /**
     * Clear the review flag on an order.
     *
     * @param int      $orderId Order ID
     * @param int|null $actorId Actor performing the action
     *
     * @return array Updated order record
     *
     * @since 0.1.9
     */
    public function clearReviewFlag(int $orderId, ?int $actorId = null): array
    {
        $order = $this->get($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $needsReview = 0;

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('needs_review') . ' = :needsReview')
            ->set($this->db->quoteName('review_reason') . ' = NULL')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':needsReview', $needsReview, ParameterType::INTEGER)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        // Record in audit trail
        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.review_cleared',
            [],
            $actorId
        );

        return $this->get($orderId);
    }

    /**
     * Cancel stale pending orders older than the specified hours.
     * Used by the scheduled task plugin.
     *
     * @param int      $hoursOld Number of hours before order is considered stale
     * @param int|null $actorId  Actor performing the action (null for system/cron)
     *
     * @return array List of canceled order IDs
     *
     * @since 0.1.9
     */
    public function cancelStaleOrders(int $hoursOld, ?int $actorId = null): array
    {
        $hoursOld = max(1, $hoursOld);
        $cutoff   = (new \DateTime())->modify("-{$hoursOld} hours")->format('Y-m-d H:i:s');

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('state') . ' = ' . $this->db->quote('pending'))
            ->where($this->db->quoteName('created') . ' < ' . $this->db->quote($cutoff));

        $this->db->setQuery($query);
        $staleIds = $this->db->loadColumn();

        $canceled = [];

        foreach ($staleIds as $orderId) {
            try {
                $this->transitionState((int) $orderId, 'canceled', $actorId);
                $this->releaseStockForOrder((int) $orderId);

                $this->getAuditService()->record(
                    'order',
                    (int) $orderId,
                    'order.stale_canceled',
                    ['hours_threshold' => $hoursOld, 'cutoff' => $cutoff]
                );

                $canceled[] = (int) $orderId;
            } catch (Throwable $e) {
                // Log but continue processing other orders
                $this->getAuditService()->record(
                    'order',
                    (int) $orderId,
                    'order.stale_cancel_failed',
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $canceled;
    }

    /**
     * Release reserved stock for a canceled/stale order.
     *
     * @param int $orderId Order ID
     *
     * @since 0.1.9
     */
    private function releaseStockForOrder(int $orderId): void
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('variant_id'),
                $this->db->quoteName('qty'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $items = $this->db->loadObjectList();

        foreach ($items as $item) {
            if ((int) $item->variant_id <= 0) {
                continue;
            }

            // Restore stock to variant
            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = ' . $this->db->quoteName('stock') . ' + :qty')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->bind(':qty', $item->qty, ParameterType::INTEGER)
                ->bind(':variantId', $item->variant_id, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();
        }
    }
}
