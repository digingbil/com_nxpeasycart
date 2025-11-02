<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Joomla\CMS\Language\Text;
use RuntimeException;

/**
 * PayPal Checkout (Orders API) integration.
 */
class PayPalGateway implements PaymentGatewayInterface
{
    private ClientInterface $http;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config, ClientInterface $http)
    {
        $this->config = $config;
        $this->http   = $http;
    }

    public function createHostedCheckout(array $order, array $preferences = []): array
    {
        $clientId     = trim((string) ($this->config['client_id'] ?? ''));
        $clientSecret = trim((string) ($this->config['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_CREDENTIALS_MISSING'));
        }

        $accessToken = $this->fetchAccessToken($clientId, $clientSecret);
        $currency    = strtoupper((string) ($order['currency'] ?? 'USD'));
        $amountCents = (int) ($order['summary']['total_cents'] ?? 0);
        $value       = number_format($amountCents / 100, 2, '.', '');

        $body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) ($order['order_no'] ?? $order['id'] ?? ''),
                    'amount'       => [
                        'currency_code' => $currency,
                        'value'         => $value,
                    ],
                ],
            ],
            'application_context' => [
                'return_url'          => $preferences['success_url'] ?? '',
                'cancel_url'          => $preferences['cancel_url']  ?? '',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        if (!empty($order['id'])) {
            $body['purchase_units'][0]['custom_id'] = (string) $order['id'];
        }

        try {
            $response = $this->http->request('POST', $this->apiBase() . '/v2/checkout/orders', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $body,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($payload['links'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_ORDER_FAILED'));
        }

        $approveLink = '';

        foreach ($payload['links'] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveLink = $link['href'];
                break;
            }
        }

        if ($approveLink === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_APPROVAL_MISSING'));
        }

        return [
            'order_id' => $payload['id'] ?? null,
            'url'      => $approveLink,
            'gateway'  => 'paypal',
        ];
    }

    public function handleWebhook(string $payload, array $context = []): array
    {
        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_PAYLOAD_INVALID'));
        }

        $resource     = $event['resource']             ?? [];
        $purchaseUnit = $resource['purchase_units'][0] ?? [];
        $amount       = $purchaseUnit['amount']        ?? [];
        $value        = isset($amount['value']) ? (float) $amount['value'] : 0.0;
        $currency     = $amount['currency_code'] ?? 'USD';

        return [
            'id'          => $event['id']         ?? null,
            'type'        => $event['event_type'] ?? null,
            'payload'     => $event,
            'order_id'    => isset($purchaseUnit['custom_id']) ? (int) $purchaseUnit['custom_id'] : null,
            'transaction' => [
                'external_id'  => $resource['id'] ?? null,
                'status'       => strtoupper((string) ($resource['status'] ?? '')) === 'COMPLETED' ? 'paid' : ($resource['status'] ?? 'PENDING'),
                'amount_cents' => (int) round($value * 100),
                'currency'     => $currency,
            ],
            'currency' => $currency,
        ];
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        try {
            $response = $this->http->request('POST', $this->apiBase() . '/v1/oauth2/token', [
                'auth'        => [$clientId, $clientSecret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($payload['access_token'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_TOKEN_FAILED'));
        }

        return (string) $payload['access_token'];
    }

    private function apiBase(): string
    {
        $mode = strtolower((string) ($this->config['mode'] ?? 'sandbox'));

        return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
