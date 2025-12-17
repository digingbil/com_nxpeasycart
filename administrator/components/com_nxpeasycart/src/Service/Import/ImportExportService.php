<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\Folder;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;

/**
 * Import/Export service orchestrator.
 *
 * Manages job lifecycle, file handling, and coordinates import/export processing.
 *
 * @since 0.3.0
 */
class ImportExportService
{
    /**
     * Job statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * @var PlatformAdapterFactory
     */
    private PlatformAdapterFactory $adapterFactory;

    /**
     * @var SettingsService
     */
    private SettingsService $settings;

    /**
     * @var string Base path for import files
     */
    private string $importPath;

    /**
     * @var string Base path for export files
     */
    private string $exportPath;

    /**
     * @var int Chunk size for processing
     */
    private int $chunkSize = 50;

    /**
     * @var int Maximum file size in MB
     */
    private int $maxFileSize = 50;

    /**
     * Constructor.
     *
     * @param DatabaseInterface $db       Database driver
     * @param SettingsService   $settings Settings service
     *
     * @since 0.3.0
     */
    public function __construct(DatabaseInterface $db, SettingsService $settings)
    {
        $this->db = $db;
        $this->settings = $settings;

        // Get settings
        $this->chunkSize = (int) $this->settings->get('import_chunk_size', 50);
        $this->maxFileSize = (int) $this->settings->get('import_max_file_size', 50);

        // Get default currency
        $currency = $this->settings->get('base_currency', 'EUR');
        $this->adapterFactory = new PlatformAdapterFactory($currency);

        // Set paths
        $this->importPath = JPATH_ROOT . '/media/com_nxpeasycart/imports';
        $this->exportPath = JPATH_ROOT . '/media/com_nxpeasycart/exports';

        // Ensure directories exist
        $this->ensureDirectories();
    }

    /**
     * Create an import job from uploaded file.
     *
     * @param string $filePath     Uploaded file path
     * @param string $filename     Original filename
     * @param int    $userId       User ID
     * @param string $platform     Platform identifier (or 'auto' for detection)
     * @param array  $mapping      Column mapping
     * @param array  $options      Import options
     *
     * @return array{job_id: int, platform: string, headers: array, preview: array, total_rows: int}
     *
     * @throws \RuntimeException If file is invalid
     *
     * @since 0.3.0
     */
    public function createImportJob(
        string $filePath,
        string $filename,
        int $userId,
        string $platform = 'auto',
        array $mapping = [],
        array $options = []
    ): array {
        // Validate file
        $this->validateCsvFile($filePath);

        // Calculate file hash
        $fileHash = hash_file('sha256', $filePath);

        // Check for duplicate
        $existingJobId = $this->findJobByHash($fileHash);

        if ($existingJobId) {
            throw new \RuntimeException(
                "This file has already been imported (Job #{$existingJobId}). Upload a different file or delete the existing job."
            );
        }

        // Parse headers and detect platform
        $csvData = $this->parseCsvFile($filePath, 6); // Header + 5 preview rows
        $headers = $csvData['headers'] ?? [];
        $preview = $csvData['rows'] ?? [];

        if ($platform === 'auto') {
            $platform = $this->adapterFactory->detectPlatform($headers) ?? 'native';
        }

        // Get default mapping if not provided
        if (empty($mapping)) {
            $adapter = $this->adapterFactory->getAdapter($platform);
            $mapping = $adapter->getDefaultMapping();
        }

        // Count total rows
        $totalRows = $this->countCsvRows($filePath);

        // Move file to permanent location
        $storagePath = $this->storeImportFile($filePath, $userId);

        // Create job record
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $job = (object) [
            'job_type'          => 'import',
            'platform'          => $platform,
            'status'            => self::STATUS_PENDING,
            'total_rows'        => $totalRows,
            'processed_rows'    => 0,
            'last_processed_row' => 0,
            'imported_products' => 0,
            'imported_variants' => 0,
            'imported_categories' => 0,
            'skipped_rows'      => 0,
            'errors'            => null,
            'warnings'          => null,
            'file_path'         => $storagePath,
            'original_filename' => $filename,
            'file_hash'         => $fileHash,
            'mapping'           => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'options'           => json_encode($options, JSON_UNESCAPED_UNICODE),
            'created_by'        => $userId,
            'created'           => $now,
        ];

        $this->db->insertObject('#__nxp_easycart_import_jobs', $job);
        $jobId = (int) $this->db->insertid();

        return [
            'job_id'     => $jobId,
            'platform'   => $platform,
            'headers'    => $headers,
            'preview'    => $preview,
            'total_rows' => $totalRows,
        ];
    }

