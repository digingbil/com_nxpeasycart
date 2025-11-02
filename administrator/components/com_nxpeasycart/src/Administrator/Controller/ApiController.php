<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Administrator\Controller\Api\AbstractJsonController;
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

    private function debug(string $message): void
    {
        try {
            $logFile = defined('JPATH_LOGS')
                ? rtrim(JPATH_LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'com_nxpeasycart-api.log'
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'com_nxpeasycart-api.log';

            $line = sprintf("[%s] %s\n", gmdate('c'), $message);
            file_put_contents($logFile, $line, FILE_APPEND);
        } catch (\Throwable $exception) {
            // Swallow logging errors.
        }
    }
}
