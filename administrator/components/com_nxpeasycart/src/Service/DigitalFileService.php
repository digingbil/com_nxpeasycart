<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Digital product file management + download token service.
 *
 * @since 0.1.13
 */
class DigitalFileService
{
    private const DEFAULT_STORAGE = 'media/com_nxpeasycart/downloads';

    /** @var int Default max file size in bytes (200 MB) */
    private const DEFAULT_MAX_FILE_SIZE = 209715200;

    /**
     * Allowed file types: extension => array of valid MIME types.
     * Extensions are lowercase without leading dot.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_FILE_TYPES = [
        // Archives
        '7z'     => ['application/x-7z-compressed'],
        'tar'    => ['application/x-tar'],
        'gz'     => ['application/gzip', 'application/x-gzip'],
        'tgz'    => ['application/gzip', 'application/x-gzip', 'application/x-tar'],
        'zip'    => ['application/zip', 'application/x-zip-compressed'],
        'rar'    => ['application/vnd.rar', 'application/x-rar-compressed'],

        // Audio
        'flac'   => ['audio/flac', 'audio/x-flac'],
        'mp3'    => ['audio/mpeg', 'audio/mp3'],
        'wav'    => ['audio/wav', 'audio/x-wav', 'audio/wave'],

        // Images
        'jpg'    => ['image/jpeg'],
        'jpeg'   => ['image/jpeg'],
        'png'    => ['image/png'],
        'gif'    => ['image/gif'],
        'svg'    => ['image/svg+xml'],
        'webp'   => ['image/webp'],
        'avif'   => ['image/avif'],

        // Documents
        'pdf'    => ['application/pdf'],
        'txt'    => ['text/plain'],
        'rtf'    => ['application/rtf', 'text/rtf'],
        'doc'    => ['application/msword'],
        'docx'   => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'    => ['application/vnd.ms-excel'],
        'xlsx'   => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'    => ['application/vnd.ms-powerpoint'],
        'pptx'   => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odt'    => ['application/vnd.oasis.opendocument.text'],
        'ods'    => ['application/vnd.oasis.opendocument.spreadsheet'],
        'odp'    => ['application/vnd.oasis.opendocument.presentation'],
        'csv'    => ['text/csv', 'text/plain', 'application/csv'],

        // Packages / Installers
        'deb'    => ['application/vnd.debian.binary-package', 'application/x-debian-package'],
        'rpm'    => ['application/x-rpm', 'application/x-redhat-package-manager'],
        'msi'    => ['application/x-ms-installer', 'application/x-msi'],
        'exe'    => ['application/vnd.microsoft.portable-executable', 'application/x-msdownload', 'application/x-dosexec'],
        'app'    => ['application/octet-stream'],
        'pkg'    => ['application/octet-stream', 'application/x-newton-compatible-pkg'],
        'dmg'    => ['application/octet-stream', 'application/x-apple-diskimage'],
        'apk'    => ['application/vnd.android.package-archive'],
        'ipa'    => ['application/octet-stream'],

        // Video
        'mp4'    => ['video/mp4'],
        'webm'   => ['video/webm'],
        'mov'    => ['video/quicktime'],
        'avi'    => ['video/x-msvideo', 'video/avi'],
        'mkv'    => ['video/x-matroska'],

        // E-books
        'epub'   => ['application/epub+zip'],
        'mobi'   => ['application/x-mobipocket-ebook'],
    ];

    /**
     * File type categories for UI grouping.
     *
     * @var array<string, array<int, string>>
     */
    public const FILE_TYPE_CATEGORIES = [
        'archives'   => ['zip', 'rar', '7z', 'tar', 'gz', 'tgz'],
        'audio'      => ['mp3', 'wav', 'flac'],
        'video'      => ['mp4', 'webm', 'mov', 'avi', 'mkv'],
        'images'     => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif'],
        'documents'  => ['pdf', 'txt', 'rtf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'csv'],
        'ebooks'     => ['epub', 'mobi'],
        'installers' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'app', 'pkg', 'apk', 'ipa'],
    ];