    /**
     * Process an import job in chunks.
     *
     * @param int      $jobId    Job ID
     * @param callable $progress Progress callback (processed, total, stats)
     *
     * @return array Final statistics
     *
     * @throws \RuntimeException If job is invalid or processing fails
     *
     * @since 0.3.0
     */
    public function processImportJob(int $jobId, callable $progress = null): array
    {
        $job = $this->getJob($jobId);

        if (!$job) {
            throw new \RuntimeException('Job not found: ' . $jobId);
        }

        if ($job->status === self::STATUS_PROCESSING) {
            throw new \RuntimeException('Job is already being processed');
        }

        if ($job->status === self::STATUS_COMPLETED) {
            throw new \RuntimeException('Job has already been completed');
        }

        // Update status to processing
        $this->updateJobStatus($jobId, self::STATUS_PROCESSING);

        $adapter = $this->adapterFactory->getAdapter($job->platform);
        $mapping = json_decode($job->mapping, true) ?: [];
        $options = json_decode($job->options, true) ?: [];
        $currency = $this->settings->get('base_currency', 'EUR');

        $processor = new ImportProcessor(
            $this->db,
            $adapter,
            $currency,
            (int) $job->created_by,
            $options
        );

        $allErrors = json_decode($job->errors ?? '[]', true) ?: [];
        $allWarnings = json_decode($job->warnings ?? '[]', true) ?: [];

        $totalProducts = (int) $job->imported_products;
        $totalVariants = (int) $job->imported_variants;
        $totalCategories = (int) $job->imported_categories;
        $totalSkipped = (int) $job->skipped_rows;

        try {
            // Open CSV and skip to last processed row
            $handle = fopen($job->file_path, 'r');

            if (!$handle) {
                throw new \RuntimeException('Cannot open import file');
            }

            // Read and skip header
            $headers = fgetcsv($handle);

            // Skip already processed rows
            $currentRow = 0;

            while ($currentRow < $job->last_processed_row && fgetcsv($handle) !== false) {
                $currentRow++;
            }

            // Process in chunks
            while (!feof($handle)) {
                // Check if job was cancelled
                $job = $this->getJob($jobId);

                if ($job->status === self::STATUS_CANCELLED) {
                    break;
                }

                // Read chunk
                $chunk = [];
                $chunkStartRow = $currentRow;

                for ($i = 0; $i < $this->chunkSize && !feof($handle); $i++) {
                    $row = fgetcsv($handle);

                    if ($row === false) {
                        break;
                    }

                    // Combine with headers
                    $rowData = [];

                    foreach ($headers as $idx => $header) {
                        $rowData[$header] = $row[$idx] ?? '';
                    }

                    // Normalize through adapter
                    $normalized = $adapter->normalizeRow($rowData, $mapping);
                    $chunk[$currentRow] = $normalized;
                    $currentRow++;
                }

                if (empty($chunk)) {
                    break;
                }

                // Process chunk
                $result = $processor->processChunk($chunk, $chunkStartRow);

                $totalProducts += $result['products'];
                $totalVariants += $result['variants'];
                $totalCategories += $result['categories'];
                $totalSkipped += $result['skipped'];
                $allErrors = array_merge($allErrors, $result['errors']);
                $allWarnings = array_merge($allWarnings, $result['warnings']);

                // Update job progress
                $this->updateJobProgress($jobId, [
                    'processed_rows'      => $currentRow,
                    'last_processed_row'  => $currentRow,
                    'imported_products'   => $totalProducts,
                    'imported_variants'   => $totalVariants,
                    'imported_categories' => $totalCategories,
                    'skipped_rows'        => $totalSkipped,
                    'errors'              => json_encode(array_slice($allErrors, -100), JSON_UNESCAPED_UNICODE),
                    'warnings'            => json_encode(array_slice($allWarnings, -100), JSON_UNESCAPED_UNICODE),
                ]);

                // Call progress callback
                if ($progress) {
                    $progress($currentRow, (int) $job->total_rows, [
                        'products'   => $totalProducts,
                        'variants'   => $totalVariants,
                        'categories' => $totalCategories,
                        'skipped'    => $totalSkipped,
                    ]);
                }

                // Reset processor stats for next chunk
                $processor->resetStats();
            }

            fclose($handle);

            // Mark as completed
            $this->updateJobStatus($jobId, self::STATUS_COMPLETED);

            return [
                'status'     => self::STATUS_COMPLETED,
                'products'   => $totalProducts,
                'variants'   => $totalVariants,
                'categories' => $totalCategories,
                'skipped'    => $totalSkipped,
                'errors'     => $allErrors,
                'warnings'   => $allWarnings,
            ];
        } catch (\Throwable $e) {
            $this->updateJobStatus($jobId, self::STATUS_FAILED);
            $allErrors[] = ['row' => 0, 'message' => $e->getMessage(), 'field' => null];

            $this->updateJobProgress($jobId, [
                'errors' => json_encode($allErrors, JSON_UNESCAPED_UNICODE),
            ]);

            throw $e;
        }
    }

