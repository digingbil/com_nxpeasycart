<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;


/**
 * Centralised payment gateway configuration manager.
 */
class PaymentGatewayService
{
    private const SETTINGS_KEY = 'payment_gateways';

    private SettingsService $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Return the stored configuration with sensitive fields masked.
     */
    public function getConfig(): array
    {
        $raw = $this->getRawConfig();

        return [
            'stripe' => [
                'publishable_key' => $raw['stripe']['publishable_key'] ?? '',
                'secret_key'      => $this->maskSecret($raw['stripe']['secret_key'] ?? ''),
                'webhook_secret'  => $this->maskSecret($raw['stripe']['webhook_secret'] ?? ''),
                'mode'            => $raw['stripe']['mode'] ?? 'test',
            ],
            'paypal' => [
                'client_id'     => $raw['paypal']['client_id'] ?? '',
                'client_secret' => $this->maskSecret($raw['paypal']['client_secret'] ?? ''),
                'webhook_id'    => $this->maskSecret($raw['paypal']['webhook_id'] ?? ''),
                'mode'          => $raw['paypal']['mode'] ?? 'sandbox',
            ],
        ];
    }

    /**
     * Return the stored configuration including secrets.
     */
    public function getRawConfig(): array
    {
        $stored = $this->settings->get(self::SETTINGS_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Persist configuration updates.
     *
     * @param array<string, mixed> $payload
     */
    public function saveConfig(array $payload): array
    {
        $existing   = $this->getRawConfig();
        $normalised = [
            'stripe' => $this->normaliseStripe($payload['stripe'] ?? [], $existing['stripe'] ?? []),
            'paypal' => $this->normalisePaypal($payload['paypal'] ?? [], $existing['paypal'] ?? []),
        ];

        $this->settings->set(self::SETTINGS_KEY, $normalised);

        return $this->getConfig();
    }

    /**
     * Retrieve configuration for a single gateway including secrets.
     */
    public function getGatewayConfig(string $gateway): array
    {
        $config = $this->getRawConfig();

        return $config[$gateway] ?? [];
    }

    private function maskSecret(string $value): string
    {
        return $value !== '' ? str_repeat('•', 6) : '';
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     */
    private function normaliseStripe(array $input, array $existing): array
    {
        $mode = isset($input['mode']) && strtolower((string) $input['mode']) === 'live' ? 'live' : 'test';

        $publishable = trim((string) ($input['publishable_key'] ?? ''));
        $secret      = trim((string) ($input['secret_key'] ?? ''));
        $webhook     = trim((string) ($input['webhook_secret'] ?? ''));

        return [
            'publishable_key' => $publishable !== '' ? $publishable : ($existing['publishable_key'] ?? ''),
            'secret_key'      => $secret           !== '' && strpos($secret, '•')  === false ? $secret : ($existing['secret_key'] ?? ''),
            'webhook_secret'  => $webhook      !== ''     && strpos($webhook, '•') === false ? $webhook : ($existing['webhook_secret'] ?? ''),
            'mode'            => $mode,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     */
    private function normalisePaypal(array $input, array $existing): array
    {
        $mode = isset($input['mode']) && strtolower((string) $input['mode']) === 'live' ? 'live' : 'sandbox';

        $clientId     = trim((string) ($input['client_id'] ?? ''));
        $clientSecret = trim((string) ($input['client_secret'] ?? ''));
        $webhookId    = trim((string) ($input['webhook_id'] ?? ''));

        return [
            'client_id'     => $clientId         !== '' ? $clientId : ($existing['client_id'] ?? ''),
            'client_secret' => $clientSecret !== '' && strpos($clientSecret, '•') === false
                ? $clientSecret
                : ($existing['client_secret'] ?? ''),
            'webhook_id' => $webhookId !== '' && strpos($webhookId, '•') === false
                ? $webhookId
                : ($existing['webhook_id'] ?? ''),
            'mode' => $mode,
        ];
    }
}
