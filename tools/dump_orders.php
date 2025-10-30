<?php
\define('_JEXEC', 1);

$root = '/var/www/html/j5.loc';
require $root . '/includes/defines.php';
require $root . '/includes/framework.php';

\JLoader::registerNamespace(
    'Nxp\\EasyCart\\Admin',
    __DIR__ . '/../administrator/components/com_nxpeasycart/src',
    false,
    false,
    'psr4'
);

$container = Joomla\CMS\Factory::getContainer();
/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

$orderService = new Nxp\EasyCart\Admin\Administrator\Service\OrderService($db);

print_r($orderService->paginate([], 20, 0));
