<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$app->input->set('appSection', 'shipping');

require __DIR__ . '/../app/default.php';
