<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;
use Nxp\EasyCart\Admin\Administrator\Service\SettingsService;
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
            $task = trim((string) array_pop($segments));
        }

        $task = $task !== '' ? $task : 'show';

        $this->debug(sprintf('SettingsController handling task=%s', $task));

        return match ($task) {
            'show', 'browse' => $this->show(),
            'update' => $this->update(),
            default => $this->respond([
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
                'name' => (string) $service->get('store.name', ''),
                'email' => (string) $service->get('store.email', ''),
                'phone' => (string) $service->get('store.phone', ''),
            ],
            'payments' => [
                'configured' => (bool) $service->get('payments.configured', false),
            ],
            'base_currency' => ConfigHelper::getBaseCurrency(),
        ];

        return $this->respond(['settings' => $settings]);
    }

    protected function update(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $payload = $this->decodePayload();

        $store = isset($payload['store']) && \is_array($payload['store']) ? $payload['store'] : [];
        $payments = isset($payload['payments']) && \is_array($payload['payments']) ? $payload['payments'] : [];
        $baseCurrencyInput = $store['base_currency'] ?? $payload['base_currency'] ?? null;
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

        $service = $this->getService();

        $service->set('store.name', $name);
        $service->set('store.email', $email);
        $service->set('store.phone', $phone);
        $service->set('payments.configured', $paymentsConfigured);

        $baseCurrency = ConfigHelper::getBaseCurrency();

        return $this->respond([
            'settings' => [
                'store' => [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ],
                'payments' => [
                    'configured' => $paymentsConfigured,
                ],
                'base_currency' => $baseCurrency,
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