    /**
     * Create an export job.
     *
     * @param int    $userId  User ID
     * @param string $format  Export format
     * @param array  $filters Export filters
     *
     * @return int Job ID
     *
     * @since 0.3.0
     */
    public function createExportJob(int $userId, string $format = 'native', array $filters = []): int
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Count total rows
        $exporter = new ExportProcessor($this->db);
        $exporter->setFilters($filters);
        $totalRows = $exporter->countTotal();

        $job = (object) [
            'job_type'          => 'export',
            'platform'          => $format,
            'status'            => self::STATUS_PENDING,
            'total_rows'        => $totalRows,
            'processed_rows'    => 0,
            'last_processed_row' => 0,
            'imported_products' => 0,
            'imported_variants' => 0,
            'imported_categories' => 0,
            'skipped_rows'      => 0,
            'options'           => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'created_by'        => $userId,
            'created'           => $now,
        ];

        $this->db->insertObject('#__nxp_easycart_import_jobs', $job);

        return (int) $this->db->insertid();
    }

    /**
     * Process an export job.
     *
     * @param int $jobId Job ID
     *
     * @return string Export file path
     *
     * @since 0.3.0
     */
    public function processExportJob(int $jobId): string
    {
        $job = $this->getJob($jobId);

        if (!$job || $job->job_type !== 'export') {
            throw new \RuntimeException('Export job not found: ' . $jobId);
        }

        $this->updateJobStatus($jobId, self::STATUS_PROCESSING);

        try {
            $filters = json_decode($job->options ?? '{}', true) ?: [];
            $format = $job->platform ?: 'native';

            // Generate file path
            $filename = sprintf('export_%d_%s.csv', $jobId, date('Ymd_His'));
            $filePath = $this->exportPath . '/' . $filename;

            $exporter = new ExportProcessor($this->db);
            $exporter->setFilters($filters);

            $result = $exporter->export($filePath, $format);

            // Update job
            $this->updateJobProgress($jobId, [
                'processed_rows' => $result['total'],
                'file_path'      => $filePath,
            ]);

            $this->updateJobStatus($jobId, self::STATUS_COMPLETED);

            return $filePath;
        } catch (\Throwable $e) {
            $this->updateJobStatus($jobId, self::STATUS_FAILED);

            throw $e;
        }
    }

    /**
     * Start an export job.
     *
     * Creates and immediately processes an export job.
     *
     * @param string $platform Export format/platform
     * @param array  $options  Export options (filters, etc.)
     *
     * @return array{success: bool, job_id?: int, status?: string, total_rows?: int, error?: string}
     *
     * @since 0.3.0
     */
    public function startExport(string $platform, array $options = []): array
    {
        try {
            $userId = (int) Factory::getApplication()->getIdentity()->id;
            $filters = $options['filters'] ?? [];

            // Create the export job
            $jobId = $this->createExportJob($userId, $platform, $filters);

            // Get job info before processing
            $job = $this->getJob($jobId);
            $totalRows = (int) ($job->total_rows ?? 0);

            // Process the job immediately (synchronous for MVP)
            try {
                $this->processExportJob($jobId);
            } catch (\Throwable $e) {
                // Job failed, but we still return the job ID so user can see the error
            }

            // Get current job status
            $job = $this->getJob($jobId);

            return [
                'success'    => true,
                'job_id'     => $jobId,
                'status'     => $job->status ?? self::STATUS_PENDING,
                'total_rows' => $totalRows,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get export file information for download.
     *
     * @param int $jobId Job ID
     *
     * @return array{path: string, filename: string}|null File info or null if not found
     *
     * @since 0.3.0
     */
    public function getExportFile(int $jobId): ?array
    {
        $job = $this->getJob($jobId);

        if (!$job || $job->job_type !== 'export' || $job->status !== self::STATUS_COMPLETED) {
            return null;
        }

        if (empty($job->file_path) || !file_exists($job->file_path)) {
            return null;
        }

        // Generate a friendly filename
        $platform = $job->platform ?: 'native';
        $date = (new \DateTime($job->created))->format('Y-m-d');
        $filename = sprintf('nxp_easycart_export_%s_%s.csv', $platform, $date);

        return [
            'path'     => $job->file_path,
            'filename' => $filename,
        ];
    }

    /**
     * Cancel a job.
     *
     * @param int $jobId Job ID
     *
     * @return array{success: bool, error?: string}
     *
     * @since 0.3.0
     */
    public function cancelJob(int $jobId): array
    {
        try {
            $job = $this->getJob($jobId);

            if (!$job) {
                return [
                    'success' => false,
                    'error'   => 'Job not found',
                ];
            }

            if (\in_array($job->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
                return [
                    'success' => false,
                    'error'   => 'Cannot cancel a job that is already ' . $job->status,
                ];
            }

            $this->updateJobStatus($jobId, self::STATUS_CANCELLED);

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a job by ID.
     *
     * @param int $jobId Job ID
     *
     * @return object|null
     *
     * @since 0.3.0
     */
    public function getJob(int $jobId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $jobId);

        $this->db->setQuery($query);

        return $this->db->loadObject() ?: null;
    }

    /**
     * Get job list with filters.
     *
     * @param array $filters Filter options
     * @param int   $offset  Offset
     * @param int   $limit   Limit
     *
     * @return array{items: array, pagination: array}
     *
     * @since 0.3.0
     */
    public function getJobs(array $filters = [], int $offset = 0, int $limit = 20): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->order('created DESC');

        // Apply filters
        if (!empty($filters['job_type'])) {
            $query->where($this->db->quoteName('job_type') . ' = ' . $this->db->quote($filters['job_type']));
        }

        if (!empty($filters['platform'])) {
            $query->where($this->db->quoteName('platform') . ' = ' . $this->db->quote($filters['platform']));
        }

        if (!empty($filters['status'])) {
            $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote($filters['status']));
        }

        if (!empty($filters['created_by'])) {
            $query->where($this->db->quoteName('created_by') . ' = ' . (int) $filters['created_by']);
        }

        // Get total count
        $countQuery = clone $query;
        $countQuery->clear('select')->select('COUNT(*)');
        $this->db->setQuery($countQuery);
        $total = (int) $this->db->loadResult();

        // Get paginated results
        $this->db->setQuery($query, $offset, $limit);
        $jobs = $this->db->loadObjectList() ?: [];

        // Transform jobs to include decoded JSON fields
        $items = array_map(function ($job) {
            return [
                'id'                  => (int) $job->id,
                'job_type'            => $job->job_type,
                'platform'            => $job->platform,
                'status'              => $job->status,
                'total_rows'          => (int) $job->total_rows,
                'processed_rows'      => (int) $job->processed_rows,
                'imported_products'   => (int) $job->imported_products,
                'imported_variants'   => (int) $job->imported_variants,
                'imported_categories' => (int) $job->imported_categories,
                'skipped_rows'        => (int) $job->skipped_rows,
                'original_filename'   => $job->original_filename ?? null,
                'created'             => $job->created,
                'started_at'          => $job->started_at,
                'completed_at'        => $job->completed_at,
            ];
        }, $jobs);

        return [
            'items'      => $items,
            'pagination' => [
                'total'  => $total,
                'offset' => $offset,
                'limit'  => $limit,
                'pages'  => $limit > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    /**
     * Delete a job and its files.
     *
     * @param int $jobId Job ID
     *
     * @return void
     *
     * @since 0.3.0
     */
    public function deleteJob(int $jobId): void
    {
        $job = $this->getJob($jobId);

        if ($job && $job->file_path && file_exists($job->file_path)) {
            @unlink($job->file_path);
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $jobId);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Store an uploaded file and return metadata.
     *
     * This is the public entry point for the upload controller to store
     * a CSV file and get back file info, headers, and preview rows.
     *
     * @param string $tmpPath       Temporary file path from PHP upload
     * @param string $originalName  Original filename from upload
     *
     * @return array{success: bool, file_id?: string, filename?: string, size?: int, row_count?: int, headers?: array, preview?: array, error?: string}
     *
     * @since 0.3.0
     */
    public function storeUploadedFile(string $tmpPath, string $originalName): array
    {
        try {
            // Validate the CSV file
            $this->validateCsvFile($tmpPath);

            // Get user ID
            $userId = (int) Factory::getApplication()->getIdentity()->id;

            // Store the file permanently
            $storedPath = $this->storeImportFile($tmpPath, $userId);

            // Generate a file ID (we'll use the filename without extension)
            $fileId = pathinfo($storedPath, PATHINFO_FILENAME);

            // Parse CSV headers and preview
            $csvData = $this->parseCsvFile($storedPath, 6);
            $headers = $csvData['headers'] ?? [];
            $preview = $csvData['rows'] ?? [];

            // Count rows
            $rowCount = $this->countCsvRows($storedPath);

            // Get file size
            $size = filesize($storedPath);

            // Store metadata in session for later retrieval
            $session = Factory::getApplication()->getSession();
            $session->set('nxpeasycart.import.' . $fileId, [
                'path'     => $storedPath,
                'filename' => $originalName,
                'user_id'  => $userId,
            ]);

            return [
                'success'   => true,
                'file_id'   => $fileId,
                'filename'  => $originalName,
                'size'      => $size,
                'row_count' => $rowCount,
                'headers'   => $headers,
                'preview'   => $preview,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Start an import job from a previously uploaded file.
     *
     * @param string $fileId   File ID from storeUploadedFile()
     * @param string $platform Platform identifier
     * @param array  $options  Import options
     *
     * @return array{success: bool, job_id?: int, status?: string, total_rows?: int, error?: string}
     *
     * @since 0.3.0
     */
    public function startImport(string $fileId, string $platform, array $options = []): array
    {
        try {
            // Retrieve file metadata from session
            $session = Factory::getApplication()->getSession();
            $fileData = $session->get('nxpeasycart.import.' . $fileId);

            if (!$fileData || !file_exists($fileData['path'])) {
                return [
                    'success' => false,
                    'error'   => 'Upload not found or expired. Please upload the file again.',
                ];
            }

            $userId = $fileData['user_id'];
            $filePath = $fileData['path'];
            $filename = $fileData['filename'];

            // Get adapter and mapping
            $adapter = $this->adapterFactory->getAdapter($platform);
            $mapping = $adapter->getDefaultMapping();

            // Create the import job
            $result = $this->createImportJob(
                $filePath,
                $filename,
                $userId,
                $platform,
                $mapping,
                $options
            );

            // Clear session data
            $session->clear('nxpeasycart.import.' . $fileId);

            // Start processing in the background (or synchronously for now)
            // For now, we'll just return the job info and let the client poll for progress
            $jobId = $result['job_id'];

            // Process the job immediately (synchronous for MVP)
            // In a production setup, this would be queued
            try {
                $this->processImportJob($jobId);
            } catch (\Throwable $e) {
                // Job failed, but we still return the job ID so user can see the error
            }

            // Get current job status
            $job = $this->getJob($jobId);

            return [
                'success'    => true,
                'job_id'     => $jobId,
                'status'     => $job->status ?? self::STATUS_PENDING,
                'total_rows' => $result['total_rows'],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get progress information for a job.
     *
     * @param int $jobId Job ID
     *
     * @return array|null Progress data or null if not found
     *
     * @since 0.3.0
     */
    public function getJobProgress(int $jobId): ?array
    {
        $job = $this->getJob($jobId);

        if (!$job) {
            return null;
        }

        return [
            'job_id'              => (int) $job->id,
            'job_type'            => $job->job_type,
            'platform'            => $job->platform,
            'status'              => $job->status,
            'total_rows'          => (int) $job->total_rows,
            'processed_rows'      => (int) $job->processed_rows,
            'imported_products'   => (int) $job->imported_products,
            'imported_variants'   => (int) $job->imported_variants,
            'imported_categories' => (int) $job->imported_categories,
            'skipped_rows'        => (int) $job->skipped_rows,
            'errors'              => json_decode($job->errors ?? '[]', true) ?: [],
            'warnings'            => json_decode($job->warnings ?? '[]', true) ?: [],
            'created'             => $job->created,
            'started_at'          => $job->started_at,
            'completed_at'        => $job->completed_at,
            'progress_percent'    => $job->total_rows > 0
                ? round(($job->processed_rows / $job->total_rows) * 100, 1)
                : 0,
        ];
    }

    /**
     * Detect platform from CSV headers.
     *
     * @param array $headers CSV headers
     *
     * @return string|null Platform identifier
     *
     * @since 0.3.0
     */
    public function detectPlatform(array $headers): ?string
    {
        return $this->adapterFactory->detectPlatform($headers);
    }

    /**
     * Get all supported platforms.
     *
     * @return array<string, string> Platform ID => Display name
     *
     * @since 0.3.0
     */
    public function getSupportedPlatforms(): array
    {
        return $this->adapterFactory->getAllPlatforms();
    }

    /**
     * Get default mapping for a platform.
     *
     * @param string $platform Platform identifier
     *
     * @return array
     *
     * @since 0.3.0
     */
    public function getDefaultMapping(string $platform): array
    {
        $adapter = $this->adapterFactory->getAdapter($platform);

        return $adapter->getDefaultMapping();
    }

    /**
     * Validate an uploaded CSV file.
     *
     * @param string $filePath File path
     *
     * @throws \RuntimeException If validation fails
     *
     * @since 0.3.0
     */
    private function validateCsvFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('File not found');
        }

        // Check file size
        $size = filesize($filePath);
        $maxBytes = $this->maxFileSize * 1024 * 1024;

        if ($size > $maxBytes) {
            throw new \RuntimeException(
                sprintf('File exceeds maximum size of %d MB', $this->maxFileSize)
            );
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'text/x-csv'];

        if (!\in_array($mime, $allowedMimes, true)) {
            throw new \RuntimeException('Invalid file type. Only CSV files are allowed.');
        }

        // Check for UTF-8 BOM and validate encoding
        $handle = fopen($filePath, 'r');
        $bom = fread($handle, 3);

        // Strip BOM if present
        if ($bom === "\xEF\xBB\xBF") {
            // File has BOM - it's UTF-8
        } else {
            // Check if content is valid UTF-8
            rewind($handle);
            $sample = fread($handle, 10000);

            if (!mb_check_encoding($sample, 'UTF-8')) {
                fclose($handle);

                throw new \RuntimeException('File encoding is not valid UTF-8');
            }
        }

        // Check for header row
        rewind($handle);

        // Skip BOM if present
        $firstBytes = fread($handle, 3);

        if ($firstBytes !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if (!$headers || empty(array_filter($headers))) {
            throw new \RuntimeException('CSV file has no header row');
        }
    }

    /**
     * Parse CSV file and return headers and preview rows.
     *
     * @param string $filePath File path
     * @param int    $maxRows  Maximum rows to read
     *
     * @return array{headers: array, rows: array}
     *
     * @since 0.3.0
     */
    private function parseCsvFile(string $filePath, int $maxRows = 6): array
    {
        $handle = fopen($filePath, 'r');

        // Skip BOM if present
        $bom = fread($handle, 3);

        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        $rows = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false && $count < $maxRows - 1) {
            $rowData = [];

            foreach ($headers as $idx => $header) {
                $rowData[$header] = $row[$idx] ?? '';
            }

            $rows[] = $rowData;
            $count++;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    /**
     * Count rows in CSV file (excluding header).
     *
     * @param string $filePath File path
     *
     * @return int
     *
     * @since 0.3.0
     */
    private function countCsvRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        $count = -1; // Exclude header

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return max(0, $count);
    }

    /**
     * Store import file in permanent location.
     *
     * @param string $tempPath Temporary file path
     * @param int    $userId   User ID
     *
     * @return string Permanent file path
     *
     * @since 0.3.0
     */
    private function storeImportFile(string $tempPath, int $userId): string
    {
        $userDir = $this->importPath . '/' . $userId;

        if (!is_dir($userDir)) {
            Folder::create($userDir);
        }

        $filename = sprintf('%d_%s.csv', time(), bin2hex(random_bytes(8)));
        $destPath = $userDir . '/' . $filename;

        if (!copy($tempPath, $destPath)) {
            throw new \RuntimeException('Failed to store import file');
        }

        return $destPath;
    }

    /**
     * Find a job by file hash.
     *
     * @param string $hash File hash
     *
     * @return int|null Job ID
     *
     * @since 0.3.0
     */
    private function findJobByHash(string $hash): ?int
    {
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->where($this->db->quoteName('file_hash') . ' = ' . $this->db->quote($hash))
            ->where($this->db->quoteName('status') . ' != ' . $this->db->quote(self::STATUS_CANCELLED))
            ->order('id DESC');

        $this->db->setQuery($query, 0, 1);
        $result = $this->db->loadResult();

        return $result ? (int) $result : null;
    }

    /**
     * Update job status.
     *
     * @param int    $jobId  Job ID
     * @param string $status New status
     *
     * @return void
     *
     * @since 0.3.0
     */
    private function updateJobStatus(int $jobId, string $status): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $data = ['status' => $status];

        if ($status === self::STATUS_PROCESSING) {
            $data['started_at'] = $now;
        } elseif (\in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            $data['completed_at'] = $now;
        }

        $this->updateJobProgress($jobId, $data);
    }

    /**
     * Update job progress.
     *
     * @param int   $jobId Job ID
     * @param array $data  Data to update
     *
     * @return void
     *
     * @since 0.3.0
     */
    private function updateJobProgress(int $jobId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $fields = [];

        // Use quote() instead of bind() to avoid reference issues
        foreach ($data as $key => $value) {
            if ($value === null) {
                $fields[] = $this->db->quoteName($key) . ' = NULL';
            } elseif (\is_int($value)) {
                $fields[] = $this->db->quoteName($key) . ' = ' . (int) $value;
            } else {
                $fields[] = $this->db->quoteName($key) . ' = ' . $this->db->quote($value);
            }
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->set($fields)
            ->where($this->db->quoteName('id') . ' = ' . (int) $jobId);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Ensure import/export directories exist.
     *
     * @return void
     *
     * @since 0.3.0
     */
    private function ensureDirectories(): void
    {
        if (!is_dir($this->importPath)) {
            Folder::create($this->importPath);
        }

        if (!is_dir($this->exportPath)) {
            Folder::create($this->exportPath);
        }

        // Create .htaccess to protect files
        $htaccess = "Order deny,allow\nDeny from all\n";

        if (!file_exists($this->importPath . '/.htaccess')) {
            file_put_contents($this->importPath . '/.htaccess', $htaccess);
        }

        if (!file_exists($this->exportPath . '/.htaccess')) {
            file_put_contents($this->exportPath . '/.htaccess', $htaccess);
        }
    }

    /**
     * Cleanup old jobs and files.
     *
     * @param int $days Days to retain (default 7)
     *
     * @return int Number of jobs deleted
     *
     * @since 0.3.0
     */
    public function cleanup(int $days = null): int
    {
        if ($days === null) {
            $days = (int) $this->settings->get('import_job_retention_days', 7);
        }

        $cutoff = (new \DateTime())->modify("-{$days} days")->format('Y-m-d H:i:s');

        // Get old jobs
        $query = $this->db->getQuery(true)
            ->select(['id', 'file_path'])
            ->from($this->db->quoteName('#__nxp_easycart_import_jobs'))
            ->where($this->db->quoteName('created') . ' < ' . $this->db->quote($cutoff));

        $this->db->setQuery($query);
        $jobs = $this->db->loadObjectList() ?: [];

        $deleted = 0;

        foreach ($jobs as $job) {
            // Delete file
            if ($job->file_path && file_exists($job->file_path)) {
                @unlink($job->file_path);
            }

            // Delete job record
            $this->deleteJob((int) $job->id);
            $deleted++;
        }

        return $deleted;
    }
}
