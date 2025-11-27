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
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Order persistence and aggregation service.
 */
class OrderService
{
    private const ORDER_STATES = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

    /**
     * @var DatabaseInterface
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
                'shipping_cents' => $totals['shipping_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents'    => $totals['total_cents'],
                'currency'       => $normalised['currency'],
                'state'          => $normalised['state'],
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

        return $updated;
    }

    /**
     * Update tracking metadata and append a fulfilment event.
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

        return [
            'order_no'       => $orderNo,
            'user_id'        => $this->toNullableInt($payload['user_id'] ?? null),
            'email'          => $email,
            'billing'        => $billing,
            'shipping'       => $shipping,
            'currency'       => $currency,
            'state'          => $state,
            'locale'         => $locale,
            'tax_cents'      => $this->toNonNegativeInt($payload['tax_cents'] ?? 0),
            'shipping_cents' => $this->toNonNegativeInt($payload['shipping_cents'] ?? 0),
            'discount_cents' => $this->toNonNegativeInt($payload['discount_cents'] ?? 0),
            'tax_inclusive'  => isset($payload['tax_inclusive']) ? (bool) $payload['tax_inclusive'] : false,
        ];
    }

    /**
     * Trim billing fields and enforce phone requirements.
     *
     * @param array<string, mixed> $billing
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
            'shipping_cents' => (int) $row->shipping_cents,
            'discount_cents' => (int) $row->discount_cents,
            'total_cents'    => (int) $row->total_cents,
            'currency'       => (string) $row->currency,
            'state'          => (string) $row->state,
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
            ->order($this->db->quoteName('order_id') . ' ASC, ' . $this->db->quoteName('id') . ' ASC');

        $placeholders = [];

        foreach ($orderIds as $index => $orderId) {
            $placeholder    = ':orderId' . $index;
            $placeholders[] = $placeholder;
            $boundId        = (int) $orderId;
            $query->bind($placeholder, $boundId, ParameterType::INTEGER);
        }

        $query->where($this->db->quoteName('order_id') . ' IN (' . implode(',', $placeholders) . ')');

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

        $this->db->insertObject('#__nxp_easycart_transactions', $object);

        $shouldMarkPaid = strtolower((string) ($object->status ?? '')) === 'paid';

        if ($shouldMarkPaid && $order['state'] !== 'paid' && $order['state'] !== 'fulfilled') {
            $this->transitionState($orderId, 'paid');
            // Inventory already reserved on creation; avoid double decrement.
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
     */
    private function generateOrderNumber(): string
    {
        $uuid = str_replace('-', '', Uuid::uuid4()->toString());

        return sprintf('EC-%s', strtoupper(substr($uuid, 0, 12)));
    }

    /**
     * Generate and ensure uniqueness of a public tracking token.
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
     */
    private function toNonNegativeInt($value): int
    {
        $int = (int) ($value ?? 0);

        return $int >= 0 ? $int : 0;
    }

    /**
     * Cast value to a positive integer (minimum 1).
     */
    private function toPositiveInt($value): int
    {
        $int = (int) ($value ?? 1);

        return $int > 0 ? $int : 1;
    }

    /**
     * Format the tax rate storage string.
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
     */
    private function currentTimestamp(): string
    {
        return Factory::getDate()->toSql();
    }

    /**
     * Generate a random public token for guest order tracking.
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
}
