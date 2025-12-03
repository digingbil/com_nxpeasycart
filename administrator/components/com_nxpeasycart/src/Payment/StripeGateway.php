<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use RuntimeException;

/**
 * Stripe checkout + webhook integration (Checkout Sessions API).
 *
 * @since 0.1.5
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
        $summary   = $this->extractSummary($order);
        $lineItems = $this->buildLineItems($order['items'] ?? [], $summary, $currency);
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
     *
     * @since 0.1.5
     */
    private function buildLineItems(array $items, array $summary, string $currency): array
    {
        if (empty($items)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_ITEMS_REQUIRED'));
        }

        $discount      = min((int) ($summary['discount_cents'] ?? 0), (int) ($summary['subtotal_cents'] ?? 0));
        $shippingCents = max(0, (int) ($summary['shipping_cents'] ?? 0));
        $taxCents      = max(0, (int) ($summary['tax_cents'] ?? 0));
        $taxInclusive  = !empty($summary['tax_inclusive']);
        $targetTotal   = max(0, (int) ($summary['total_cents'] ?? 0));
        $subtotal      = (int) ($summary['subtotal_cents'] ?? 0);

        if ($subtotal <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_ITEMS_REQUIRED'));
        }

        $adjustedItems = $this->applyDiscountToItems($items, $discount, $subtotal);
        $entries       = [];

        foreach ($adjustedItems as $item) {
            $name        = (string) ($item['title'] ?? ($item['sku'] ?? 'Item'));
            $qty         = max(1, (int) ($item['qty'] ?? 1));
            $totalCents  = (int) ($item['total_cents'] ?? 0);

            if ($totalCents <= 0) {
                continue;
            }

            $label = $qty > 1 ? sprintf('%s (x%d)', $name, $qty) : $name;

            $entries[] = [
                'name'         => $label,
                'amount_cents' => $totalCents,
                'quantity'     => 1, // Encode per-line total to avoid rounding drift
            ];
        }

        if ($shippingCents > 0) {
            $entries[] = [
                'name'         => Text::_('COM_NXPEASYCART_ORDER_SHIPPING'),
                'amount_cents' => $shippingCents,
                'quantity'     => 1,
            ];
        }

        if (!$taxInclusive && $taxCents > 0) {
            $entries[] = [
                'name'         => Text::_('COM_NXPEASYCART_CART_TAX'),
                'amount_cents' => $taxCents,
                'quantity'     => 1,
            ];
        }

        if (empty($entries)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_ITEMS_REQUIRED'));
        }

        $computedTotal = array_reduce(
            $entries,
            static fn (int $sum, array $entry) => $sum + ($entry['amount_cents'] ?? 0) * ($entry['quantity'] ?? 1),
            0
        );

        $delta = $targetTotal - $computedTotal;

        if ($delta !== 0) {
            $lastIndex = \count($entries) - 1;
            $entries[$lastIndex]['amount_cents'] += $delta;

            if ($entries[$lastIndex]['amount_cents'] <= 0) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_ITEMS_REQUIRED'));
            }
        }

        $params = [];
        $index  = 0;

        foreach ($entries as $entry) {
            $position                                                         = "line_items[$index]";
            $params[sprintf('%s[price_data][currency]', $position)]           = $currency;
            $params[sprintf('%s[price_data][unit_amount]', $position)]        = (int) $entry['amount_cents'];
            $params[sprintf('%s[price_data][product_data][name]', $position)] = (string) ($entry['name'] ?? 'Item');
            $params[sprintf('%s[quantity]', $position)]                       = (int) ($entry['quantity'] ?? 1);
            $index++;
        }

        return $params;
    }

    /**
     * @return array<string, string>
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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

    /**
     * @return array{subtotal_cents:int, shipping_cents:int, tax_cents:int, tax_inclusive:bool, discount_cents:int, total_cents:int}
     */
    private function extractSummary(array $order): array
    {
        $summary = isset($order['summary']) && \is_array($order['summary']) ? $order['summary'] : [];

        return [
            'subtotal_cents' => (int) ($summary['subtotal_cents'] ?? ($order['subtotal_cents'] ?? 0)),
            'shipping_cents' => (int) ($summary['shipping_cents'] ?? ($order['shipping_cents'] ?? 0)),
            'tax_cents'      => (int) ($summary['tax_cents'] ?? ($order['tax_cents'] ?? 0)),
            'tax_inclusive'  => !empty($summary['tax_inclusive'] ?? ($order['tax_inclusive'] ?? false)),
            'discount_cents' => (int) ($summary['discount_cents'] ?? ($order['discount_cents'] ?? 0)),
            'total_cents'    => (int) ($summary['total_cents'] ?? ($order['total_cents'] ?? 0)),
        ];
    }

    /**
     * Prorate discount across items to keep Stripe totals aligned with order totals.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function applyDiscountToItems(array $items, int $discountCents, int $subtotalCents): array
    {
        if ($discountCents <= 0 || $subtotalCents <= 0) {
            return array_map(
                static function ($item) {
                    if (\is_array($item)) {
                        $item['total_cents'] = (int) ($item['total_cents'] ?? (($item['unit_price_cents'] ?? 0) * ($item['qty'] ?? 1)));
                    }
                    return $item;
                },
                $items
            );
        }

        $distributed = 0;
        $count       = \count($items);
        $result      = [];

        foreach ($items as $idx => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $qty        = max(1, (int) ($item['qty'] ?? 1));
            $itemTotal  = (int) ($item['total_cents'] ?? (($item['unit_price_cents'] ?? 0) * $qty));
            $share      = $idx === $count - 1
                ? $discountCents - $distributed
                : (int) floor($discountCents * ($itemTotal / $subtotalCents));

            $distributed += $share;
            $adjustedTotal = max(0, $itemTotal - $share);

            $item['total_cents'] = $adjustedTotal;
            $item['qty']         = $qty;

            $result[] = $item;
        }

        return $result;
    }
}
