<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use RuntimeException;

/**
 * Stripe checkout + webhook integration (Checkout Sessions API).
 */
class StripeGateway implements PaymentGatewayInterface
{
    private Http $http;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config, ?Http $http = null)
    {
        $this->config = $config;
        $this->http   = $http ?? (new HttpFactory())->getHttp();
    }

    public function createHostedCheckout(array $order, array $preferences = []): array
    {
        $secret = trim((string) ($this->config['secret_key'] ?? ''));

        if ($secret === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_SECRET_MISSING'));
        }

        $currency  = strtolower((string) ($order['currency'] ?? 'usd'));
        $lineItems = $this->buildLineItems($order['items'] ?? [], $currency);
        $metadata  = $this->buildMetadata($order);

        $body = array_merge(
            [
                'mode'                    => 'payment',
                'success_url'             => $preferences['success_url'] ?? '',
                'cancel_url'              => $preferences['cancel_url']  ?? '',
                'payment_method_types[0]' => 'card',
            ],
            $lineItems,
            $metadata
        );

        if (!empty($order['email'])) {
            $body['customer_email'] = (string) $order['email'];
        }

        try {
            $headers = [
                'Authorization'  => 'Basic ' . base64_encode($secret . ':'),
                'Stripe-Version' => '2023-10-16',
                'Content-Type'   => 'application/x-www-form-urlencoded',
            ];

            $response = $this->http->post(
                'https://api.stripe.com/v1/checkout/sessions',
                http_build_query($body),
                $headers
            );
        } catch (\Exception $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if ($response->code >= 400) {
            $errorData = json_decode($response->body, true);
            $errorMsg  = $errorData['error']['message'] ?? 'Stripe API error (HTTP ' . $response->code . ')';
            throw new RuntimeException($errorMsg);
        }

        $payload = json_decode($response->body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($payload['id'], $payload['url'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_SESSION_FAILED'));
        }

        return [
            'session_id' => (string) $payload['id'],
            'url'        => (string) $payload['url'],
            'gateway'    => 'stripe',
        ];
    }

    public function handleWebhook(string $payload, array $context = []): array
    {
        $webhookSecret = trim((string) ($this->config['webhook_secret'] ?? ''));

        // SECURITY: Webhook secret is mandatory to prevent webhook forgery
        if ($webhookSecret === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_WEBHOOK_SECRET_MISSING'));
        }

        $signature = $context['Stripe-Signature'] ?? '';

        if (!$this->verifySignature($payload, $signature, $webhookSecret)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_SIGNATURE_INVALID'));
        }

        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_PAYLOAD_INVALID'));
        }

        $object   = $event['data']['object'] ?? [];
        $metadata = $object['metadata']      ?? [];

        return [
            'id'          => $event['id']   ?? null,
            'type'        => $event['type'] ?? null,
            'payload'     => $event,
            'order_id'    => isset($metadata['order_id']) ? (int) $metadata['order_id'] : null,
            'transaction' => $this->normaliseTransaction($event),
            'currency'    => isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildLineItems(array $items, string $currency): array
    {
        if (empty($items)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_ITEMS_REQUIRED'));
        }

        $params = [];

        foreach ($items as $index => $item) {
            $position                                                         = "line_items[$index]";
            $params[sprintf('%s[price_data][currency]', $position)]           = $currency;
            $params[sprintf('%s[price_data][unit_amount]', $position)]        = (int) ($item['unit_price_cents'] ?? 0);
            $params[sprintf('%s[price_data][product_data][name]', $position)] = (string) ($item['title'] ?? 'Item');
            $params[sprintf('%s[quantity]', $position)]                       = (int) ($item['qty'] ?? 1);
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    private function buildMetadata(array $order): array
    {
        $metadata = [];

        if (!empty($order['id'])) {
            $metadata['metadata[order_id]'] = (string) $order['id'];
        }

        if (!empty($order['order_no'])) {
            $metadata['metadata[order_no]'] = (string) $order['order_no'];
        }

        if (!empty($order['billing']['phone'])) {
            $metadata['metadata[phone]'] = (string) $order['billing']['phone'];
        }

        return $metadata;
    }

    private function verifySignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            $parts[$key]   = $value;
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $signedPayload = $parts['t'] . '.' . $payload;
        $expected      = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $parts['v1']);
    }

    /**
     * @return array<string, mixed>
     */
    private function normaliseTransaction(array $event): array
    {
        $object = $event['data']['object'] ?? [];
        $amount = isset($object['amount_total']) ? (int) $object['amount_total'] : 0;

        return [
            'external_id'  => $object['payment_intent'] ?? $object['id'] ?? null,
            'status'       => $event['type'] === 'checkout.session.completed' ? 'paid' : ($object['status'] ?? 'pending'),
            'amount_cents' => $amount,
            'currency'     => isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
        ];
    }
}
