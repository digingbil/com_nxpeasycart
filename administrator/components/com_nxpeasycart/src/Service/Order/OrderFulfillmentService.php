<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use RuntimeException;

/**
 * Order fulfillment service.
 *
 * Handles tracking updates, notes, and digital delivery.
 *
 * @since 0.3.2
 */
class OrderFulfillmentService
{
    private DatabaseInterface $db;
    private ?AuditService $audit = null;
    private ?SettingsService $settings = null;

    public function __construct(DatabaseInterface $db, ?AuditService $audit = null, ?SettingsService $settings = null)
    {
        $this->db       = $db;
        $this->audit    = $audit;
        $this->settings = $settings;
    }

    /**
     * Update tracking metadata and append a fulfilment event.
     *
     * @since 0.1.5
     */
    public function updateTracking(
        int $orderId,
        array $tracking,
        ?int $actorId,
        callable $getOrder,
        callable $assertEditable,
        callable $transitionState
    ): array {
        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $assertEditable($orderId, $actorId);

        $carrier        = substr(trim((string) ($tracking['carrier'] ?? '')), 0, 50);
        $trackingNumber = substr(trim((string) ($tracking['tracking_number'] ?? '')), 0, 64);
        $trackingUrl    = substr(trim((string) ($tracking['tracking_url'] ?? '')), 0, 255);
        $markFulfilled  = !empty($tracking['mark_fulfilled']);

        $timestamp = Factory::getDate()->toSql();
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

        $encodedEvents = json_encode($events, JSON_THROW_ON_ERROR);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('carrier') . ' = ' . ($carrier !== '' ? ':carrier' : 'NULL'))
            ->set($this->db->quoteName('tracking_number') . ' = ' . ($trackingNumber !== '' ? ':trackingNumber' : 'NULL'))
            ->set($this->db->quoteName('tracking_url') . ' = ' . ($trackingUrl !== '' ? ':trackingUrl' : 'NULL'))
            ->set($this->db->quoteName('fulfillment_events') . ' = :events')
            ->set($this->db->quoteName('status_updated_at') . ' = :statusUpdatedAt')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':events', $encodedEvents, ParameterType::STRING)
            ->bind(':statusUpdatedAt', $timestamp, ParameterType::STRING)
            ->bind(':modified', $timestamp, ParameterType::STRING)
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
            return $transitionState($orderId, 'fulfilled', $actorId);
        }

        return $getOrder($orderId) ?? $order;
    }

    /**
     * Append an audit note for fulfilment context.
     *
     * @since 0.1.5
     */
    public function addNote(
        int $orderId,
        string $message,
        ?int $actorId,
        callable $getOrder,
        callable $assertEditable
    ): array {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOTE_REQUIRED'));
        }

        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $assertEditable($orderId, $actorId);

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.note',
            ['message' => $message],
            $actorId
        );

        return $getOrder($orderId);
    }

    /**
     * Auto-fulfil digital-only orders when payment completes.
     *
     * @since 0.1.13
     */
    public function maybeAutoFulfillDigital(array $order, callable $transitionState): void
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

        $autoFulfill = $this->getSettingsService()
            ? (bool) $this->getSettingsService()->get('digital_auto_fulfill', 1)
            : true;

        if (!$autoFulfill) {
            return;
        }

        try {
            $transitionState($orderId, 'fulfilled');
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
    public function markDigitalItemsDelivered(int $orderId): void
    {
        $timestamp = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_order_items'))
            ->set($this->db->quoteName('delivered_at') . ' = :deliveredAt')
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->where($this->db->quoteName('is_digital') . ' = 1')
            ->where($this->db->quoteName('delivered_at') . ' IS NULL')
            ->bind(':deliveredAt', $timestamp, ParameterType::STRING)
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Normalise fulfillment events to a consistent structure.
     */
    public function normaliseFulfillmentEvents($events): array
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
                'at'       => $timestamp !== '' ? $timestamp : Factory::getDate()->toSql(),
                'actor_id' => isset($event['actor_id']) ? (int) $event['actor_id'] : null,
            ];
        }

        return $normalised;
    }

    /**
     * Get the AuditService, creating it if needed.
     */
    private function getAuditService(): AuditService
    {
        if ($this->audit === null) {
            $container = Factory::getContainer();

            if (!$container->has(AuditService::class)) {
                $container->set(
                    AuditService::class,
                    static fn ($container) => new AuditService(
                        $container->get(DatabaseInterface::class)
                    )
                );
            }

            $this->audit = $container->get(AuditService::class);
        }

        return $this->audit;
    }

    /**
     * Get the SettingsService, creating it if needed.
     */
    private function getSettingsService(): ?SettingsService
    {
        if ($this->settings === null) {
            $container = Factory::getContainer();

            if ($container->has(SettingsService::class)) {
                $this->settings = $container->get(SettingsService::class);
            }
        }

        return $this->settings;
    }
}
