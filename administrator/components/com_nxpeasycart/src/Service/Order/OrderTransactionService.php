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
use RuntimeException;

/**
 * Order transaction service.
 *
 * Handles payment transaction recording and validation.
 *
 * @since 0.3.2
 */
class OrderTransactionService
{
    private DatabaseInterface $db;
    private ?AuditService $audit = null;

    public function __construct(DatabaseInterface $db, ?AuditService $audit = null)
    {
        $this->db    = $db;
        $this->audit = $audit;
    }

    /**
     * Record a payment transaction for an order.
     *
     * @param int   $orderId     Order ID
     * @param array $transaction Transaction data
     * @param callable $getOrder       fn(int): ?array
     * @param callable $assertEditable fn(int, ?int): void
     * @param callable $transitionState fn(int, string, ?int): array
     * @param callable $maybeAutoFulfill fn(array): void
     * @param callable $sendDownloadsEmail fn(array): void
     *
     * @return array Updated order
     *
     * @since 0.1.5
     */
    public function recordTransaction(
        int $orderId,
        array $transaction,
        callable $getOrder,
        callable $assertEditable,
        callable $transitionState,
        callable $maybeAutoFulfill,
        callable $sendDownloadsEmail
    ): array {
        $order = $getOrder($orderId);

        if (!$order) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_ORDER_NOT_FOUND'));
        }

        $assertEditable($orderId, $transaction['actor_id'] ?? null);

        $gateway = (string) ($transaction['gateway'] ?? '');

        if ($gateway === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_TRANSACTION_GATEWAY_REQUIRED'));
        }

        $idempotencyKey = isset($transaction['idempotency_key']) ? (string) $transaction['idempotency_key'] : '';

        if ($idempotencyKey !== '' && $this->transactionExistsByIdempotency($gateway, $idempotencyKey)) {
            return $getOrder($orderId);
        }

        $externalId = isset($transaction['external_id']) ? (string) $transaction['external_id'] : null;

        if ($externalId !== null && $this->transactionExistsByExternalId($gateway, $externalId)) {
            return $getOrder($orderId);
        }

        $transactionAmount   = (int) ($transaction['amount_cents'] ?? 0);
        $transactionCurrency = strtoupper((string) ($transaction['currency'] ?? ''));
        $orderCurrency       = strtoupper((string) ($order['currency'] ?? ''));
        $expectedAmount      = (int) ($order['total_cents'] ?? 0);

        $timestamp = Factory::getDate()->toSql();

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
            'created'      => $timestamp,
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

        $previousState = $order['state'] ?? '';
        $stateTransitioned = false;

        if ($shouldMarkPaid && $previousState !== 'paid' && $previousState !== 'fulfilled') {
            $transitionState($orderId, 'paid', null);
            $order = $getOrder($orderId) ?? $order;
            $stateTransitioned = true;
            // Inventory already reserved on creation; avoid double decrement.
        }

        if (($order['state'] ?? '') === 'paid') {
            $maybeAutoFulfill($order);
            $order = $getOrder($orderId) ?? $order;
        }

        // Send downloads ready email if order has digital items and was just marked as paid
        if ($stateTransitioned && !empty($order['has_digital'])) {
            $sendDownloadsEmail($order);
        }

        if (
            $shouldCancel
            && !\in_array($order['state'], ['canceled', 'refunded', 'fulfilled', 'paid'], true)
        ) {
            $transitionState($orderId, 'canceled', null);
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

        return $getOrder($orderId) ?? $order;
    }

    /**
     * Check if transaction exists by external ID.
     */
    public function transactionExistsByExternalId(string $gateway, string $externalId): bool
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

    /**
     * Check if transaction exists by idempotency key.
     */
    public function transactionExistsByIdempotency(string $gateway, string $idempotencyKey): bool
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
}
