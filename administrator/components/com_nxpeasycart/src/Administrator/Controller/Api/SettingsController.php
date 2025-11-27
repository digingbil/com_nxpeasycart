<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;
use RuntimeException;

class SettingsController extends AbstractJsonController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    public function execute($task)
    {
        $task = trim(strtolower((string) $task));

        if (str_contains($task, '.')) {
            $segments = array_filter(explode('.', $task));
            $task     = trim((string) array_pop($segments));
        }

        $task = $task !== '' ? $task : 'show';

        $this->debug(sprintf('SettingsController handling task=%s', $task));

        return match ($task) {
            'show', 'browse' => $this->show(),
            'update' => $this->update(),
            default  => $this->respond([
                'message' => Text::sprintf('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND', $task),
            ], 404),
        };
    }

    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $service = $this->getService();

        // The database already has normalized values (in seconds).
        // Just use them directly and fill in any missing fields with defaults.
        $rateLimits = (array) $service->get('security.rate_limits', []);
        $defaults = $this->getDefaultRateLimits();
        foreach ($defaults as $key => $defaultValue) {
            if (!isset($rateLimits[$key])) {
                $rateLimits[$key] = $defaultValue;
            }
        }

        $settings = [
            'store' => [
                'name'  => (string) $service->get('store.name', ''),
                'email' => (string) $service->get('store.email', ''),
                'phone' => (string) $service->get('store.phone', ''),
            ],
            'payments' => [
                'configured' => (bool) $service->get('payments.configured', false),
            ],
            'base_currency' => ConfigHelper::getBaseCurrency(),
            'checkout_phone_required' => ConfigHelper::isCheckoutPhoneRequired(),
            'visual' => [
                'primary_color' => (string) $service->get('visual.primary_color', ''),
                'text_color'    => (string) $service->get('visual.text_color', ''),
                'surface_color' => (string) $service->get('visual.surface_color', ''),
                'border_color'  => (string) $service->get('visual.border_color', ''),
                'muted_color'   => (string) $service->get('visual.muted_color', ''),
            ],
            'visual_defaults' => $this->getTemplateDefaults(),
            'security' => [
                'rate_limits' => [
                    'checkout_ip_limit'      => $rateLimits['checkout_ip_limit'],
                    'checkout_email_limit'   => $rateLimits['checkout_email_limit'],
                    'checkout_session_limit' => $rateLimits['checkout_session_limit'],
                    'checkout_window_minutes' => $rateLimits['checkout_window'] > 0
                        ? (int) ceil($rateLimits['checkout_window'] / 60)
                        : 0,
                    'offline_ip_limit'    => $rateLimits['offline_ip_limit'],
                    'offline_email_limit' => $rateLimits['offline_email_limit'],
                    'offline_window_minutes' => $rateLimits['offline_window'] > 0
                        ? (int) ceil($rateLimits['offline_window'] / 60)
                        : 0,
                ],
            ],
        ];

        return $this->respond(['settings' => $settings]);
    }

    protected function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $store             = isset($payload['store'])    && \is_array($payload['store']) ? $payload['store'] : [];
        $payments          = isset($payload['payments']) && \is_array($payload['payments']) ? $payload['payments'] : [];
        $visual            = isset($payload['visual'])   && \is_array($payload['visual']) ? $payload['visual'] : [];
        $security          = isset($payload['security']) && \is_array($payload['security']) ? $payload['security'] : [];
        $baseCurrencyInput = $store['base_currency'] ?? $payload['base_currency'] ?? null;
        $checkoutPhoneRequired = isset($payload['checkout_phone_required'])
            ? (bool) $payload['checkout_phone_required']
            : (isset($store['checkout_phone_required']) ? (bool) $store['checkout_phone_required'] : null);
        unset($store['base_currency']);

        $name = trim((string) ($store['name'] ?? ''));

        if (strlen($name) > 190) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SETTINGS_STORE_NAME_LENGTH'), 400);
        }

        $email = trim((string) ($store['email'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SETTINGS_EMAIL_INVALID'), 400);
        }

        $phone = trim((string) ($store['phone'] ?? ''));

        if (strlen($phone) > 64) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SETTINGS_PHONE_LENGTH'), 400);
        }

        $paymentsConfigured = isset($payments['configured']) ? (bool) $payments['configured'] : false;

        if ($baseCurrencyInput !== null) {
            try {
                ConfigHelper::setBaseCurrency((string) $baseCurrencyInput);
            } catch (RuntimeException $exception) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SETTINGS_BASE_CURRENCY_INVALID'), 400, $exception);
            }
        }

        if ($checkoutPhoneRequired !== null) {
            ConfigHelper::setCheckoutPhoneRequired((bool) $checkoutPhoneRequired);
        }

        $service = $this->getService();
        $rateLimits = $this->normaliseRateLimits(
            isset($security['rate_limits']) && \is_array($security['rate_limits'])
                ? $security['rate_limits']
                : [],
            (array) $service->get('security.rate_limits', [])
        );

        // Force override from explicit minute fields when provided to avoid any fallback drift.
        if (isset($security['rate_limits']['checkout_window_minutes'])) {
            $rateLimits['checkout_window'] = max(0, (int) $security['rate_limits']['checkout_window_minutes']) * 60;
        }

        if (isset($security['rate_limits']['offline_window_minutes'])) {
            $rateLimits['offline_window'] = max(0, (int) $security['rate_limits']['offline_window_minutes']) * 60;
        }

        // Only update store settings if provided in payload
        if (!empty($store)) {
            $service->set('store.name', $name);
            $service->set('store.email', $email);
            $service->set('store.phone', $phone);
        }

        // Only update payments settings if provided in payload
        if (!empty($payments)) {
            $service->set('payments.configured', $paymentsConfigured);
        }

        $service->set('security.rate_limits', $rateLimits);

        // Handle visual customization settings
        foreach (['primary_color', 'text_color', 'surface_color', 'border_color', 'muted_color'] as $colorKey) {
            if (isset($visual[$colorKey])) {
                $colorValue = trim((string) $visual[$colorKey]);
                $service->set('visual.' . $colorKey, $colorValue);
            }
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();

        // Use the values we just saved (don't re-read from database to avoid race conditions)

        // Always return current values from database (not just what was sent)
        return $this->respond([
            'settings' => [
                'store' => [
                    'name'  => (string) $service->get('store.name', ''),
                    'email' => (string) $service->get('store.email', ''),
                    'phone' => (string) $service->get('store.phone', ''),
                ],
                'payments' => [
                    'configured' => (bool) $service->get('payments.configured', false),
                ],
                'base_currency' => $baseCurrency,
                'checkout_phone_required' => ConfigHelper::isCheckoutPhoneRequired(),
                'visual' => [
                    'primary_color' => (string) $service->get('visual.primary_color', ''),
                    'text_color'    => (string) $service->get('visual.text_color', ''),
                    'surface_color' => (string) $service->get('visual.surface_color', ''),
                    'border_color'  => (string) $service->get('visual.border_color', ''),
                    'muted_color'   => (string) $service->get('visual.muted_color', ''),
                ],
                'visual_defaults' => $this->getTemplateDefaults(),
                'security' => [
                    'rate_limits' => [
                        'checkout_ip_limit'      => $rateLimits['checkout_ip_limit'],
                        'checkout_email_limit'   => $rateLimits['checkout_email_limit'],
                        'checkout_session_limit' => $rateLimits['checkout_session_limit'],
                        'checkout_window_minutes' => $rateLimits['checkout_window'] > 0
                            ? (int) ceil($rateLimits['checkout_window'] / 60)
                            : 0,
                        'offline_ip_limit'    => $rateLimits['offline_ip_limit'],
                        'offline_email_limit' => $rateLimits['offline_email_limit'],
                        'offline_window_minutes' => $rateLimits['offline_window'] > 0
                            ? (int) ceil($rateLimits['offline_window'] / 60)
                            : 0,
                    ],
                ],
            ],
        ]);
    }

    private function decodePayload(): array
    {
        $raw = $this->input->json->getRaw();

        if ($raw === null || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400);
        }

        return (array) $data;
    }

    /**
     * Normalise rate limit payload into a stored structure (windows in seconds).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     *
     * @return array<string, int>
     */
    private function normaliseRateLimits(array $input, array $existing = []): array
    {
        // Temporary debug hook to trace incoming payload during QA.
        if (getenv('NXP_EASYCART_DEBUG_RATELIMIT') === '1') {
            try {
                error_log('RateLimit input: ' . json_encode($input, JSON_UNESCAPED_SLASHES));
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $defaults = $this->getDefaultRateLimits();

        $checkoutWindowMinutes = array_key_exists('checkout_window_minutes', $input)
            ? $input['checkout_window_minutes']
            : (isset($existing['checkout_window']) ? (int) $existing['checkout_window'] / 60 : $defaults['checkout_window'] / 60);

        $offlineWindowMinutes = array_key_exists('offline_window_minutes', $input)
            ? $input['offline_window_minutes']
            : (isset($existing['offline_window']) ? (int) $existing['offline_window'] / 60 : $defaults['offline_window'] / 60);

        return [
            'checkout_ip_limit'      => $this->sanitiseLimit(
                array_key_exists('checkout_ip_limit', $input) ? $input['checkout_ip_limit'] : ($existing['checkout_ip_limit'] ?? null),
                $defaults['checkout_ip_limit']
            ),
            'checkout_email_limit'   => $this->sanitiseLimit(
                array_key_exists('checkout_email_limit', $input) ? $input['checkout_email_limit'] : ($existing['checkout_email_limit'] ?? null),
                $defaults['checkout_email_limit']
            ),
            'checkout_session_limit' => $this->sanitiseLimit(
                array_key_exists('checkout_session_limit', $input) ? $input['checkout_session_limit'] : ($existing['checkout_session_limit'] ?? null),
                $defaults['checkout_session_limit']
            ),
            'checkout_window'        => $this->sanitiseWindowSeconds($checkoutWindowMinutes, $defaults['checkout_window']),
            'offline_ip_limit'       => $this->sanitiseLimit(
                array_key_exists('offline_ip_limit', $input) ? $input['offline_ip_limit'] : ($existing['offline_ip_limit'] ?? null),
                $defaults['offline_ip_limit']
            ),
            'offline_email_limit'    => $this->sanitiseLimit(
                array_key_exists('offline_email_limit', $input) ? $input['offline_email_limit'] : ($existing['offline_email_limit'] ?? null),
                $defaults['offline_email_limit']
            ),
            'offline_window'         => $this->sanitiseWindowSeconds($offlineWindowMinutes, $defaults['offline_window']),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getDefaultRateLimits(): array
    {
        return [
            'checkout_ip_limit'      => 10,
            'checkout_email_limit'   => 5,
            'checkout_session_limit' => 15,
            'checkout_window'        => 900,
            'offline_ip_limit'       => 10,
            'offline_email_limit'    => 5,
            'offline_window'         => 14400,
        ];
    }

    private function sanitiseLimit($value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : $default;
    }

    private function sanitiseWindowSeconds($minutesValue, int $default): int
    {
        if ($minutesValue === null || $minutesValue === '') {
            return $default;
        }

        $minutes = (int) $minutesValue;

        if ($minutes < 0) {
            return $default;
        }

        return $minutes * 60;
    }

    private function getService(): SettingsService
    {
        $container = Factory::getContainer();

        if (!$container->has(SettingsService::class)) {
            $container->set(
                SettingsService::class,
                static fn ($container) => new SettingsService($container->get(DatabaseInterface::class))
            );
        }

        return $container->get(SettingsService::class);
    }

    /**
     * Get template color defaults without user overrides.
     */
    private function getTemplateDefaults(): array
    {
        try {
            // Temporarily switch to site app to resolve template
            $currentApp = Factory::getApplication();

            // Get template defaults by calling resolve WITHOUT applying user overrides
            // We need to replicate the logic but skip applyUserOverrides
            $resolved = TemplateAdapter::resolveWithoutOverrides();

            $cssVars = $resolved['css_vars'] ?? [];

            return [
                'primary_color' => $this->extractColor($cssVars['--nxp-ec-color-primary'] ?? '#4f6d7a'),
                'text_color'    => $this->extractColor($cssVars['--nxp-ec-color-text'] ?? '#1f2933'),
                'surface_color' => $this->extractColor($cssVars['--nxp-ec-color-surface'] ?? '#ffffff'),
                'border_color'  => $this->extractColor($cssVars['--nxp-ec-color-border'] ?? '#e4e7ec'),
                'muted_color'   => $this->extractColor($cssVars['--nxp-ec-color-muted'] ?? '#6b7280'),
            ];
        } catch (\Throwable $e) {
            // Return hardcoded defaults on error
            return [
                'primary_color' => '#4f6d7a',
                'text_color'    => '#1f2933',
                'surface_color' => '#ffffff',
                'border_color'  => '#e4e7ec',
                'muted_color'   => '#6b7280',
            ];
        }
    }

    /**
     * Extract a simple hex color from CSS value (handles var() fallbacks).
     */
    private function extractColor(string $value): string
    {
        // If it's already a hex color, return it
        if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            return strtolower($value);
        }

        // If it's rgba, convert to hex (simplified - just use fallback)
        if (str_starts_with($value, 'rgba(')) {
            return '#e4e7ec'; // Default for borders
        }

        // If it's a CSS var, try to extract the fallback
        if (preg_match('/var\([^,]+,\s*([#0-9a-f]+)\)/i', $value, $matches)) {
            return strtolower($matches[1]);
        }

        // Default fallback
        return '#4f6d7a';
    }

    private function debug(string $message): void
    {
        try {
            // Prefer Joomla configured log path; fall back to system temp dir if missing
            $logPath = '';

            try {
                if (isset($this->app) && method_exists($this->app, 'get')) {
                    $logPath = (string) ($this->app->get('log_path') ?? '');
                }
            } catch (\Throwable $e) {
                // Ignore and use fallback
            }

            // Secondary fallback: read from global config if available
            if ($logPath === '') {
                try {
                    $app = \Joomla\CMS\Factory::getApplication();
                    if ($app) {
                        $config = $app->getConfig();
                        if ($config) {
                            $logPath = (string) ($config->get('log_path') ?? '');
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore and use final fallback
                }
            }

            if ($logPath === '' || !is_dir($logPath)) {
                $logPath = sys_get_temp_dir();
            }

            $logFile = rtrim($logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'com_nxpeasycart-settings.log';

            $line = sprintf("[%s] %s\n", gmdate('c'), $message);
            file_put_contents($logFile, $line, FILE_APPEND);
        } catch (\Throwable $exception) {
            // Ignore logging errors.
        }
    }
}
