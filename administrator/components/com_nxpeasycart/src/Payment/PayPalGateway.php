<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Http\Http;
use Joomla\Http\HttpFactory;
use RuntimeException;

/**
 * PayPal Checkout (Orders API) integration.
 *
 * @since 0.1.5
 */
class PayPalGateway implements PaymentGatewayInterface
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
            $headers = [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ];

            $response = $this->http->post(
                $this->apiBase() . '/v2/checkout/orders',
                json_encode($body),
                $headers
            );
        } catch (\Exception $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if ($response->code >= 400) {
            $errorData = json_decode($response->body, true);
            $errorMsg  = $errorData['message'] ?? $errorData['error_description'] ?? 'PayPal API error (HTTP ' . $response->code . ')';
            throw new RuntimeException($errorMsg);
        }

        $payload = json_decode($response->body, true);

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
        // SECURITY: Verify PayPal webhook signature before processing
        try {
            $accessToken = $this->verifyWebhookSignature($payload, $context);
        } catch (RuntimeException $exception) {
            $this->logWebhookError(
                'Verification failed: ' . $exception->getMessage(),
                $this->extractEventMetaForLog($payload, $context)
            );

            throw $exception;
        }

        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_PAYLOAD_INVALID'));
        }

        $resource     = $event['resource']             ?? [];
        $eventType    = strtoupper((string) ($event['event_type'] ?? ''));
        $purchaseUnit = $resource['purchase_units'][0] ?? [];
        $amount       = $purchaseUnit['amount']        ?? [];
        $value        = isset($amount['value']) ? (float) $amount['value'] : 0.0;
        $currency     = $amount['currency_code'] ?? 'USD';

        $transactionPayload = $this->buildTransactionPayload(
            $eventType,
            $resource,
            $accessToken ?? '',
            $event
        );

        return [
            'id'          => $event['id']         ?? null,
            'type'        => $event['event_type'] ?? null,
            'payload'     => $transactionPayload['payload'],
            'order_id'    => $transactionPayload['order_id'],
            'transaction' => $transactionPayload['transaction'],
            'currency' => $currency,
        ];
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        try {
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ];

            $response = $this->http->post(
                $this->apiBase() . '/v1/oauth2/token',
                'grant_type=client_credentials',
                $headers
            );
        } catch (\Exception $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        if ($response->code >= 400) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_TOKEN_FAILED'));
        }

        $payload = json_decode($response->body, true);

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

    /**
     * Verify PayPal webhook signature using PayPal's verification API.
     *
     * @param string $payload The raw webhook payload
     * @param array<string, mixed> $context Headers from the webhook request
     * @throws RuntimeException if verification fails
     * @return string Access token used for verification (can be reused for follow-up API calls)
     *
     * @since 0.1.5
     */
    private function verifyWebhookSignature(string $payload, array $context): string
    {
        $webhookId = trim((string) ($this->config['webhook_id'] ?? ''));

        // SECURITY: Webhook ID is mandatory to prevent webhook forgery
        if ($webhookId === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_ID_MISSING'));
        }

        // Extract required headers for verification
        $transmissionId   = $context['PayPal-Transmission-Id']   ?? '';
        $transmissionTime = $context['PayPal-Transmission-Time'] ?? '';
        $transmissionSig  = $context['PayPal-Transmission-Sig']  ?? '';
        $certUrl          = $context['PayPal-Cert-Url']          ?? '';
        $authAlgo         = $context['PayPal-Auth-Algo']         ?? '';

        if (empty($transmissionId) || empty($transmissionTime) || empty($transmissionSig)) {
            $this->logWebhookError(
                'PayPal webhook headers missing for verification',
                [
                    'transmission_id'   => $transmissionId ?: null,
                    'transmission_time' => $transmissionTime ?: null,
                    'has_sig'           => $transmissionSig !== '',
                ]
            );
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_HEADERS_MISSING'));
        }

        // Get access token for verification API
        $clientId     = trim((string) ($this->config['client_id'] ?? ''));
        $clientSecret = trim((string) ($this->config['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_CREDENTIALS_MISSING'));
        }

        $accessToken = $this->fetchAccessToken($clientId, $clientSecret);

        // Call PayPal's webhook verification endpoint
        // IMPORTANT: Decode WITHOUT associative array flag (false) to preserve empty objects as stdClass
        // This prevents {} from becoming [] during the decode/encode cycle, which would break signature verification
        $webhookEvent = json_decode($payload, false);

        if (json_last_error() !== JSON_ERROR_NONE || $webhookEvent === null) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_PAYLOAD_INVALID'));
        }

        $verificationBody = (object) [
            'transmission_id'   => $transmissionId,
            'transmission_time' => $transmissionTime,
            'cert_url'          => $certUrl,
            'auth_algo'         => $authAlgo,
            'transmission_sig'  => $transmissionSig,
            'webhook_id'        => $webhookId,
            'webhook_event'     => $webhookEvent,
        ];

        try {
            $headers = [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ];

            $response = $this->http->post(
                $this->apiBase() . '/v1/notifications/verify-webhook-signature',
                json_encode($verificationBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $headers
            );
        } catch (\Exception $exception) {
            throw new RuntimeException(
                Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_VERIFICATION_FAILED') . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if ($response->code >= 400) {
            $this->logWebhookError(
                'PayPal verification HTTP error',
                [
                    'http_code'       => $response->code,
                    'webhook_id'      => $this->maskWebhookId($webhookId),
                    'transmission_id' => $transmissionId,
                    'body_excerpt'    => substr((string) $response->body, 0, 300),
                ]
            );
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_VERIFICATION_FAILED'));
        }

        $result = json_decode($response->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logWebhookError(
                'PayPal verification response invalid JSON',
                [
                    'webhook_id'      => $this->maskWebhookId($webhookId),
                    'transmission_id' => $transmissionId,
                    'body_excerpt'    => substr((string) $response->body, 0, 300),
                ]
            );
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_VERIFICATION_INVALID'));
        }

        $verificationStatus = strtoupper((string) ($result['verification_status'] ?? ''));

        if ($verificationStatus !== 'SUCCESS') {
            $this->logWebhookError(
                'PayPal webhook signature verification failed',
                [
                    'status'          => $verificationStatus,
                    'transmission_id' => $transmissionId,
                ]
            );
            throw new RuntimeException(
                Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_SIGNATURE_INVALID') . ' (status: ' . $verificationStatus . ')'
            );
        }

        return $accessToken;
    }

    /**
     * Attempt to capture an approved PayPal order so we can transition to "paid" without waiting
     * for a separate capture webhook.
     *
     * @since 0.1.5
     */
    private function captureOrder(string $orderId, string $accessToken): array
    {
        if ($orderId === '') {
            throw new RuntimeException('Missing PayPal order ID for capture.');
        }

        if ($accessToken === '') {
            throw new RuntimeException('Missing access token for PayPal capture.');
        }

        try {
            $headers = [
                'Content-Type'      => 'application/json',
                'Authorization'     => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => 'nxp-ec-capture-' . $orderId,
            ];

            $response = $this->http->post(
                $this->apiBase() . '/v2/checkout/orders/' . urlencode($orderId) . '/capture',
                '',
                $headers
            );
        } catch (\Exception $exception) {
            throw new RuntimeException(
                'PayPal capture request failed: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if ($response->code >= 400) {
            throw new RuntimeException('PayPal capture failed (HTTP ' . $response->code . ').');
        }

        $payload = json_decode($response->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_PAYLOAD_INVALID'));
        }

        return $payload;
    }

    /**
     * Build a transaction payload from the webhook resource, capturing the order when applicable.
     *
     * @param string $eventType
     * @param array<string, mixed> $resource
     * @param string $accessToken
     * @param array<string, mixed> $event
     * @return array{transaction: array<string, mixed>, payload: array<string, mixed>, order_id: ?int}
     *
     * @since 0.1.5
     */
    private function buildTransactionPayload(
        string $eventType,
        array $resource,
        string $accessToken,
        array $event
    ): array {
        $orderId = $this->extractOrderId($resource);
        $amount      = $resource['amount'] ?? ($resource['purchase_units'][0]['amount'] ?? []);
        $currency    = $amount['currency_code'] ?? 'USD';
        $amountValue = isset($amount['value']) ? (float) $amount['value'] : 0.0;
        $paypalStatus = strtoupper((string) ($resource['status'] ?? ''));
        $externalId = $resource['id'] ?? null;
        $payload = $event;

        if ($eventType === 'CHECKOUT.ORDER.APPROVED' && !empty($resource['id'])) {
            // Auto-capture the order so we can mark it paid immediately.
            try {
                $capture = $this->captureOrder((string) $resource['id'], $accessToken);
                $captureDetails = $this->extractCaptureDetails($capture);

                if ($captureDetails['payload'] !== null) {
                    $payload = [
                        'event'   => $event,
                        'capture' => $captureDetails['payload'],
                    ];
                }

                $externalId   = $captureDetails['external_id'] ?? $externalId;
                $paypalStatus = $captureDetails['status']     ?? $paypalStatus;
                $amountValue  = $captureDetails['amount']     ?? $amountValue;
                $currency     = $captureDetails['currency']   ?? $currency;
            } catch (\Throwable $exception) {
                $this->logWebhookError(
                    'Auto-capture failed: ' . $exception->getMessage(),
                    ['event_type' => $eventType, 'order_id' => $orderId]
                );
                throw $exception;
            }
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $paypalStatus = 'COMPLETED';
        }

        $status = $paypalStatus === 'COMPLETED'
            ? 'paid'
            : ($resource['status'] ?? 'PENDING');

        return [
            'order_id' => $orderId,
            'payload'  => $payload,
            'transaction' => [
                'external_id'  => $externalId,
                'status'       => $status,
                'amount_cents' => (int) round($amountValue * 100),
                'currency'     => $currency,
            ],
        ];
    }

    /**
     * Extract the merchant order ID from common PayPal resource locations.
     *
     * @since 0.1.5
     */
    private function extractOrderId(array $resource): ?int
    {
        $candidates = [
            $resource['custom_id'] ?? null,
            $resource['purchase_units'][0]['custom_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $orderId = (int) $candidate;

            if ($orderId > 0) {
                return $orderId;
            }
        }

        return null;
    }

    /**
     * Extract capture details from a capture response payload.
     *
     * @return array{external_id: ?string, status: string, amount: float, currency: string, payload: ?array}
     *
     * @since 0.1.5
     */
    private function extractCaptureDetails(array $capture): array
    {
        $captureNode = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
        $amount      = $captureNode['amount'] ?? [];
        $value       = isset($amount['value']) ? (float) $amount['value'] : 0.0;

        return [
            'external_id' => $captureNode['id'] ?? ($capture['id'] ?? null),
            'status'      => strtoupper((string) ($captureNode['status'] ?? ($capture['status'] ?? ''))),
            'amount'      => $value,
            'currency'    => $amount['currency_code'] ?? ($capture['purchase_units'][0]['amount']['currency_code'] ?? 'USD'),
            'payload'     => !empty($capture) ? $capture : null,
        ];
    }

    /**
     * Log webhook verification/capture issues without exposing secrets.
     *
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    private function logWebhookError(string $message, array $context = []): void
    {
        try {
            $suffix = !empty($context)
                ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '';

            Log::add('[PayPal] ' . $message . $suffix, Log::ERROR, 'com_nxpeasycart');
        } catch (\Throwable $exception) {
            // If logging fails, we silently ignore to avoid masking the original error.
        }
    }

    /**
     * Mask webhook id for logs to avoid leaking the full value.
     *
     * @since 0.1.5
     */
    private function maskWebhookId(string $webhookId): string
    {
        $webhookId = trim($webhookId);

        if ($webhookId === '') {
            return '';
        }

        if (strlen($webhookId) <= 6) {
            return $webhookId;
        }

        return substr($webhookId, 0, 3) . '***' . substr($webhookId, -3);
    }

    /**
     * Reduce verification response for safe logging.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    private function safeVerificationResponse(array $response): array
    {
        $allowed = [
            'verification_status',
            'time',
            'transmission_id',
            'transmission_time',
            'status',
            'reason',
            'signing_cert_url',
        ];
        $filtered = [];

        foreach ($allowed as $key) {
            if (isset($response[$key])) {
                $filtered[$key] = $response[$key];
            }
        }

        return $filtered;
    }

    /**
     * Mask verification body for logging.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    private function maskVerificationBody(array $body): array
    {
        $masked = $body;

        if (isset($masked['transmission_sig'])) {
            $masked['transmission_sig'] = substr((string) $masked['transmission_sig'], 0, 6) . '***';
        }

        if (isset($masked['webhook_id'])) {
            $masked['webhook_id'] = $this->maskWebhookId((string) $masked['webhook_id']);
        }

        // Payload can be large; keep only ids and event_type
        if (isset($masked['webhook_event']) && \is_array($masked['webhook_event'])) {
            $event = $masked['webhook_event'];
            $masked['webhook_event'] = [
                'id'         => $event['id']         ?? null,
                'event_type' => $event['event_type'] ?? null,
                'resource'   => [
                    'id'          => $event['resource']['id'] ?? null,
                    'custom_id'   => $event['resource']['custom_id'] ?? ($event['resource']['purchase_units'][0]['custom_id'] ?? null),
                    'status'      => $event['resource']['status'] ?? null,
                ],
            ];
        }

        return $masked;
    }

    /**
     * Mask certificate URL to avoid logging full paths.
     *
     * @since 0.1.5
     */
    private function maskCertUrl(string $certUrl): string
    {
        $certUrl = trim($certUrl);

        if ($certUrl === '') {
            return '';
        }

        // Keep domain, drop query/paths.
        $parsed = parse_url($certUrl);
        $host = $parsed['host'] ?? '';

        return $host !== '' ? $host : substr($certUrl, 0, 20) . '...';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    private function extractEventMetaForLog(string $payload, array $context): array
    {
        $meta = [
            'event_type' => null,
            'transmission_id' => $context['PayPal-Transmission-Id'] ?? null,
        ];

        $decoded = json_decode($payload, true);

        if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
            $meta['event_type'] = $decoded['event_type'] ?? null;
        }

        return array_filter($meta, static fn ($value) => $value !== null && $value !== '');
    }
}