    private DatabaseInterface $db;

    private SettingsService $settings;

    public function __construct(DatabaseInterface $db, SettingsService $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /**
     * Upload and persist a digital file for a product/variant.
     *
     * @param array<string, mixed> $file Upload array (`name`, `tmp_name`, `error`, `size`, `type`)
     *
     * @return array<string, mixed> Stored file record
     */
    public function upload(int $productId, ?int $variantId, array $file, string $version = '1.0'): array
    {
        $this->assertProductExists($productId);

        if ($variantId !== null && $variantId > 0) {
            $this->assertVariantBelongsToProduct($variantId, $productId);
        } else {
            $variantId = null;
        }

        if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_UPLOAD_INVALID'));
        }

        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_OK;

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_UPLOAD_FAILED', $error));
        }

        // Validate file size
        $fileSize = isset($file['size']) ? (int) $file['size'] : (int) filesize($file['tmp_name']);
        $maxSizeMb = (int) $this->settings->get('digital_max_file_size', 200);
        $maxSizeBytes = $maxSizeMb > 0 ? $maxSizeMb * 1024 * 1024 : self::DEFAULT_MAX_FILE_SIZE;

        if ($fileSize > $maxSizeBytes) {
            throw new RuntimeException(
                Text::sprintf('COM_NXPEASYCART_ERROR_UPLOAD_FILE_TOO_LARGE', $maxSizeMb)
            );
        }

        $originalName = isset($file['name']) ? trim((string) $file['name']) : '';
        $safeName     = File::makeSafe($originalName !== '' ? $originalName : 'download.bin');

        if ($safeName === '') {
            $safeName = 'download.bin';
        }

        // Validate file extension
        $extension = $this->extractExtension($safeName);

        if (!$this->isAllowedExtension($extension)) {
            throw new RuntimeException(
                Text::sprintf('COM_NXPEASYCART_ERROR_UPLOAD_EXTENSION_NOT_ALLOWED', $extension)
            );
        }

        // Validate MIME type from file content (not trusting client-provided type)
        $detectedMime = '';

