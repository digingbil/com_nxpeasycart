<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Session\SessionInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

\JLoader::registerNamespace('Joomla\\Component\\Nxpeasycart\\Site', __DIR__ . '/src', false, false, 'psr4');
\JLoader::registerNamespace('Joomla\\Component\\Nxpeasycart\\Administrator', JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/src/Administrator', false, false, 'psr4');

$container = Factory::getContainer();

if (!$container->has(CartService::class)) {
    $container->set(
        CartService::class,
        static fn ($container): CartService => new CartService($container->get(DatabaseInterface::class))
    );
}

if (!$container->has(CartSessionService::class)) {
    $container->set(
        CartSessionService::class,
        static fn ($container): CartSessionService => new CartSessionService(
            $container->get(CartService::class),
            $container->get(SessionInterface::class)
        )
    );
}

if (!$container->has(CartPresentationService::class)) {
    $container->set(
        CartPresentationService::class,
        static fn ($container): CartPresentationService => new CartPresentationService(
            $container->get(DatabaseInterface::class)
        )
    );
}

$controller = BaseController::getInstance(
    'Nxpeasycart',
    [
    'namespace' => 'Joomla\\Component\\Nxpeasycart\\Site\\Controller',
    ]
);

$controller->execute(Factory::getApplication()->input->getCmd('task', 'display'));
$controller->redirect();
