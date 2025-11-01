<?php

namespace Nxp\EasyCart\Admin\Administrator\Payment;

\defined('_JEXEC') or die;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Nxp\EasyCart\Admin\Administrator\Service\OrderService;
use Nxp\EasyCart\Admin\Administrator\Service\PaymentGatewayService;
use Nxp\EasyCart\Admin\Administrator\Service\MailService;
use RuntimeException;

/**
 * Orchestrates payment gateway actions across Stripe and PayPal.
 */
class PaymentGatewayManager
{
    private PaymentGatewayService $config;

    private OrderService $orders;

    private ClientInterface $http;

    private MailService $mailer;

    public function __construct(
        PaymentGatewayService $config,
        OrderService $orders,
        MailService $mailer,
        ?ClientInterface $http = null
    ) {
        $this->config = $config;
        $this->orders = $orders;
        $this->http = $http ?? new Client();
        $this->mailer = $mailer;
    }

    /**
     * Create a hosted checkout session for the given gateway.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $preferences
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
     */
    public function handleWebhook(string $gateway, string $payload, array $context = []): array
    {
        $driver = $this->resolveGateway($gateway);
        $event = $driver->handleWebhook($payload, $context);

        $orderId = isset($event['order_id']) ? (int) $event['order_id'] : 0;
        $order = null;

        if ($orderId > 0 && isset($event['transaction'])) {
            $transaction = $event['transaction'];

            $order = $this->orders->recordTransaction(
                $orderId,
                [
                    'gateway' => $gateway,
                    'external_id' => $transaction['external_id'] ?? null,
                    'status' => $transaction['status'] ?? 'pending',
                    'amount_cents' => (int) ($transaction['amount_cents'] ?? 0),
                    'currency' => $transaction['currency'] ?? ($event['currency'] ?? 'USD'),
                    'payload' => $event['payload'] ?? [],
                    'idempotency_key' => $event['id'] ?? null,
                ]
            );
        }

        if ($order && ($order['state'] ?? '') === 'paid') {
            $this->mailer->sendOrderConfirmation($order);
        }

        return $event;
    }

    private function resolveGateway(string $gateway): PaymentGatewayInterface
    {
        $key = strtolower($gateway);
        $config = $this->config->getGatewayConfig($key);

        return match ($key) {
            'stripe' => new StripeGateway($config, $this->http),
            'paypal' => new PayPalGateway($config, $this->http),
            default  => throw new RuntimeException('Unsupported payment gateway: ' . $gateway),
        };
    }
}
