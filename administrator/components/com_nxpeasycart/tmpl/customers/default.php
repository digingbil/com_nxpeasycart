<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$app->input->set('appSection', 'customers');

require __DIR__ . '/../app/default.php';
