<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Controller\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\ImportExportService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\PlatformAdapterFactory;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use RuntimeException;

/**
 * Export API controller.
 *
 * Handles export job creation, progress polling, and file downloads.
 *
 * @since 0.3.0
 */
class ExportController extends AbstractJsonController
{
    /**
     * {@inheritDoc}
     */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'platforms'        => $this->platforms(),
            'start'            => $this->start(),
            'progress'         => $this->progress(),
            'download'         => $this->download(),
            'cancel'           => $this->cancel(),
            'list', 'jobs'     => $this->jobs(),
            default            => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * Get available export platforms/formats.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function platforms(): JsonResponse
    {
        $this->assertCan('core.manage');

        $factory = $this->getAdapterFactory();
        $platforms = $factory->getAllPlatforms();

        $result = [];

        foreach ($platforms as $id => $name) {
            $result[] = [
                'id'   => $id,
                'name' => $name,
            ];
        }

        return $this->respond(['platforms' => $result]);
    }

    /**
     * Start an export job.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function start(): JsonResponse
    {
        $this->assertCan('core.manage');
        $this->assertToken();

        $payload = $this->decodePayload();

        $platform = $payload['platform'] ?? 'native';
        $options = $payload['options'] ?? [];

        // Validate platform
        $factory = $this->getAdapterFactory();

        if (!$factory->isSupported($platform)) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_UNSUPPORTED_PLATFORM', $platform), 400);
        }

        $service = $this->getImportExportService();
        $result = $service->startExport($platform, $options);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?? Text::_('COM_NXPEASYCART_ERROR_EXPORT_START_FAILED'), 500);
        }

        return $this->respond([
            'job_id'       => $result['job_id'],
            'status'       => $result['status'],
            'total_rows'   => $result['total_rows'],
        ], 201);
    }

    /**
     * Get export job progress.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function progress(): JsonResponse
    {
        $this->assertCan('core.manage');

        $jobId = $this->input->getInt('job_id', 0);

        if ($jobId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_JOB_ID_REQUIRED'), 400);
        }

        $service = $this->getImportExportService();
        $progress = $service->getJobProgress($jobId);

        if ($progress === null) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_JOB_NOT_FOUND'), 404);
        }

        return $this->respond($progress);
    }

    /**
     * Download export file.
     *
     * @return void
     *
     * @since 0.3.0
     */
    protected function download(): void
    {
        $this->assertCan('core.manage');

        $jobId = $this->input->getInt('job_id', 0);

        if ($jobId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_JOB_ID_REQUIRED'), 400);
        }

        $service = $this->getImportExportService();
        $fileInfo = $service->getExportFile($jobId);

        if ($fileInfo === null) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_EXPORT_FILE_NOT_FOUND'), 404);
        }

        if (!file_exists($fileInfo['path'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_EXPORT_FILE_MISSING'), 404);
        }

        // Send file download
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8');
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $fileInfo['filename'] . '"');
        $app->setHeader('Content-Length', (string) filesize($fileInfo['path']));
        $app->setHeader('Cache-Control', 'no-cache, must-revalidate');
        $app->setHeader('Pragma', 'no-cache');
        $app->sendHeaders();

        readfile($fileInfo['path']);

        $app->close();
    }

    /**
     * Cancel an export job.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function cancel(): JsonResponse
    {
        $this->assertCan('core.manage');
        $this->assertToken();

        $payload = $this->decodePayload();
        $jobId = (int) ($payload['job_id'] ?? 0);

        if ($jobId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_JOB_ID_REQUIRED'), 400);
        }

        $service = $this->getImportExportService();
        $result = $service->cancelJob($jobId);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?? Text::_('COM_NXPEASYCART_ERROR_JOB_CANCEL_FAILED'), 500);
        }

        return $this->respond(['cancelled' => true, 'job_id' => $jobId]);
    }

    /**
     * List export jobs.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function jobs(): JsonResponse
    {
        $this->assertCan('core.manage');

        $limit = $this->input->getInt('limit', 20);
        $start = $this->input->getInt('start', 0);

        $service = $this->getImportExportService();
        $jobs = $service->getJobs(['job_type' => 'export'], max(0, $start), max(1, $limit));

        return $this->respond([
            'items'      => $jobs['items'],
            'pagination' => $jobs['pagination'],
        ]);
    }

    /**
     * Decode JSON payload from request body.
     *
     * @return array
     *
     * @since 0.3.0
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

        return \is_array($data) ? $data : [];
    }

    /**
     * Get the platform adapter factory.
     *
     * @return PlatformAdapterFactory
     *
     * @since 0.3.0
     */
    private function getAdapterFactory(): PlatformAdapterFactory
    {
        $container = Factory::getContainer();

        if ($container->has(PlatformAdapterFactory::class)) {
            return $container->get(PlatformAdapterFactory::class);
        }

        return new PlatformAdapterFactory('EUR');
    }

    /**
     * Get the import/export service.
     *
     * @return ImportExportService
     *
     * @since 0.3.0
     */
    private function getImportExportService(): ImportExportService
    {
        $container = Factory::getContainer();

        if ($container->has(ImportExportService::class)) {
            return $container->get(ImportExportService::class);
        }

        $db = $container->get('DatabaseDriver');
        $settings = $container->has(SettingsService::class)
            ? $container->get(SettingsService::class)
            : new SettingsService($db);

        return new ImportExportService($db, $settings);
    }
}
