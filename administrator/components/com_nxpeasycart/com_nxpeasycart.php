<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

// Instantiate the controller using the component's namespace.
$controller = BaseController::getInstance(
    'Nxpeasycart',
    [
        'namespace' => 'Nxp\\EasyCart\\Admin\\Controller',
    ]
);

// Execute the requested task.
$controller->execute(Factory::getApplication()->input->getCmd('task', 'display'));

// Redirect if required by the controller.
$controller->redirect();
