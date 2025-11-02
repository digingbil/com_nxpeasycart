<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use RuntimeException;

/**
 * Admin API controller for payment configuration.
 */
class PaymentsController extends AbstractJsonController
{
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'show');

        return match ($task) {
            'show', 'list' => $this->show(),
            'update', 'save' => $this->update(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $service = $this->getService();

        return $this->respond([
            'config' => $service->getConfig(),
        ]);
    }

    protected function update(): JsonResponse
    {
        $this->assertCan('core.admin');
        $this->assertToken();

        $payload = $this->decodePayload();

        $service = $this->getService();
        $config  = $service->saveConfig($payload['config'] ?? []);

        return $this->respond([
            'config'  => $config,
            'message' => Text::_('COM_NXPEASYCART_PAYMENTS_SAVED'),
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

    private function getService(): PaymentGatewayService
    {
        $container = Factory::getContainer();

        if (!$container->has(SettingsService::class)) {
            $container->set(
                SettingsService::class,
                static fn ($container) => new SettingsService($container->get(\Joomla\Database\DatabaseInterface::class))
            );
        }

        if (!$container->has(PaymentGatewayService::class)) {
            $container->set(
                PaymentGatewayService::class,
                static fn ($container) => new PaymentGatewayService($container->get(SettingsService::class))
            );
        }

        return $container->get(PaymentGatewayService::class);
    }
}
