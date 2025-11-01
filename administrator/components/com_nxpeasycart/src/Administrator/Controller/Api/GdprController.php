<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use RuntimeException;
use Nxp\EasyCart\Admin\Administrator\Service\GdprService;

/**
 * GDPR utilities (export/anonymise) for administrators.
 */
class GdprController extends AbstractJsonController
{
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'export');

        return match ($task) {
            'export' => $this->export(),
            'anonymise', 'anonymize' => $this->anonymise(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    protected function export(): JsonResponse
    {
        $this->assertCan('core.manage');

        $email = (string) $this->input->get('email', '', 'STRING');
        $service = $this->getService();

        $data = $service->exportByEmail($email);

        return $this->respond(['export' => $data]);
    }

    protected function anonymise(): JsonResponse
    {
        $this->assertCan('core.admin');
        $this->assertToken();

        $payload = $this->decodePayload();
        $email = (string) ($payload['email'] ?? '');

        $service = $this->getService();
        $affected = $service->anonymiseByEmail($email);

        return $this->respond([
            'affected' => $affected,
            'message' => Text::_('COM_NXPEASYCART_GDPR_ANONYMISED'),
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

    private function getService(): GdprService
    {
        $container = Factory::getContainer();

        if (!$container->has(GdprService::class)) {
            $container->set(
                GdprService::class,
                static fn ($container) => new GdprService($container->get(\Joomla\Database\DatabaseInterface::class))
            );
        }

        return $container->get(GdprService::class);
    }
}
