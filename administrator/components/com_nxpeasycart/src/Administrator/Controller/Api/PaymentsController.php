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
 *
 * @since 0.1.5
 */
class PaymentsController extends AbstractJsonController
{
    /**
     * {@inheritDoc}
     *
     * @param string $task The task name
     * @return JsonResponse
     *
     * @since 0.1.5
     */
    public function execute($task): JsonResponse {
        $task = strtolower((string) $task ?: 'show');

        return match ($task) {
            'show', 'list' => $this->show(),
            'update', 'save' => $this->update(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * Handles the display of core configuration details for authorized users.
     *
     * @return JsonResponse The response containing core configuration details.
     *
     * @since 0.1.5
     */
    protected function show(): JsonResponse
    {
        $this->assertCan('core.manage');

        $service = $this->getService();

        return $this->respond([
            'config' => $service->getConfig(),
        ]);
    }

    /**
     * Updates the core configuration settings for authorized users and verifies the request token.
     *
     * Decodes the provided payload and saves the updated configuration using the service.
     * Returns a response containing the updated configuration details and a success message.
     *
     * @return JsonResponse The response including updated configuration details and a success message.
     *
     * @since 0.1.5
     */
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

    /**
     * Decodes the JSON payload from input, validating its structure and content.
     *
     * @return array The decoded JSON payload as an associative array.
     * @throws RuntimeException If the JSON is invalid or cannot be decoded.
     *
     * @since 0.1.5
     */
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
     * Retrieves an instance of the PaymentGatewayService, ensuring that both the PaymentGatewayService
     * and its dependency, the SettingsService, are instantiated and registered in the container if not already present.
     *
     * @return PaymentGatewayService An instance of PaymentGatewayService, retrieved or created from the container.
     *
     * @since 0.1.5
     */
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
