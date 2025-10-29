<?php

namespace Nxp\EasyCart\Admin\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Nxp\EasyCart\Admin\Controller\Api\AbstractJsonController;
use RuntimeException;

\defined('_JEXEC') or die;

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
        $task = trim((string) $task);

        if ($task === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_API_TASK_REQUIRED'));
        }

        $segments = explode('.', $task);
        $resource = array_shift($segments);
        $action = $segments ? implode('.', $segments) : 'browse';

        $controller = $this->loadResourceController($resource);

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
        $class = 'Nxp\\EasyCart\\Admin\\Controller\\Api\\' . $resource . 'Controller';

        if (!class_exists($class)) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_API_RESOURCE_NOT_FOUND', $resource));
        }

        return new $class($this->config, $this->factory, $this->app);
    }
}
