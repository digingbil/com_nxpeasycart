<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Tests\Stubs\StubApplication;
use Tests\Stubs\StubLanguage;
use Tests\Stubs\StubRouter;
use Joomla\Registry\Registry;

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}

if (!\defined('JPATH_SITE')) {
    \define('JPATH_SITE', dirname(__DIR__));
}

if (!\defined('JPATH_ADMINISTRATOR')) {
    \define('JPATH_ADMINISTRATOR', JPATH_SITE . '/administrator');
}

$_SERVER['HTTP_HOST']     = $_SERVER['HTTP_HOST']     ?? 'localhost';
$_SERVER['REQUEST_URI']   = $_SERVER['REQUEST_URI']   ?? '/index.php';
$_SERVER['SCRIPT_NAME']   = $_SERVER['SCRIPT_NAME']   ?? '/index.php';
$_SERVER['PHP_SELF']      = $_SERVER['PHP_SELF']      ?? '/index.php';
$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs/StubLanguage.php';
require_once __DIR__ . '/Stubs/StubRouter.php';
require_once __DIR__ . '/Stubs/StubApplication.php';
require_once __DIR__ . '/Stubs/TrackingQuery.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Joomla\\Component\\Nxpeasycart\\Site\\'            => __DIR__ . '/../components/com_nxpeasycart/src/',
        'Joomla\\Component\\Nxpeasycart\\Administrator\\'   => __DIR__ . '/../administrator/components/com_nxpeasycart/src/Administrator/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require_once $file;
            }

            return;
        }
    }
});

Factory::$language     = new StubLanguage();
Factory::$application  = new StubApplication();

$container = new Container();
$container->set('SiteRouter', function () {
    return new StubRouter();
});
$container->share('config', new Registry(['live_site' => 'http://localhost']));

Factory::$container = $container;
