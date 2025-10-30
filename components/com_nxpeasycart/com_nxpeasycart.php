<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\SessionInterface;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Service\CartService;
use Nxp\EasyCart\Site\Service\CartSessionService;

\JLoader::registerNamespace('Nxp\\EasyCart\\Site', __DIR__ . '/src', false, false, 'psr4');
\JLoader::registerNamespace('Nxp\\EasyCart\\Admin', JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/src', false, false, 'psr4');

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

$controller = BaseController::getInstance(
    'Nxpeasycart',
    [
        'namespace' => 'Nxp\\EasyCart\\Site\\Controller',
    ]
);

$controller->execute(Factory::getApplication()->input->getCmd('task', 'display'));
$controller->redirect();