        if (function_exists('mime_content_type')) {
            $detectedMime = (string) mime_content_type($file['tmp_name']);
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $detectedMime = (string) finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        if ($detectedMime !== '' && !$this->isAllowedMime($extension, $detectedMime)) {
            throw new RuntimeException(
                Text::sprintf('COM_NXPEASYCART_ERROR_UPLOAD_MIME_MISMATCH', $extension, $detectedMime)
            );
        }

        $hashPrefix = bin2hex(random_bytes(12));
        $storedName = $hashPrefix . '_' . $safeName;

        $storageRelative = $this->getStorageRelativePath();
        $storageRoot     = $this->getStorageAbsolutePath($storageRelative);
        $this->ensureDirectory($storageRoot);
        $productPath     = $storageRoot . '/' . $productId;

        $this->ensureDirectory($productPath);

        $targetPath   = $productPath . '/' . $storedName;
        $relativePath = $storageRelative . '/' . $productId . '/' . $storedName;

        if (!File::upload($file['tmp_name'], $targetPath, false, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_UPLOAD_FAILED'));
        }

        // Use detected MIME or fall back to client-provided
        $mimeType = $detectedMime !== '' ? $detectedMime : (isset($file['type']) ? trim((string) $file['type']) : '');

        $now = Factory::getDate()->toSql();

        $record = (object) [
            'product_id'  => $productId,
            'variant_id'  => $variantId,
            'filename'    => $originalName !== '' ? $originalName : $safeName,
            'storage_path'=> $relativePath,
            'file_size'   => $fileSize,
            'mime_type'   => $mimeType !== '' ? $mimeType : null,
            'version'     => $version !== '' ? $version : '1.0',
            'created'     => $now,
            'modified'    => $now,
        ];

        $this->db->insertObject('#__nxp_easycart_digital_files', $record);
        $id = (int) $this->db->insertid();

        return $this->getFile($id) ?? [];
    }

    /**
     * Extract file extension from filename (lowercase, no dot).
     */
    private function extractExtension(string $filename): string
    {
        $pos = strrpos($filename, '.');

        if ($pos === false) {
            return '';
        }

        return strtolower(substr($filename, $pos + 1));
    }

    /**
     * Check if the extension is allowed (predefined + enabled, or custom).
     */
    private function isAllowedExtension(string $extension): bool
    {
        if ($extension === '') {
            return false;
        }

        $extension = strtolower($extension);

        // Check if it's an enabled predefined type
        if ($this->isEnabledPredefinedExtension($extension)) {
            return true;
        }

        // Check if it's a custom extension
        if ($this->isCustomExtension($extension)) {
            return true;
        }

        return false;
    }

    /**
     * Check if extension is a predefined type that's currently enabled.
     */
    private function isEnabledPredefinedExtension(string $extension): bool
    {
        // Must be in the predefined list
        if (!isset(self::ALLOWED_FILE_TYPES[$extension])) {
            return false;
        }

        // Get enabled extensions from settings (null = all enabled)
        $enabledExtensions = $this->settings->get('digital_allowed_extensions');

        if ($enabledExtensions === null) {
            // No restrictions - all predefined types allowed
            return true;
        }

        if (!\is_array($enabledExtensions)) {
            return true;
        }

        return \in_array($extension, $enabledExtensions, true);
    }

    /**
     * Check if extension is in the custom extensions list.
     */
    private function isCustomExtension(string $extension): bool
    {
        $customExtensions = $this->getCustomExtensions();

        return \in_array($extension, $customExtensions, true);
    }

    /**
     * Get parsed custom extensions from settings.
     *
     * @return array<int, string>
     */
    private function getCustomExtensions(): array
    {
        $custom = $this->settings->get('digital_custom_extensions', '');

        if (!\is_string($custom) || trim($custom) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $custom, -1, PREG_SPLIT_NO_EMPTY);

        if (!$parts) {
            return [];
        }

        $extensions = [];

        foreach ($parts as $part) {
            $ext = strtolower(ltrim(trim($part), '.'));

            if ($ext !== '' && preg_match('/^[a-z0-9]+$/', $ext)) {
                $extensions[] = $ext;
            }
        }

        return array_unique($extensions);
    }

    /**
     * Check if the detected MIME type matches allowed types for the extension.
     */
    private function isAllowedMime(string $extension, string $mimeType): bool
    {
        if ($extension === '' || $mimeType === '') {
            return false;
        }

        $extension = strtolower($extension);
        $mimeType  = strtolower($mimeType);

        // Custom extensions: bypass MIME check (accept any MIME including octet-stream)
        if ($this->isCustomExtension($extension)) {
            return true;
        }

        // Must be a predefined type from here
        if (!isset(self::ALLOWED_FILE_TYPES[$extension])) {
            return false;
        }

        $allowedMimes = self::ALLOWED_FILE_TYPES[$extension];

        // Direct match
        if (\in_array($mimeType, $allowedMimes, true)) {
            return true;
        }

        // Some files (especially .exe, .app, .pkg, .dmg) may report generic octet-stream
        if ($mimeType === 'application/octet-stream' && \in_array('application/octet-stream', $allowedMimes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get list of all predefined file extensions.
     *
     * @return array<int, string>
     */
    public function getAllPredefinedExtensions(): array
    {
        return array_keys(self::ALLOWED_FILE_TYPES);
    }

    /**
     * Get the file type categories for UI display.
     *
     * @return array<string, array<int, string>>
     */
    public function getFileTypeCategories(): array
    {
        return self::FILE_TYPE_CATEGORIES;
    }

    /**
     * Get currently allowed extensions (enabled predefined + custom).
     *
     * @return array<int, string>
     */
    public function getAllowedExtensions(): array
    {
        $enabledExtensions = $this->settings->get('digital_allowed_extensions');
        $customExtensions  = $this->getCustomExtensions();

        if ($enabledExtensions === null) {
            // All predefined + custom
            $predefined = array_keys(self::ALLOWED_FILE_TYPES);
        } elseif (\is_array($enabledExtensions)) {
            // Only enabled predefined
            $predefined = array_filter($enabledExtensions, fn($ext) => isset(self::ALLOWED_FILE_TYPES[$ext]));
        } else {
            $predefined = array_keys(self::ALLOWED_FILE_TYPES);
        }

        return array_unique(array_merge($predefined, $customExtensions));
    }

    /**
     * Delete a digital file and its download tokens.
     */
    public function delete(int $fileId): bool
    {
        $file = $this->getFile($fileId);

        if (!$file) {
            return false;
        }

        $absolutePath = $this->resolveAbsolutePath($file['storage_path']);

        if ($absolutePath !== null && is_file($absolutePath)) {
            File::delete($absolutePath);
        }

        // Remove download tokens referencing this file
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_downloads'))
            ->where($this->db->quoteName('file_id') . ' = :fileId')
            ->bind(':fileId', $fileId, ParameterType::INTEGER);
        $this->db->setQuery($query);
        $this->db->execute();

        $deleteFile = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_digital_files'))
            ->where($this->db->quoteName('id') . ' = :fileId')
            ->bind(':fileId', $fileId, ParameterType::INTEGER);

        $this->db->setQuery($deleteFile);
        $this->db->execute();

        return true;
    }

    /**
     * List files for a product (both product-level and variant-level).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilesForProduct(int $productId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_digital_files'))
            ->where($this->db->quoteName('product_id') . ' = :productId')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        return array_map(fn ($row) => $this->mapFileRow($row), $rows);
    }

    /**
     * List files explicitly attached to a variant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFilesForVariant(int $variantId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_digital_files'))
            ->where($this->db->quoteName('variant_id') . ' = :variantId')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        return array_map(fn ($row) => $this->mapFileRow($row), $rows);
    }

    /**
     * Generate a download token for a specific order line + file.
     */
    public function createDownloadToken(int $orderId, int $orderItemId, int $fileId, ?int $maxDownloads = null, ?string $expiresAt = null): string
    {
        $token = bin2hex(random_bytes(32));
        $now   = Factory::getDate()->toSql();

        $object = (object) [
            'order_id'       => $orderId,
            'order_item_id'  => $orderItemId,
            'file_id'        => $fileId,
            'token'          => $token,
            'download_count' => 0,
            'max_downloads'  => $maxDownloads !== null ? $maxDownloads : $this->getDefaultMaxDownloads(),
            'expires_at'     => $expiresAt !== null ? $expiresAt : $this->defaultExpiryDate(),
            'last_download_at' => null,
            'ip_address'     => null,
            'created'        => $now,
        ];

        $this->db->insertObject('#__nxp_easycart_downloads', $object);

        return $token;
    }

    /**
     * Validate a token and return the download row joined with file metadata.
     *
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select([
                'd.*',
                'f.filename',
                'f.storage_path',
                'f.mime_type',
                'f.file_size',
                'f.version',
                'o.state AS order_state',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_downloads', 'd'))
            ->join('INNER', $this->db->quoteName('#__nxp_easycart_digital_files', 'f') . ' ON ' . $this->db->quoteName('f.id') . ' = ' . $this->db->quoteName('d.file_id'))
            ->join('INNER', $this->db->quoteName('#__nxp_easycart_orders', 'o') . ' ON ' . $this->db->quoteName('o.id') . ' = ' . $this->db->quoteName('d.order_id'))
            ->where($this->db->quoteName('d.token') . ' = :token')
            ->bind(':token', $token, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        $expiresAt = $row->expires_at !== null ? strtotime((string) $row->expires_at) : null;

        if ($expiresAt !== null && $expiresAt > 0 && $expiresAt < time()) {
            return null;
        }

        $maxDownloads = $row->max_downloads !== null ? (int) $row->max_downloads : null;
        $downloaded   = (int) $row->download_count;

        if ($maxDownloads !== null && $downloaded >= $maxDownloads) {
            return null;
        }

        $orderState = strtolower((string) ($row->order_state ?? ''));

        if (!\in_array($orderState, ['paid', 'fulfilled'], true)) {
            return null;
        }

        return $this->mapDownloadRow($row);
    }

    /**
     * Record a download attempt (increments counters).
     */
    public function recordDownload(string $token, string $ipAddress): bool
    {
        $download = $this->getDownloadByToken($token);

        if (!$download) {
            return false;
        }

        $last = Factory::getDate()->toSql();
        $ip45 = substr($ipAddress, 0, 45);

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_downloads'))
            ->set($this->db->quoteName('download_count') . ' = ' . $this->db->quoteName('download_count') . ' + 1')
            ->set($this->db->quoteName('last_download_at') . ' = :last')
            ->set($this->db->quoteName('ip_address') . ' = :ip')
            ->where($this->db->quoteName('token') . ' = :token')
            ->bind(':last', $last, ParameterType::STRING)
            ->bind(':ip', $ip45, ParameterType::STRING)
            ->bind(':token', $token, ParameterType::STRING);

        $this->db->setQuery($query);
        $this->db->execute();

        return true;
    }

    /**
     * Stream a digital download to the browser.
     */
    public function streamDownload(string $token): void
    {
        $download = $this->validateToken($token);

        if (!$download) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID'), 404);
        }

        $absolute = $this->resolveAbsolutePath($download['storage_path']);

        if ($absolute === null || !is_file($absolute)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID'), 404);
        }

        $this->recordDownload($token, $this->getClientIp());

        $mime = $download['mime_type'] ?? 'application/octet-stream';
        $name = $download['filename'] ?? basename($absolute);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($absolute));
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($absolute);

        Factory::getApplication()->close();
    }

    /**
     * Create download entries for digital items within an order.
     *
     * @param array<int, array<string, mixed>> $orderItems
     *
     * @return array<int, array<string, mixed>>
     */
    public function createDownloadsForOrder(int $orderId, array $orderItems): array
    {
        $created = [];

        foreach ($orderItems as $item) {
            if (empty($item['is_digital'])) {
                continue;
            }

            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
            $orderItemId = isset($item['id']) ? (int) $item['id'] : 0;

            if ($productId <= 0 || $orderItemId <= 0) {
                continue;
            }

            $files = $this->getFilesForProduct($productId);

            if ($variantId) {
                $variantFiles = $this->getFilesForVariant($variantId);
                $files        = array_merge($files, $variantFiles);
            }

            $unique = [];

            foreach ($files as $file) {
                $fileId = (int) $file['id'];

                if (isset($unique[$fileId])) {
                    continue;
                }

                $unique[$fileId] = true;

                $token = $this->createDownloadToken(
                    $orderId,
                    $orderItemId,
                    $fileId
                );

                $created[] = [
                    'file_id'       => $fileId,
                    'order_item_id' => $orderItemId,
                    'order_id'      => $orderId,
                    'token'         => $token,
                ];
            }
        }

        return $created;
    }

    /**
     * Reset the download count for a specific download record.
     *
     * @param int $downloadId Download record ID
     *
     * @return bool True if reset was successful
     *
     * @since 0.1.13
     */
    public function resetDownloadCount(int $downloadId): bool
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_downloads'))
            ->set($this->db->quoteName('download_count') . ' = 0')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $downloadId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows() > 0;
    }

    /**
     * Get a download record by ID.
     *
     * @param int $downloadId Download record ID
     *
     * @return array<string, mixed>|null Download record or null if not found
     *
     * @since 0.1.13
     */
    public function getDownload(int $downloadId): ?array
    {
        $query = $this->db->getQuery(true)
            ->select([
                'd.*',
                'f.filename',
                'f.storage_path',
                'f.mime_type',
                'f.file_size',
                'f.version',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_downloads', 'd'))
            ->join('INNER', $this->db->quoteName('#__nxp_easycart_digital_files', 'f') . ' ON ' . $this->db->quoteName('f.id') . ' = ' . $this->db->quoteName('d.file_id'))
            ->where($this->db->quoteName('d.id') . ' = :id')
            ->bind(':id', $downloadId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapDownloadRow($row) : null;
    }

    /**
     * Fetch downloads for an order joined with file metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDownloadsForOrder(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                'd.*',
                'f.filename',
                'f.storage_path',
                'f.mime_type',
                'f.file_size',
                'f.version',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_downloads', 'd'))
            ->join('INNER', $this->db->quoteName('#__nxp_easycart_digital_files', 'f') . ' ON ' . $this->db->quoteName('f.id') . ' = ' . $this->db->quoteName('d.file_id'))
            ->where($this->db->quoteName('d.order_id') . ' = :orderId')
            ->order($this->db->quoteName('d.created') . ' ASC')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        return array_map(fn ($row) => $this->mapDownloadRow($row), $rows);
    }

    /**
     * Resolve default expiry datetime string.
     */
    private function defaultExpiryDate(): ?string
    {
        $days = (int) $this->settings->get('digital_download_expiry', 30);

        if ($days <= 0) {
            return null;
        }

        $date = Factory::getDate();
        $date->modify('+' . $days . ' days');

        return $date->toSql();
    }

    private function getDefaultMaxDownloads(): ?int
    {
        $value = $this->settings->get('digital_download_max', 5);
        $max   = (int) $value;

        return $max > 0 ? $max : null;
    }

    private function mapFileRow(object $row): array
    {
        return [
            'id'           => (int) $row->id,
            'product_id'   => (int) $row->product_id,
            'variant_id'   => $row->variant_id !== null ? (int) $row->variant_id : null,
            'filename'     => (string) $row->filename,
            'storage_path' => (string) $row->storage_path,
            'file_size'    => (int) ($row->file_size ?? 0),
            'mime_type'    => $row->mime_type !== null ? (string) $row->mime_type : null,
            'version'      => $row->version !== null ? (string) $row->version : '1.0',
            'created'      => (string) $row->created,
            'modified'     => (string) $row->modified,
        ];
    }

    private function mapDownloadRow(object $row): array
    {
        $download = [
            'id'              => (int) $row->id,
            'order_id'        => (int) $row->order_id,
            'order_item_id'   => (int) $row->order_item_id,
            'file_id'         => (int) $row->file_id,
            'token'           => (string) $row->token,
            'download_count'  => (int) $row->download_count,
            'max_downloads'   => $row->max_downloads !== null ? (int) $row->max_downloads : null,
            'expires_at'      => $row->expires_at !== null ? (string) $row->expires_at : null,
            'last_download_at'=> $row->last_download_at !== null ? (string) $row->last_download_at : null,
            'ip_address'      => $row->ip_address !== null ? (string) $row->ip_address : null,
            'created'         => (string) $row->created,
            'filename'        => $row->filename ?? null,
            'storage_path'    => $row->storage_path ?? null,
            'mime_type'       => $row->mime_type ?? null,
            'file_size'       => $row->file_size ?? null,
            'version'         => $row->version ?? null,
        ];

        $download['url'] = $this->buildDownloadUrl($download['token']);

        return $download;
    }

    private function getDownloadByToken(string $token): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_downloads'))
            ->where($this->db->quoteName('token') . ' = :token')
            ->bind(':token', $token, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapDownloadRow($row) : null;
    }

    private function getFile(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_digital_files'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapFileRow($row) : null;
    }

    private function assertProductExists(int $productId): void
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__nxp_easycart_products'))
            ->where($this->db->quoteName('id') . ' = :pid')
            ->bind(':pid', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query, 0, 1);

        if (!$this->db->loadResult()) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_NOT_FOUND'));
        }
    }

    private function assertVariantBelongsToProduct(int $variantId, int $productId): void
    {
        $query = $this->db->getQuery(true)
            ->select('product_id')
            ->from($this->db->quoteName('#__nxp_easycart_variants'))
            ->where($this->db->quoteName('id') . ' = :vid')
            ->bind(':vid', $variantId, ParameterType::INTEGER);

        $this->db->setQuery($query, 0, 1);
        $foundProductId = (int) $this->db->loadResult();

        if ($foundProductId <= 0 || $foundProductId !== $productId) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRODUCT_MISMATCH'));
        }
    }

    /**
     * Normalise and return the configured storage path (relative to JPATH_ROOT).
     */
    private function getStorageRelativePath(): string
    {
        $configured = (string) $this->settings->get('digital_storage_path', self::DEFAULT_STORAGE);
        $configured = str_replace(['\\', '..'], ['/', ''], trim($configured));

        if ($configured === '') {
            $configured = self::DEFAULT_STORAGE;
        }

        if (str_starts_with($configured, JPATH_ROOT)) {
            $configured = ltrim(substr($configured, strlen(JPATH_ROOT)), '/');
        }

        $configured = ltrim($configured, '/');

        return $configured !== '' ? $configured : self::DEFAULT_STORAGE;
    }

    /**
     * Convert a relative storage path into an absolute filesystem path.
     */
    private function getStorageAbsolutePath(string $relative): string
    {
        $relative = trim($relative, '/');
        $absolute = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR) . '/' . $relative;

        return $absolute;
    }

