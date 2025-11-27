<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Administrator\Controller\Api\AbstractJsonController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
use RuntimeException;

/**
 * Router controller that proxies API tasks to resource controllers.
 */
class ApiController extends BaseController
{
    /**
     * Execute the requested task.
     *
     * @param string $task The API task string ("resource.action").
     *
     * @return mixed
     */
    public function execute($task)
    {
        $rawTaskParam = $_GET['task'] ?? $_POST['task'] ?? $this->input->get('task', '', 'RAW');

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

    private function debug(string $message): void
    {
        try {
            // Prefer Joomla configured log path; fall back to system temp dir if missing
            $logPath = '';

            try {
                if (isset($this->app) && method_exists($this->app, 'get')) {
                    $logPath = (string) ($this->app->get('log_path') ?? '');
                }
            } catch (\Throwable $e) {
                // Ignore and use fallback
            }

            // Secondary fallback: read from global config if available
            if ($logPath === '') {
                try {
                    $app = \Joomla\CMS\Factory::getApplication();
                    if ($app) {
                        $config = $app->getConfig();
                        if ($config) {
                            $logPath = (string) ($config->get('log_path') ?? '');
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore and use final fallback
                }
            }

            if ($logPath === '' || !is_dir($logPath)) {
                $logPath = sys_get_temp_dir();
            }

            $logFile = rtrim($logPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'com_nxpeasycart-api.log';

            $line = sprintf("[%s] %s\n", gmdate('c'), $message);
            file_put_contents($logFile, $line, FILE_APPEND);
        } catch (\Throwable $exception) {
            // Swallow logging errors.
        }
    }
}
