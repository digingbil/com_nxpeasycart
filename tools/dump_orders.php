<?php
\define('_JEXEC', 1);

$root = '/var/www/html/j5.loc';
require $root . '/includes/defines.php';
require $root . '/includes/framework.php';

foreach (
    [
        __DIR__ . '/../administrator/components/com_nxpeasycart/vendor/autoload.php',
        __DIR__ . '/../components/com_nxpeasycart/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
    ] as $autoload
) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

\JLoader::registerNamespace(
    'Joomla\\Component\\Nxpeasycart\\Administrator',
    __DIR__ . '/../administrator/components/com_nxpeasycart/src/Administrator',
    false,
    false,
    'psr4'
);

$container = Joomla\CMS\Factory::getContainer();
/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

$orderService = new Joomla\Component\Nxpeasycart\Administrator\Service\OrderService($db);

print_r($orderService->paginate([], 20, 0));
