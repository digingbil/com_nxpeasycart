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

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Component\Nxpeasycart\Administrator\Service\DigitalFileService;
use RuntimeException;

/**
 * Digital file management API controller.
 *
 * @since 0.1.13
 */
class DigitalfilesController extends AbstractJsonController
{
    public function __construct($config = [], MVCFactoryInterface $factory = null, CMSApplicationInterface $app = null)
    {
        parent::__construct($config, $factory, $app);
    }

    public function execute($task)
    {
        $task = trim(strtolower((string) $task ?: 'list'));

        return match ($task) {
            'list', 'browse' => $this->browse(),
            'upload', 'store', 'create' => $this->upload(),
            'delete', 'remove' => $this->delete(),
            default => $this->respond(['message' => Text::_('JLIB_APPLICATION_ERROR_TASK_NOT_FOUND')], 404),
        };
    }

    protected function browse(): JsonResponse
    {
        $this->assertCan('core.manage');

        $productId = $this->input->getInt('product_id');
        $variantId = $this->input->getInt('variant_id');

        if ($productId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);
        }

        $service = $this->getDigitalFileService();
        $files   = $service->getFilesForProduct($productId);

        if ($variantId > 0) {
            $variantFiles = $service->getFilesForVariant($variantId);
            $files        = $this->mergeFiles($files, $variantFiles);
        }

        return $this->respond(['files' => array_values($files)]);
    }

    protected function upload(): JsonResponse
    {
        $this->assertCan('core.edit');
        $this->assertToken();

        $productId = $this->input->getInt('product_id');
        $variantId = $this->input->getInt('variant_id') ?: null;
        $version   = $this->input->getString('version', '1.0');
        $file      = $this->input->files->get('file');

        if ($productId <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'), 400);
        }

        if (!$file || !\is_array($file)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_UPLOAD_INVALID'), 400);
        }

        $service = $this->getDigitalFileService();
        $stored  = $service->upload($productId, $variantId, $file, $version ?? '1.0');

        return $this->respond(['file' => $stored], 201);
    }

    protected function delete(): JsonResponse
    {
        $this->assertCan('core.delete');
        $this->assertToken();

        $payload = $this->decodePayload();
        $id      = isset($payload['id']) ? (int) $payload['id'] : (isset($payload['file_id']) ? (int) $payload['file_id'] : 0);

        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DIGITAL_FILE_ID_REQUIRED'), 400);
        }

        $this->getDigitalFileService()->delete($id);

        return $this->respond(['deleted' => $id]);
    }

    private function mergeFiles(array $files, array $variantFiles): array
    {
        $lookup = [];

        foreach (array_merge($files, $variantFiles) as $file) {
            if (!isset($file['id'])) {
                continue;
            }

            $lookup[(int) $file['id']] = $file;
        }

        return $lookup;
    }

    private function getDigitalFileService(): DigitalFileService
    {
        $container = Factory::getContainer();

        if (!$container->has(DigitalFileService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                $container->registerServiceProvider(require $providerPath);
            }
        }

        if (!$container->has(DigitalFileService::class)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DIGITAL_FILES_UNAVAILABLE'), 500);
        }

        return $container->get(DigitalFileService::class);
    }
}
