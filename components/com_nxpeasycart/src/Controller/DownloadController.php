<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Administrator\Service\DigitalFileService;

/**
 * Public download controller for digital products.
 *
 * @since 0.1.13
 */
class DownloadController extends BaseController
{
    /**
     * Stream a download by token.
     *
     * @since 0.1.13
     */
    public function download(): void
    {
        $app   = Factory::getApplication();
        $token = $app->input->getString('token', '');
        $container = Factory::getContainer();

        // Bootstrap service provider if not already loaded
        if (!$container->has(DigitalFileService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                $provider = require $providerPath;
                $container->registerServiceProvider($provider);
            }
        }

        if (!$container->has(DigitalFileService::class)) {
            throw new \RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID'), 404);
        }

        /** @var DigitalFileService $downloads */
        $downloads = $container->get(DigitalFileService::class);

        try {
            $downloads->streamDownload($token);
        } catch (\RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID'), 404, $exception);
        }
    }
}