    private function resolveAbsolutePath(?string $storagePath): ?string
    {
        if ($storagePath === null || $storagePath === '') {
            return null;
        }

        $relative = ltrim(str_replace(['\\', '..'], ['/', ''], $storagePath), '/');

        return $this->getStorageAbsolutePath($relative);
    }

    private function ensureDirectory(string $path): void
    {
        if (!Folder::exists($path)) {
            Folder::create($path);
        }

        $this->writeProtectionFiles($path);
    }

    private function writeProtectionFiles(string $path): void
    {
        $htaccess = rtrim($path, '/\\') . '/.htaccess';
        $index    = rtrim($path, '/\\') . '/index.html';
        $nginxConf = rtrim($path, '/\\') . '/nginx.conf';

        // Apache protection
        if (!is_file($htaccess)) {
            $htaccessContent = <<<'HTACCESS'
# Deny all direct access to digital download files
# Files are served through the secure download controller
Order deny,allow
Deny from all

# Apache 2.4+ syntax
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
HTACCESS;
            File::write($htaccess, $htaccessContent);
        }

        // Nginx protection (must be included in server block manually)
        if (!is_file($nginxConf)) {
            $nginxContent = <<<'NGINX'
# Nginx protection for NXP Easy Cart digital downloads
# Include this file in your nginx server block:
#   include /path/to/media/com_nxpeasycart/downloads/nginx.conf;
#
# Or add this location block directly to your server configuration:

location ~* /media/com_nxpeasycart/downloads/ {
    internal;
    # Alternatively use: deny all;
}
NGINX;
            File::write($nginxConf, $nginxContent);
        }

        // Directory listing protection
        if (!is_file($index)) {
            File::write($index, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1></body></html>');
        }
    }

    private function buildDownloadUrl(string $token): string
    {
        $base = rtrim(Uri::root(), '/');

        if (str_ends_with($base, '/administrator')) {
            $base = substr($base, 0, -strlen('/administrator'));
        }

        return $base . '/index.php?option=com_nxpeasycart&task=download.download&token=' . rawurlencode($token);
    }

    private function getClientIp(): string
    {
        $server = $_SERVER;
        $ip     = '';

        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($server[$key])) {
                $ip = (string) $server[$key];
                break;
            }
        }

        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip    = trim($parts[0]);
        }

        return substr($ip, 0, 45);
    }
}
