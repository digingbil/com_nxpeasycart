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
use Joomla\Component\Nxpeasycart\Administrator\Event\EasycartEventDispatcher;
use RuntimeException;
use Throwable;

/**
 * Order state machine service.
 *
 * Handles order state transitions, validation, review flags, and stale order cleanup.
 *
 * @since 0.3.2
 */
class OrderStateService
{
    public const ORDER_STATES = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

    /**
     * Valid state transitions for the order state machine.
     */
    public const VALID_TRANSITIONS = [
        'cart'      => ['pending', 'canceled'],
        'pending'   => ['paid', 'canceled'],
        'paid'      => ['fulfilled', 'refunded', 'canceled'],
        'fulfilled' => ['refunded'],
        'refunded'  => [],  // terminal state
        'canceled'  => [],  // terminal state
    ];

    private DatabaseInterface $db;
    private ?AuditService $audit = null;

    public function __construct(DatabaseInterface $db, ?AuditService $audit = null)
    {
        $this->db    = $db;
        $this->audit = $audit;
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
                    static fn ($container) => new AuditService($container->get(DatabaseInterface::class))
                );
            }

            $this->audit = $container->get(AuditService::class);
        }

        return $this->audit;
    }

    /**
     * Check if a state transition is valid.
     */
    public function isValidTransition(string $from, string $to): bool
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];

        return \in_array($to, $allowed, true);
    }

    /**
     * Get allowed next states for a given state.
     */
    public function getNextStates(string $currentState): array
    {
        return self::VALID_TRANSITIONS[$currentState] ?? [];
    }

    /**
     * Validate and perform a state transition.
     *
     * @return array{success: bool, order: array, fromState: string}
     */
    public function transition(int $orderId, string $toState, ?int $actorId = null, callable $getOrder, callable $assertEditable): array
    {
        $toState = strtolower(trim($toState));

        if (!\in_array($toState, self::ORDER_STATES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_STATE_INVALID'));
        }

        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $assertEditable($orderId, $actorId);

        $fromState = $order['state'];

        if ($fromState === $toState) {
            return ['success' => true, 'order' => $order, 'fromState' => $fromState];
        }

        if (!$this->isValidTransition($fromState, $toState)) {
            throw new RuntimeException(
                Text::sprintf(
                    'COM_NXPEASYCART_ERROR_ORDER_STATE_TRANSITION_INVALID',
                    $fromState,
                    $toState
                )
            );
        }

        $timestamp = Factory::getDate()->toSql();
        $events    = $this->normaliseFulfillmentEvents($order['fulfillment_events'] ?? []);
        $events[]  = $this->buildStatusEvent($toState, $timestamp, $actorId, $fromState);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('state') . ' = :state')
            ->set($this->db->quoteName('status_updated_at') . ' = :statusUpdatedAt')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('fulfillment_events') . ' = :fulfillmentEvents')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':state', $toState, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER)
            ->bind(':statusUpdatedAt', $timestamp, ParameterType::STRING)
            ->bind(':modified', $timestamp, ParameterType::STRING)
            ->bind(':fulfillmentEvents', json_encode($events, JSON_THROW_ON_ERROR), ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.state.transitioned',
            ['from' => $fromState, 'to' => $toState],
            $actorId
        );

        $updated = $getOrder($orderId);

        if (!$updated) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        // Dispatch plugin event
        EasycartEventDispatcher::afterOrderStateChange($updated, $fromState, $toState, $actorId);

        return ['success' => true, 'order' => $updated, 'fromState' => $fromState];
    }

    /**
     * Transition multiple orders to a new state.
     *
     * @return array{updated: array, failed: array}
     */
    public function bulkTransition(array $orderIds, string $state, ?int $actorId, callable $transitionSingle): array
    {
        $unique  = array_unique(array_map('intval', $orderIds));
        $updated = [];
        $failed  = [];

        foreach ($unique as $orderId) {
            if ($orderId <= 0) {
                continue;
            }

            try {
                $updated[] = $transitionSingle($orderId, $state, $actorId);
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
     * Flag an order for manual review.
     */
    public function flagForReview(int $orderId, string $reason, array $context, ?int $actorId, callable $getOrder): array
    {
        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $reason      = substr(trim($reason), 0, 255);
        $needsReview = 1;
        $timestamp   = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('needs_review') . ' = :needsReview')
            ->set($this->db->quoteName('review_reason') . ' = :reason')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':needsReview', $needsReview, ParameterType::INTEGER)
            ->bind(':reason', $reason, ParameterType::STRING)
            ->bind(':modified', $timestamp, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.flagged_for_review',
            array_merge(['reason' => $reason], $context),
            $actorId
        );

        return $getOrder($orderId);
    }

    /**
     * Clear the review flag on an order.
     */
    public function clearReviewFlag(int $orderId, ?int $actorId, callable $getOrder): array
    {
        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $needsReview = 0;
        $timestamp   = Factory::getDate()->toSql();

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_orders'))
            ->set($this->db->quoteName('needs_review') . ' = :needsReview')
            ->set($this->db->quoteName('review_reason') . ' = NULL')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':needsReview', $needsReview, ParameterType::INTEGER)
            ->bind(':modified', $timestamp, ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        $this->getAuditService()->record(
            'order',
            $orderId,
            'order.review_cleared',
            [],
            $actorId
        );

        return $getOrder($orderId);
    }

    /**
     * Cancel stale pending orders older than specified hours.
     */
    public function cancelStaleOrders(int $hoursOld, ?int $actorId, callable $transitionState, callable $releaseStock): array
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
                $transitionState((int) $orderId, 'canceled', $actorId);
                $releaseStock((int) $orderId);

                $this->getAuditService()->record(
                    'order',
                    (int) $orderId,
                    'order.stale_canceled',
                    ['hours_threshold' => $hoursOld, 'cutoff' => $cutoff]
                );

                $canceled[] = (int) $orderId;
            } catch (Throwable $e) {
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
     * Build a fulfillment event payload.
     */
    public function buildStatusEvent(string $state, string $timestamp, ?int $actorId = null, ?string $fromState = null): array
    {
        $state = strtolower(trim($state));
        $meta  = [];

        if ($fromState !== null && $fromState !== '') {
            $meta = ['from' => $fromState, 'to' => $state];
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
}
