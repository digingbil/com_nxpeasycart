<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

use Joomla\Component\Nxpeasycart\Administrator\Event\EasycartEventDispatcher;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use RuntimeException;

/**
 * Orchestrates payment gateway actions across Stripe and PayPal.
 *
 * @since 0.1.5
 */
class PaymentGatewayManager
{
    private PaymentGatewayService $config;

    private OrderService $orders;

    private Http $http;

    private MailService $mailer;

    public function __construct(
        PaymentGatewayService $config,
        OrderService $orders,
        MailService $mailer,
        ?Http $http = null
    ) {
        $this->config = $config;
        $this->orders = $orders;
        $this->http   = $http ?? (new HttpFactory())->getHttp();
        $this->mailer = $mailer;
    }

    /**
     * Create a hosted checkout session for the given gateway.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $preferences
     *
     * @since 0.1.5
     */
    public function createHostedCheckout(string $gateway, array $order, array $preferences = []): array
    {
        $driver = $this->resolveGateway($gateway);

        return $driver->createHostedCheckout($order, $preferences);
    }

    /**
     * Handle webhook payloads from configured gateways.
     *
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    /**
     * Maximum allowed variance in cents before flagging for review.
     */
    private const AMOUNT_VARIANCE_TOLERANCE_CENTS = 1;

    public function handleWebhook(string $gateway, string $payload, array $context = []): array
    {
        $driver = $this->resolveGateway($gateway);
        $event  = $driver->handleWebhook($payload, $context);

        $orderId = isset($event['order_id']) ? (int) $event['order_id'] : 0;
        $order   = null;

        if ($orderId > 0 && isset($event['transaction'])) {
            $transaction = $event['transaction'];
            $webhookAmountCents = (int) ($transaction['amount_cents'] ?? 0);

            $order = $this->orders->recordTransaction(
                $orderId,
                [
                    'gateway'         => $gateway,
                    'external_id'     => $transaction['external_id'] ?? null,
                    'status'          => $transaction['status']      ?? 'pending',
                    'amount_cents'    => $webhookAmountCents,
                    'currency'        => $transaction['currency'] ?? ($event['currency'] ?? 'USD'),
                    'payload'         => $event['payload']        ?? [],
                    'idempotency_key' => $event['id']             ?? null,
                ]
            );

            // Check for payment amount mismatch after transaction is recorded
            if ($order && $webhookAmountCents > 0) {
                $this->checkAmountVariance($order, $webhookAmountCents, $gateway);
            }
        }

        if ($order && \in_array($order['state'] ?? '', ['paid', 'fulfilled'], true)) {
            try {
                $this->mailer->sendOrderConfirmation($order);
            } catch (\Throwable $mailException) {
                // Non-fatal: log but don't fail webhook processing if email fails
                // The order is already paid - email can be resent manually if needed
            }

            // Dispatch plugin event: onNxpEasycartAfterPaymentComplete
            EasycartEventDispatcher::afterPaymentComplete(
                $order,
                $event['transaction'] ?? [],
                $gateway
            );
        }

        return $event;
    }

    /**
     * Check for payment amount variance and flag order for review if mismatch detected.
     *
     * @param array  $order              The order record
     * @param int    $webhookAmountCents Amount received from webhook
     * @param string $gateway            Payment gateway name
     *
     * @since 0.1.9
     */
    private function checkAmountVariance(array $order, int $webhookAmountCents, string $gateway): void
    {
        $orderTotalCents = (int) ($order['total_cents'] ?? 0);
        $variance = abs($webhookAmountCents - $orderTotalCents);

        if ($variance > self::AMOUNT_VARIANCE_TOLERANCE_CENTS) {
            $this->orders->flagForReview(
                (int) $order['id'],
                'payment_amount_mismatch',
                [
                    'expected_cents' => $orderTotalCents,
                    'received_cents' => $webhookAmountCents,
                    'variance_cents' => $variance,
                    'gateway'        => $gateway,
                ]
            );
        }
    }

    private function resolveGateway(string $gateway): PaymentGatewayInterface
    {
        $key    = strtolower($gateway);
        $config = $this->config->getGatewayConfig($key);

        return match ($key) {
            'stripe' => new StripeGateway($config, $this->http),
            'paypal' => new PayPalGateway($config, $this->http),
            default  => throw new RuntimeException('Unsupported payment gateway: ' . $gateway),
        };
    }
}
