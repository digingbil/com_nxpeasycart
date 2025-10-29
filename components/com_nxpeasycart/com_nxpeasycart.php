<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

$controller = BaseController::getInstance(
    'Nxpeasycart',
    [
        'namespace' => 'Nxp\\EasyCart\\Site\\Controller',
    ]
);

$controller->execute(Factory::getApplication()->input->getCmd('task', 'display'));
$controller->redirect();
