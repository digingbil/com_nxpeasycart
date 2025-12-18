<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

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
use Joomla\Component\Nxpeasycart\Administrator\Service\DigitalFileService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderStateService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderInventoryService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderFulfillmentService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderNotificationService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderTransactionService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Order\OrderExportService;
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

    private ?DigitalFileService $digitalFiles = null;

    private ?SettingsService $settings = null;

    private ?TaxService $taxService = null;

    /**
     * Cached user names keyed by user id for lock metadata.
     *
     * @var array<int, string>
     */
    private array $userNameCache = [];

    // Sub-services for delegation
    private ?OrderStateService $stateService = null;
    private ?OrderInventoryService $inventoryService = null;
    private ?OrderFulfillmentService $fulfillmentService = null;
    private ?OrderNotificationService $notificationService = null;
    private ?OrderTransactionService $transactionService = null;
    private ?OrderExportService $exportService = null;

    /**
     * OrderService constructor.
     *
     * @param DatabaseInterface $db Database connector
     *
     * @since 0.1.5
     */
    public function __construct(DatabaseInterface $db, ?AuditService $audit = null, ?DigitalFileService $digitalFiles = null)
    {
        $this->db           = $db;
        $this->audit        = $audit;
        $this->digitalFiles = $digitalFiles;
    }

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

    private function getDigitalFileService(): ?DigitalFileService
    {
        if ($this->digitalFiles !== null) {
            return $this->digitalFiles;
        }

        $container = Factory::getContainer();

        if ($container->has(DigitalFileService::class)) {
            $this->digitalFiles = $container->get(DigitalFileService::class);
        } else {
            $settings = $this->getSettingsService() ?? new SettingsService($this->db);
            $this->digitalFiles = new DigitalFileService($this->db, $settings);
        }

        return $this->digitalFiles;
    }

    private function getSettingsService(): ?SettingsService
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $container = Factory::getContainer();

        if ($container->has(SettingsService::class)) {
            $this->settings = $container->get(SettingsService::class);

            return $this->settings;
        }

        return null;
    }

    private function getTaxService(): TaxService
    {
        if ($this->taxService !== null) {
            return $this->taxService;
        }

        $container = Factory::getContainer();

        if ($container->has(TaxService::class)) {
            $this->taxService = $container->get(TaxService::class);
        } else {
            $this->taxService = new TaxService($this->db);
        }

        return $this->taxService;
    }

    // ------------------------------------------------------------------
    // Sub-Service Getters (lazy initialization)
    // ------------------------------------------------------------------

    private function getStateService(): OrderStateService
    {
        if ($this->stateService === null) {
            $this->stateService = new OrderStateService($this->db, $this->getAuditService());
        }

        return $this->stateService;
    }

    private function getInventoryService(): OrderInventoryService
    {
        if ($this->inventoryService === null) {
            $this->inventoryService = new OrderInventoryService($this->db);
        }

        return $this->inventoryService;
    }

    private function getFulfillmentService(): OrderFulfillmentService
    {
        if ($this->fulfillmentService === null) {
            $this->fulfillmentService = new OrderFulfillmentService(
                $this->db,
                $this->getAuditService(),
                $this->getSettingsService()
            );
        }

        return $this->fulfillmentService;
    }

    private function getNotificationService(): OrderNotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = new OrderNotificationService(
                $this->getAuditService(),
                $this->getDigitalFileService()
            );
        }

        return $this->notificationService;
    }

    private function getTransactionService(): OrderTransactionService
    {
        if ($this->transactionService === null) {
            $this->transactionService = new OrderTransactionService($this->db, $this->getAuditService());
        }

        return $this->transactionService;
    }

    private function getExportService(): OrderExportService
    {
        if ($this->exportService === null) {
            $this->exportService = new OrderExportService($this->db);
        }

        return $this->exportService;
    }

    /**
     * Resolve a user to a lightweight payload for lock metadata.
     */
    private function resolveUserMeta(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        if (!isset($this->userNameCache[$userId])) {
            $user = Factory::getUser($userId);
            $this->userNameCache[$userId] = $user && $user->id ? (string) $user->name : '';
        }

        $name = $this->userNameCache[$userId];

        if ($name === '') {
            return null;
        }

        return [
            'id'   => $userId,
            'name' => $name,
        ];
    }

    /**
     * Build a human-friendly lock message for orders.
     */
    private function buildLockMessage(int $userId): string
    {
        $user = $this->resolveUserMeta($userId);

        if ($user !== null && $user['name'] !== '') {
            return Text::sprintf('COM_NXPEASYCART_ERROR_ORDER_CHECKED_OUT', $user['name']);
        }

        return Text::_('COM_NXPEASYCART_ERROR_ORDER_CHECKED_OUT_GENERIC');
    }

    /**
     * Fetch lock state for an order.
     *
     * @return array{checked_out:int, checked_out_time:mixed}
     */
    private function getLockState(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select(
                [
                    $this->db->quoteName('checked_out'),
                    $this->db->quoteName('checked_out_time'),
                ]
            )
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if (!$row) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'), 404);
        }

        return [
            'checked_out'      => isset($row->checked_out) ? (int) $row->checked_out : 0,
            'checked_out_time' => $row->checked_out_time ?? null,
        ];
    }

    /**
     * Ensure the current actor can modify the order.
     */
    private function assertEditable(int $orderId, ?int $actorId = null): void
    {
        $lock = $this->getLockState($orderId);

        // If order is not checked out, allow
        if ($lock['checked_out'] === 0) {
            return;
        }

        $actorId = $actorId ?? 0;

        // If same user who checked out, allow
        if ($lock['checked_out'] === $actorId) {
            return;
        }

        // If we can't determine the actor but they passed authentication upstream, allow
        if ($actorId === 0) {
            return;
        }

        // Order is checked out by a different user
        throw new RuntimeException($this->buildLockMessage($lock['checked_out']), 423);
    }

    /**
     * Check out an order for editing.
     */
    public function checkout(int $orderId, int $actorId): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_CHECKED_OUT_GENERIC'), 403);
        }

        $this->assertEditable($orderId, $actorId);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('checked_out') . ' = :actor')
            ->set($this->db->quoteName('checked_out_time') . ' = :time')
            ->where($this->db->quoteName('id') . ' = :orderId')
            ->bind(':actor', $actorId, ParameterType::INTEGER)
            ->bind(':time', Factory::getDate()->toSql())
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->get($orderId) ?? [];
    }

    /**
     * Release a checkout lock on an order.
     */
    public function checkin(int $orderId, bool $force = false, ?int $actorId = null): array
    {
        $lock = $this->getLockState($orderId);

        if (!$force) {
            $actorId = $actorId ?? 0;

            if ($lock['checked_out'] !== 0 && $lock['checked_out'] !== $actorId) {
                throw new RuntimeException($this->buildLockMessage($lock['checked_out']), 423);
            }
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('checked_out') . ' = 0')
            ->set($this->db->quoteName('checked_out_time') . ' = NULL')
            ->where($this->db->quoteName('id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->get($orderId) ?? [];
    }

    /**
     * Look up tax rate name based on billing country/region.
     *
     * @param string $countryCode Two-letter country code
     * @param string $regionCode  Optional region code
     *
     * @return string|null Tax rate name or null if not found
     *
     * @since 0.1.13
     */
    private function lookupTaxName(string $countryCode, string $regionCode = ''): ?string
    {
        if ($countryCode === '') {
            return null;
        }

        $taxService = $this->getTaxService();
        $rates = $taxService->paginate(['search' => ''], 100, 0)['items'] ?? [];

        $countryCode = strtoupper($countryCode);
        $regionCode = strtolower(trim($regionCode));

        // First try to find exact country+region match
        foreach ($rates as $rate) {
            $rateCountry = strtoupper($rate['country'] ?? '');
            $rateRegion = strtolower(trim($rate['region'] ?? ''));

            if ($rateCountry === $countryCode && $rateRegion !== '' && $rateRegion === $regionCode) {
                return $rate['name'] ?? null;
            }
        }

        // Then try country-only match
        foreach ($rates as $rate) {
            $rateCountry = strtoupper($rate['country'] ?? '');
            $rateRegion = trim($rate['region'] ?? '');

            if ($rateCountry === $countryCode && $rateRegion === '') {
                return $rate['name'] ?? null;
            }
        }

        // Finally try global rate (no country specified)
        foreach ($rates as $rate) {
            $rateCountry = trim($rate['country'] ?? '');

            if ($rateCountry === '') {
                return $rate['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve tax name from payload or look it up from billing address.
     *
     * @param array<string, mixed> $payload Order payload
     * @param array<string, mixed> $billing Normalised billing address
     *
     * @return string|null Tax rate name
     *
     * @since 0.1.13
     */
    private function resolveTaxName(array $payload, array $billing): ?string
    {
        // If explicitly provided in payload, use that
        if (isset($payload['tax_name']) && trim((string) $payload['tax_name']) !== '') {
            return trim((string) $payload['tax_name']);
        }

        // Otherwise look up based on billing address
        $countryCode = $billing['country_code'] ?? '';
        $regionCode = $billing['region_code'] ?? '';

        return $this->lookupTaxName($countryCode, $regionCode);
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
        $items      = $this->applyDigitalFlags($items);
        $composition = $this->determineOrderComposition($items);
        $hasDigital  = $composition['has_digital'];
        $hasPhysical = $composition['has_physical'];

        if (empty($items)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_ITEMS_REQUIRED'));
        }

        if (!$hasPhysical) {
            $normalised['shipping_cents'] = 0;
            $normalised['shipping']       = null;
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
                'tax_name'       => $normalised['tax_name'],
                'shipping_cents' => $totals['shipping_cents'],
                'discount_cents' => $totals['discount_cents'],
                'total_cents'    => $totals['total_cents'],
                'currency'       => $normalised['currency'],
                'state'          => $normalised['state'],
                'payment_method' => $normalised['payment_method'],
                'locale'         => $normalised['locale'],
                'public_token'   => $publicToken,
                'has_digital'    => $hasDigital ? 1 : 0,
                'has_physical'   => $hasPhysical ? 1 : 0,
                'status_updated_at' => $statusTimestamp,
                'created'        => $statusTimestamp,
                'carrier'        => null,
                'tracking_number'=> null,
                'tracking_url'   => null,
                'fulfillment_events' => $this->encodeJson($fulfillmentEvents),
            ];

            $this->db->insertObject('#__nxp_easycart_orders', $order);

            $orderId = (int) $this->db->insertid();
            $storedItems = [];

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
                    'is_digital'       => !empty($item['is_digital']) ? 1 : 0,
                    'delivered_at'     => null,
                ];

                $this->db->insertObject('#__nxp_easycart_order_items', $itemObject);
                $itemId = (int) $this->db->insertid();

                $storedItems[] = [
                    'id'         => $itemId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'is_digital' => !empty($item['is_digital']),
                ];
            }

            if ($hasDigital) {
                $digitalService = $this->getDigitalFileService();

                if ($digitalService !== null) {
                    try {
                        $digitalService->createDownloadsForOrder($orderId, $storedItems);
                    } catch (\Throwable $exception) {
                        $this->getAuditService()->record(
                            'order',
                            $orderId,
                            'order.digital.downloads_failed',
                            ['message' => $exception->getMessage()],
                            $actorId
                        );
                    }
                }
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
        // Delegate core transition to OrderStateService
        $result = $this->getStateService()->transition(
            $orderId,
            $state,
            $actorId,
            fn(int $id) => $this->get($id),
            fn(int $id, ?int $actor) => $this->assertEditable($id, $actor)
        );

        $updated   = $result['order'];
        $fromState = $result['fromState'];

        // Send transactional emails based on state transition
        $this->getNotificationService()->sendStateTransitionEmail($updated, $fromState, $state);

        // Mark digital items delivered on fulfillment
        if ($state === 'fulfilled') {
            $this->getFulfillmentService()->markDigitalItemsDelivered($orderId);
        }

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
     * Send the "downloads ready" email for orders with digital items.
     *
     * Called when a manual payment is recorded and the order has digital items.
     * Ensures download links are populated before sending.
     *
     * @param array<string, mixed> $order
     *
     * @since 0.1.13
     */
    private function sendDownloadsReadyEmail(array $order): void
    {
        if (empty($order['has_digital'])) {
            return;
        }

        $mailService = $this->getMailService();

        if ($mailService === null) {
            return;
        }

        try {
            // Ensure downloads are populated with URLs
            if (empty($order['downloads']) && $this->digitalFileService !== null) {
                $orderId = (int) ($order['id'] ?? 0);

                if ($orderId > 0) {
                    $downloads = $this->digitalFileService->getDownloadsForOrder($orderId);

                    if (!empty($downloads)) {
                        $order['downloads'] = $downloads;
                    }
                }
            }

            $mailService->sendDownloadsReady($order);
        } catch (\Throwable $e) {
            // Log but don't fail the payment recording
            $this->getAuditService()->record(
                'order',
                (int) ($order['id'] ?? 0),
                'order.email.downloads_ready_failed',
                ['message' => $e->getMessage()]
            );
        }
    }

    /**
     * Update tracking metadata and append a fulfilment event.
     *
     * @since 0.1.5
     */
    public function updateTracking(int $orderId, array $tracking, ?int $actorId = null): array
    {
        // Delegate to OrderFulfillmentService
        return $this->getFulfillmentService()->updateTracking(
            $orderId,
            $tracking,
            $actorId,
            fn(int $id) => $this->get($id),
            fn(int $id, ?int $actor) => $this->assertEditable($id, $actor),
            fn(int $id, string $state, ?int $actor) => $this->transitionState($id, $state, $actor)
        );
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
        // Delegate to OrderFulfillmentService
        return $this->getFulfillmentService()->addNote(
            $orderId,
            $message,
            $actorId,
            fn(int $id) => $this->get($id),
            fn(int $id, ?int $actor) => $this->assertEditable($id, $actor)
        );
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
        // Delegate to OrderExportService
        return $this->getExportService()->exportToCsv(
            $filters,
            fn(array $orderIds) => $this->getOrderItemsMap($orderIds)
        );
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
            'tax_name'       => $this->resolveTaxName($payload, $billing),
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
                'is_digital'       => !empty($item['is_digital']),
            ];
        }

        return $normalised;
    }

    /**
     * Determine digital flags for each order item from authoritative product data.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.13
     */
    private function applyDigitalFlags(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $productIds = [];

        foreach ($items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;

            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }

        $productTypes = $this->loadProductTypes($productIds);

        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $isDigital = $productId > 0 && ($productTypes[$productId] ?? 'physical') === 'digital';
            $items[$index]['is_digital'] = $isDigital;
        }

        return $items;
    }

    /**
     * Summarise order composition between digital and physical items.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{has_digital: bool, has_physical: bool}
     *
     * @since 0.1.13
     */
    private function determineOrderComposition(array $items): array
    {
        $hasDigital  = false;
        $hasPhysical = false;

        foreach ($items as $item) {
            if (!empty($item['is_digital'])) {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }
        }

        return [
            'has_digital'  => $hasDigital,
            'has_physical' => $hasPhysical,
        ];
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
     * Load product types for a set of product IDs.
     *
     * @param array<int, int> $productIds
     *
     * @return array<int, string> Keyed by product ID
     *
     * @since 0.1.13
     */
    private function loadProductTypes(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if (empty($productIds)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('product_type'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_products'))
            ->whereIn($this->db->quoteName('id'), $productIds);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $types = [];

        foreach ($rows as $row) {
            $types[(int) $row->id] = isset($row->product_type)
                ? strtolower((string) $row->product_type)
                : 'physical';
        }

        return $types;
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
        $downloads    = [];

        if ($includeHistory) {
            $digitalService = $this->getDigitalFileService();

            if ($digitalService !== null) {
                try {
                    $downloads = $digitalService->getDownloadsForOrder($orderId);
                } catch (\Throwable $exception) {
                    $downloads = [];
                }
            }
        }
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
            'tax_name'       => isset($row->tax_name) ? (string) $row->tax_name : null,
            'shipping_cents' => (int) $row->shipping_cents,
            'discount_cents' => (int) $row->discount_cents,
            'total_cents'    => (int) $row->total_cents,
            'currency'       => (string) $row->currency,
            'state'          => (string) $row->state,
            'payment_method' => isset($row->payment_method) ? (string) $row->payment_method : null,
            'has_digital'    => (bool) ($row->has_digital ?? 0),
            'has_physical'   => (bool) ($row->has_physical ?? 0),
            'requires_shipping' => (bool) ($row->has_physical ?? 0),
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
            'checked_out'    => isset($row->checked_out) ? (int) $row->checked_out : 0,
            'checked_out_time' => $row->checked_out_time ?? null,
            'checked_out_user' => $this->resolveUserMeta(isset($row->checked_out) ? (int) $row->checked_out : 0),
            'items_count'    => $itemsCount,
            'items'          => $items,
            'transactions'   => $transactions,
            'timeline'       => $timeline,
            'downloads'      => $downloads,
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
                'is_digital'       => !empty($row->is_digital),
                'delivered_at'     => $row->delivered_at !== null ? (string) $row->delivered_at : null,
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
        // Delegate to OrderTransactionService
        return $this->getTransactionService()->recordTransaction(
            $orderId,
            $transaction,
            fn(int $id) => $this->get($id),
            fn(int $id, ?int $actor) => $this->assertEditable($id, $actor),
            fn(int $id, string $state, ?int $actor) => $this->transitionState($id, $state, $actor),
            fn(array $order) => $this->getFulfillmentService()->maybeAutoFulfillDigital(
                $order,
                fn(int $id, string $state) => $this->transitionState($id, $state)
            ),
            fn(array $order) => $this->getNotificationService()->sendDownloadsReadyEmail($order)
        );
    }

    /**
     * Auto-fulfil digital-only orders when payment completes.
     *
     * @since 0.1.13
     */
    private function maybeAutoFulfillDigital(array $order): void
    {
        $orderId = (int) ($order['id'] ?? 0);

        if (
            $orderId <= 0
            || empty($order['has_digital'])
            || !empty($order['has_physical'])
            || ($order['state'] ?? '') !== 'paid'
        ) {
            return;
        }

        $settings    = $this->getSettingsService();
        $autoFulfill = $settings ? (bool) $settings->get('digital_auto_fulfill', 1) : true;

        if (!$autoFulfill) {
            return;
        }

        try {
            $this->transitionState($orderId, 'fulfilled');
            $this->markDigitalItemsDelivered($orderId);
        } catch (\Throwable $exception) {
            $this->getAuditService()->record(
                'order',
                $orderId,
                'order.digital.auto_fulfill_failed',
                ['message' => $exception->getMessage()]
            );
        }
    }

    /**
     * Mark all digital order items as delivered.
     *
     * @since 0.1.13
     */
    private function markDigitalItemsDelivered(int $orderId): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_order_items'))
            ->set($this->db->quoteName('delivered_at') . ' = :deliveredAt')
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->where($this->db->quoteName('is_digital') . ' = 1')
            ->where($this->db->quoteName('delivered_at') . ' IS NULL')
            ->bind(':deliveredAt', $this->currentTimestamp(), ParameterType::STRING)
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
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
            ->where($this->db->quoteName('is_digital') . ' = 0')
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
        // Delegate to OrderInventoryService
        $this->getInventoryService()->reserveStockForItems($items);
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
        // Delegate to OrderStateService
        return $this->getStateService()->flagForReview(
            $orderId,
            $reason,
            $context,
            $actorId,
            fn(int $id) => $this->get($id)
        );
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
        // Delegate to OrderStateService
        return $this->getStateService()->clearReviewFlag(
            $orderId,
            $actorId,
            fn(int $id) => $this->get($id)
        );
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
        // Delegate to OrderStateService
        return $this->getStateService()->cancelStaleOrders(
            $hoursOld,
            $actorId,
            fn(int $id, string $state, ?int $actor) => $this->transitionState($id, $state, $actor),
            fn(int $id) => $this->getInventoryService()->releaseStockForOrder($id)
        );
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
        // Delegate to OrderInventoryService
        $this->getInventoryService()->releaseStockForOrder($orderId);
    }
}
