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
use Joomla\CMS\Log\Log;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\ImportExportService;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\PlatformAdapterFactory;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use RuntimeException;

/**
 * Import API controller.
 *
 * Handles CSV file uploads, platform detection, job creation, and progress polling.
 *
 * @since 0.3.0
 */
class ImportController extends AbstractJsonController
{
    /**
     * Maximum file size in bytes (10MB).
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed MIME types for CSV files.
     */
    private const ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute($task)
    {
        $task = strtolower((string) $task ?: 'list');

        return match ($task) {
            'platforms'        => $this->platforms(),
            'upload'           => $this->upload(),
            'detect'           => $this->detect(),
            'start'            => $this->start(),
            'progress'         => $this->progress(),
            'cancel'           => $this->cancel(),
            'list', 'jobs'     => $this->jobs(),
            'samples'          => $this->samples(),
            default            => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    /**
     * Get available import platforms.
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
     * Upload a CSV file for import.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function upload(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $file = $this->input->files->get('file', [], 'array');

        if (empty($file) || empty($file['tmp_name'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_NO_FILE_UPLOADED'), 400);
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                Text::sprintf('COM_NXPEASYCART_ERROR_FILE_TOO_LARGE', self::MAX_FILE_SIZE / 1024 / 1024),
                400
            );
        }

        // Validate MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_FILE_TYPE'), 400);
        }

        // Move to temporary storage
        $service = $this->getImportExportService();
        $uploadResult = $service->storeUploadedFile($file['tmp_name'], $file['name']);

        if (!$uploadResult['success']) {
            throw new RuntimeException($uploadResult['error'] ?? Text::_('COM_NXPEASYCART_ERROR_FILE_UPLOAD_FAILED'), 500);
        }

        // Detect platform from headers
        $factory = $this->getAdapterFactory();
        $detectedPlatform = null;

        if (!empty($uploadResult['headers'])) {
            $detectedPlatform = $factory->detectPlatform($uploadResult['headers']);
        }

        return $this->respond([
            'file_id'           => $uploadResult['file_id'],
            'filename'          => $uploadResult['filename'],
            'size'              => $uploadResult['size'],
            'row_count'         => $uploadResult['row_count'],
            'headers'           => $uploadResult['headers'],
            'detected_platform' => $detectedPlatform,
            'preview'           => $uploadResult['preview'] ?? [],
        ], 201);
    }

    /**
     * Detect platform from uploaded file headers.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function detect(): JsonResponse
    {
        $this->assertCan('core.manage');

        $payload = $this->decodePayload();
        $headers = $payload['headers'] ?? [];

        if (empty($headers) || !\is_array($headers)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_HEADERS'), 400);
        }

        $factory = $this->getAdapterFactory();
        $platform = $factory->detectPlatform($headers);

        $mapping = [];

        if ($platform !== null) {
            $adapter = $factory->getAdapter($platform);
            $mapping = $adapter->getDefaultMapping();
        }

        return $this->respond([
            'platform' => $platform,
            'mapping'  => $mapping,
        ]);
    }

    /**
     * Start an import job.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function start(): JsonResponse
    {
        $this->assertCan('core.create');
        $this->assertToken();

        $payload = $this->decodePayload();

        $fileId = $payload['file_id'] ?? '';
        $platform = $payload['platform'] ?? '';
        $options = $payload['options'] ?? [];

        if (empty($fileId)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_FILE_ID_REQUIRED'), 400);
        }

        if (empty($platform)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PLATFORM_REQUIRED'), 400);
        }

        $service = $this->getImportExportService();
        $result = $service->startImport($fileId, $platform, $options);

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?? Text::_('COM_NXPEASYCART_ERROR_IMPORT_START_FAILED'), 500);
        }

        return $this->respond([
            'job_id'     => $result['job_id'],
            'status'     => $result['status'],
            'total_rows' => $result['total_rows'],
        ], 201);
    }

    /**
     * Get import job progress.
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
     * Cancel an import job.
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
     * List import jobs.
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
        $type = $this->input->getString('type', 'import');

        $service = $this->getImportExportService();
        $jobs = $service->getJobs(['job_type' => $type], max(0, $start), max(1, $limit));

        return $this->respond([
            'items'      => $jobs['items'],
            'pagination' => $jobs['pagination'],
        ]);
    }

    /**
     * Get sample CSV files info.
     *
     * @return JsonResponse
     *
     * @since 0.3.0
     */
    protected function samples(): JsonResponse
    {
        $this->assertCan('core.manage');

        $samplesDir = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/samples';
        $samples = [];

        $factory = $this->getAdapterFactory();
        $platforms = $factory->getAllPlatforms();

        foreach ($platforms as $id => $name) {
            $filename = $id . '_sample.csv';
            $filepath = $samplesDir . '/' . $filename;

            if (file_exists($filepath)) {
                $samples[] = [
                    'platform' => $id,
                    'name'     => $name,
                    'filename' => $filename,
                    'size'     => filesize($filepath),
                    'download' => 'index.php?option=com_nxpeasycart&task=api.import.downloadSample&platform=' . $id,
                ];
            }
        }

        return $this->respond(['samples' => $samples]);
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
