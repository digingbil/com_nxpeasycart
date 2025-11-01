<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use JsonException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;
use Nxp\EasyCart\Admin\Administrator\Service\AuditService;
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
        $this->db = $db;
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
        $items = $this->normaliseOrderItems($payload['items'] ?? []);

        if (empty($items)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEMS_REQUIRED'));
        }

        $totals = $this->computeTotals($items, $normalised);

        $this->db->transactionStart();

        try {
            $order = (object) [
                'order_no' => $normalised['order_no'],
                'user_id' => $normalised['user_id'],
                'email' => $normalised['email'],
                'billing' => $this->encodeJson($normalised['billing']),
                'shipping' => $normalised['shipping'] === null ? null : $this->encodeJson($normalised['shipping']),
                'subtotal_cents' => $totals['subtotal_cents'],
                'tax_cents' => $totals['tax_cents'],
                'shipping_cents' => $totals['shipping_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents' => $totals['total_cents'],
                'currency' => $normalised['currency'],
                'state' => $normalised['state'],
                'locale' => $normalised['locale'],
            ];

            $this->db->insertObject('#__nxp_easycart_orders', $order);

            $orderId = (int) $this->db->insertid();

            foreach ($items as $item) {
                $itemObject = (object) [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'sku' => $item['sku'],
                    'title' => $item['title'],
                    'qty' => $item['qty'],
                    'unit_price_cents' => $item['unit_price_cents'],
                    'tax_rate' => $item['tax_rate'],
                    'total_cents' => $item['total_cents'],
                ];

                $this->db->insertObject('#__nxp_easycart_order_items', $itemObject);
            }

            $this->db->transactionCommit();
        } catch (Throwable $exception) {
            $this->db->transactionRollback();
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_CREATE_FAILED'), 0, $exception);
        }

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.created',
            [
                'order_no' => $normalised['order_no'],
                'state' => $normalised['state'],
                'total_cents' => $totals['total_cents'],
            ],
            $actorId
        );

        $order = $this->getByNumber($normalised['order_no']);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

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

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':state', $state, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.state.transitioned',
            [
                'from' => $current['state'],
                'to' => $state,
            ],
            $actorId
        );

        $updated = $this->get($orderId);

        if (!$updated) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        return $updated;
    }

    /**
     * Transition multiple orders and return updated representations.
     *
     * @return array{updated: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>}
     */
    public function bulkTransition(array $orderIds, string $state, ?int $actorId = null): array
    {
        $unique = array_unique(array_map('intval', $orderIds));
        $updated = [];
        $failed = [];

        foreach ($unique as $orderId) {
            if ($orderId <= 0) {
                continue;
            }

            try {
                $updated[] = $this->transitionState($orderId, $state, $actorId);
            } catch (RuntimeException $exception) {
                $failed[] = [
                    'id' => $orderId,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
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
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_orders'));

        $state = isset($filters['state']) ? strtolower(trim((string) $filters['state'])) : '';

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

        $rows = $this->db->loadObjectList() ?: [];
        $items = $this->mapOrderRows($rows);

        $pages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $current = $limit > 0 ? (int) floor($start / $limit) + 1 : 1;

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'start' => $start,
                'pages' => max(1, $pages),
                'current' => max(1, $current),
            ],
        ];
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

        $shipping = $payload['shipping'] ?? null;

        if ($shipping !== null && !\is_array($shipping)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_SHIPPING_INVALID'));
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();
        $currency = strtoupper((string) ($payload['currency'] ?? $baseCurrency));

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
            'order_no' => $orderNo,
            'user_id' => $this->toNullableInt($payload['user_id'] ?? null),
            'email' => $email,
            'billing' => $billing,
            'shipping' => $shipping,
            'currency' => $currency,
            'state' => $state,
            'locale' => $locale,
            'tax_cents' => $this->toNonNegativeInt($payload['tax_cents'] ?? 0),
            'shipping_cents' => $this->toNonNegativeInt($payload['shipping_cents'] ?? 0),
            'discount_cents' => $this->toNonNegativeInt($payload['discount_cents'] ?? 0),
        ];
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
        $normalised = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEM_INVALID'));
            }

            $sku = (string) ($item['sku'] ?? '');
            $sku = trim($sku);
            $title = (string) ($item['title'] ?? '');
            $title = trim($title);

            if ($sku === '' || $title === '') {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEM_INVALID'));
            }

            $qty = $this->toPositiveInt($item['qty'] ?? 1);
            $unitPrice = $this->toNonNegativeInt($item['unit_price_cents'] ?? null);
            $taxRate = isset($item['tax_rate']) ? (string) $item['tax_rate'] : '0.00';

            $lineCurrency = strtoupper((string) ($item['currency'] ?? $baseCurrency));

            if ($lineCurrency !== $baseCurrency) {
                throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_ORDER_CURRENCY_MISMATCH', $baseCurrency));
            }

            $total = isset($item['total_cents']) ? $this->toNonNegativeInt($item['total_cents']) : $qty * $unitPrice;

            $normalised[] = [
                'product_id' => $this->toNullableInt($item['product_id'] ?? null),
                'variant_id' => $this->toNullableInt($item['variant_id'] ?? null),
                'sku' => $sku,
                'title' => $title,
                'qty' => $qty,
                'unit_price_cents' => $unitPrice,
                'tax_rate' => $this->formatTaxRate($taxRate),
                'total_cents' => $total,
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

        $tax = $order['tax_cents'];
        $shipping = $order['shipping_cents'];
        $discount = $order['discount_cents'];

        $total = max(0, $subtotal + $tax + $shipping - $discount);

        return [
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'shipping_cents' => $shipping,
            'discount_cents' => $discount,
            'total_cents' => $total,
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
        $timeline = $includeHistory ? $this->getAuditTrail($orderId) : [];

        return [
            'id' => $orderId,
            'order_no' => (string) $row->order_no,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'email' => (string) $row->email,
            'billing' => $this->decodeJson($row->billing ?? '{}'),
            'shipping' => $row->shipping !== null ? $this->decodeJson($row->shipping) : null,
            'subtotal_cents' => (int) $row->subtotal_cents,
            'tax_cents' => (int) $row->tax_cents,
            'shipping_cents' => (int) $row->shipping_cents,
            'discount_cents' => (int) $row->discount_cents,
            'total_cents' => (int) $row->total_cents,
            'currency' => (string) $row->currency,
            'state' => (string) $row->state,
            'locale' => (string) $row->locale,
            'created' => (string) $row->created,
            'modified' => $row->modified !== null ? (string) $row->modified : null,
            'items' => $items,
            'transactions' => $transactions,
            'timeline' => $timeline,
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
            $placeholder = ':orderId' . $index;
            $placeholders[] = $placeholder;
            $boundId = (int) $orderId;
            $query->bind($placeholder, $boundId, ParameterType::INTEGER);
        }

        $query->where($this->db->quoteName('order_id') . ' IN (' . implode(',', $placeholders) . ')');

        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList() ?: [];
        $map = [];

        foreach ($rows as $row) {
            $orderId = (int) $row->order_id;

            if (!isset($map[$orderId])) {
                $map[$orderId] = [];
            }

            $map[$orderId][] = [
                'id' => (int) $row->id,
                'order_id' => $orderId,
                'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
                'variant_id' => $row->variant_id !== null ? (int) $row->variant_id : null,
                'sku' => (string) $row->sku,
                'title' => (string) $row->title,
                'qty' => (int) $row->qty,
                'unit_price_cents' => (int) $row->unit_price_cents,
                'tax_rate' => (string) $row->tax_rate,
                'total_cents' => (int) $row->total_cents,
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
                'id' => (int) $row->id,
                'gateway' => (string) $row->gateway,
                'external_id' => $row->ext_id !== null ? (string) $row->ext_id : null,
                'status' => (string) $row->status,
                'amount_cents' => (int) $row->amount_cents,
                'payload' => $payload,
                'idempotency_key' => $row->event_idempotency_key !== null ? (string) $row->event_idempotency_key : null,
                'created' => (string) $row->created,
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
            'order_id' => $orderId,
            'gateway' => $gateway,
            'ext_id' => $externalId,
            'status' => (string) ($transaction['status'] ?? 'pending'),
            'amount_cents' => (int) ($transaction['amount_cents'] ?? 0),
            'payload' => !empty($transaction['payload'])
                ? json_encode($transaction['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'event_idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ];

        $this->db->insertObject('#__nxp_easycart_transactions', $object);

        $shouldMarkPaid = strtolower((string) ($object->status ?? '')) === 'paid';

        if ($shouldMarkPaid && $order['state'] !== 'paid' && $order['state'] !== 'fulfilled') {
            $this->transitionState($orderId, 'paid');
            $this->decrementInventory($orderId);
        }

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.payment.recorded',
            [
                'gateway' => $gateway,
                'status' => $object->status,
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
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('variant_id'),
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
            $qty = max(0, (int) $row->qty);

            if ($variantId <= 0 || $qty <= 0) {
                continue;
            }

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = GREATEST(' . $this->db->quoteName('stock') . ' - :qty, 0)')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();
        }
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

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_DESERIALISE_FAILED'), 0, $exception);
        }

        return (array) $decoded;
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
}
