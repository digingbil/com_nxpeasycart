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

        // Handle visual customization settings
        foreach (['primary_color', 'text_color', 'surface_color', 'border_color', 'muted_color'] as $colorKey) {
            if (isset($visual[$colorKey])) {
                $colorValue = trim((string) $visual[$colorKey]);
                $service->set('visual.' . $colorKey, $colorValue);
            }
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();

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
            $logFile = defined('JPATH_LOGS')
                ? rtrim(JPATH_LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'com_nxpeasycart-settings.log'
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'com_nxpeasycart-settings.log';

            $line = sprintf("[%s] %s\n", gmdate('c'), $message);
            file_put_contents($logFile, $line, FILE_APPEND);
        } catch (\Throwable $exception) {
            // Ignore logging errors.
        }
    }
}
