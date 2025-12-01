<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;


/**
 * Centralised payment gateway configuration manager.
 *
 * @since 0.1.5
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
     *
     * @since 0.1.5
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
            'cod' => [
                'enabled' => array_key_exists('cod', $raw)
                    ? (bool) ($raw['cod']['enabled'] ?? false)
                    : true,
                'label'   => $raw['cod']['label'] ?? 'Cash on delivery',
            ],
            'bank_transfer' => [
                'enabled'      => array_key_exists('bank_transfer', $raw)
                    ? (bool) ($raw['bank_transfer']['enabled'] ?? false)
                    : false,
                'label'        => $raw['bank_transfer']['label'] ?? 'Bank transfer',
                'instructions' => $raw['bank_transfer']['instructions'] ?? '',
                'account_name' => $raw['bank_transfer']['account_name'] ?? '',
                'iban'         => $raw['bank_transfer']['iban'] ?? '',
                'bic'          => $raw['bank_transfer']['bic'] ?? '',
            ],
        ];
    }

    /**
     * Return the stored configuration including secrets.
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
     */
    public function saveConfig(array $payload): array
    {
        $existing   = $this->getRawConfig();
        $normalised = [
            'stripe' => $this->normaliseStripe($payload['stripe'] ?? [], $existing['stripe'] ?? []),
            'paypal' => $this->normalisePaypal($payload['paypal'] ?? [], $existing['paypal'] ?? []),
            'cod'    => $this->normaliseCod($payload['cod'] ?? [], $existing['cod'] ?? []),
            'bank_transfer' => $this->normaliseBankTransfer(
                $payload['bank_transfer'] ?? [],
                $existing['bank_transfer'] ?? []
            ),
        ];

        $this->settings->set(self::SETTINGS_KEY, $normalised);

        return $this->getConfig();
    }

    /**
     * Retrieve configuration for a single gateway including secrets.
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     *
     * @since 0.1.5
     */
    private function normaliseCod(array $input, array $existing): array
    {
        $enabled = isset($input['enabled'])
            ? (bool) $input['enabled']
            : ($existing['enabled'] ?? true);

        $label = trim((string) ($input['label'] ?? ''));

        return [
            'enabled' => $enabled,
            'label'   => $label !== '' ? $label : ($existing['label'] ?? 'Cash on delivery'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     *
     * @since 0.1.5
     */
    private function normaliseBankTransfer(array $input, array $existing): array
    {
        $enabled = isset($input['enabled'])
            ? (bool) $input['enabled']
            : (bool) ($existing['enabled'] ?? false);

        $label        = trim((string) ($input['label'] ?? ''));
        $instructions = strip_tags(trim((string) ($input['instructions'] ?? '')));
        $accountName  = trim((string) ($input['account_name'] ?? ''));
        $iban         = $this->sanitiseIban((string) ($input['iban'] ?? ''));
        $bic          = $this->sanitiseBic((string) ($input['bic'] ?? ''));

        return [
            'enabled'      => $enabled,
            'label'        => $label !== '' ? $label : ($existing['label'] ?? 'Bank transfer'),
            'instructions' => $instructions !== '' ? $instructions : ($existing['instructions'] ?? ''),
            'account_name' => $accountName !== '' ? $accountName : ($existing['account_name'] ?? ''),
            'iban'         => $iban !== '' ? $iban : ($existing['iban'] ?? ''),
            'bic'          => $bic !== '' ? $bic : ($existing['bic'] ?? ''),
        ];
    }

    private function sanitiseIban(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalised = strtoupper((string) preg_replace('/\s+/', '', $value));

        return preg_match('/^[A-Z]{2}[0-9A-Z]{6,32}$/', $normalised) ? $normalised : '';
    }

    private function sanitiseBic(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalised = strtoupper(trim($value));

        return preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $normalised) ? $normalised : '';
    }
}
