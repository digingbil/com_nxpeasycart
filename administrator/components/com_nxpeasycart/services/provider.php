<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactoryProvider;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Nxp\EasyCart\Admin\Extension\NxpEasyCartComponent;

return new class implements ServiceProviderInterface {
    /**
     * Registers the component services with the Joomla DI container.
     *
     * @param Container $container The DI container
     *
     * @return void
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactoryProvider('com_nxpeasycart'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('com_nxpeasycart'));

        $container->alias(MVCFactoryInterface::class, 'mvc.factory.com_nxpeasycart');
        $container->set(
            NxpEasyCartComponent::class,
            static function (Container $container): NxpEasyCartComponent {
                return new NxpEasyCartComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class)
                );
            }
        );

        $container->alias(ComponentInterface::class, NxpEasyCartComponent::class);
    }
};
