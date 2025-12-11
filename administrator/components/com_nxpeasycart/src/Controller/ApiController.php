<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Administrator\Controller\Api\AbstractJsonController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use RuntimeException;

/**
 * Router controller that proxies API tasks to resource controllers.
 *
 * @since 0.1.5
 */
class ApiController extends BaseController
{
    /**
     * Execute the requested task.
     *
     * @param string $task The API task string ("resource.action").
     *
     * @return mixed
     *
     * @since 0.1.5
     */
    public function execute($task): mixed {

        // Use RAW filter to preserve dots in task parameter (e.g., api.products.store).
        // Read from GET/POST inputs directly to avoid the dispatcher truncating multi-dot tasks
        // (e.g., api.products.store -> products) when it splits controller/task.
        $rawTaskParam = $this->input->get->get('task', '', 'RAW');

        if ($rawTaskParam === '') {
            $rawTaskParam = $this->input->post->get('task', '', 'RAW');
        }

        if ($rawTaskParam === '') {
            $rawTaskParam = $this->input->get('task', '', 'RAW');
        }

        if (!\is_string($rawTaskParam)) {
            $rawTaskParam = '';
        }

        $rawTaskParam = preg_replace('/[^A-Za-z0-9._-]/', '', $rawTaskParam);
        $task         = trim($rawTaskParam !== '' ? $rawTaskParam : (string) $task);

        if ($task === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_API_TASK_REQUIRED'));
        }

        $segments = explode('.', $task);
        $resource = array_shift($segments);
        $action   = $segments ? implode('.', $segments) : 'browse';

        if (strcasecmp($resource, 'api') === 0 && !empty($segments)) {
            $resource = array_shift($segments);
            $action   = $segments ? implode('.', $segments) : 'browse';
        }

        $this->debug(sprintf('Dispatch request task=%s => resource=%s action=%s', $task, $resource, $action));

        $controller = $this->loadResourceController($resource);

        $this->debug(sprintf('Loaded controller %s', get_class($controller)));

        $this->assertTokenForUnsafeRequests();

        return $controller->execute($action);
    }

    /**
     * Instantiate the controller for an API resource.
     *
     * @param string $resource Resource identifier
     *
     * @return AbstractJsonController
     *
     * @since 0.1.5
     */
    private function loadResourceController(string $resource): AbstractJsonController
    {
        $resource = ucfirst($resource);
        $class    = __NAMESPACE__ . '\\Api\\' . $resource . 'Controller';

        if (!class_exists($class)) {
            $path = __DIR__ . '/Api/' . $resource . 'Controller.php';

            if (is_file($path)) {
                require_once $path;
                $this->debug(sprintf('Included controller file %s', $path));
            }
        }

        if (!class_exists($class)) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_API_RESOURCE_NOT_FOUND', $resource));
        }

        $config = $this->config ?? [];

        if (!\is_array($config)) {
            $config = [];
        }

        return new $class($config, $this->factory, $this->app, $this->input);
    }

    /**
     * Enforce CSRF protection for non-idempotent HTTP verbs so stateful endpoints
     * cannot be hit without a valid token.
     *
     * @return void
     *
     * @since 0.1.5
     */
    private function assertTokenForUnsafeRequests(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!Session::checkToken('request')) {
                throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }
        }
    }

    /**
     * Logs a debug message to the system log. If an error occurs during the logging process, it will be silently ignored.
     *
     * @param   string  $message  The debug message to be logged.
     *
     * @return void
     *
     * @since 0.1.5
     */
    private function debug(string $message): void
    {
        try {
            Log::add($message, Log::DEBUG, 'com_nxpeasycart.api');
        } catch (\Throwable $exception) {
            // Swallow logging errors
        }
    }
}
