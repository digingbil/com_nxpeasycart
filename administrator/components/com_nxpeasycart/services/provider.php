<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Nxp\EasyCart\Admin\Administrator\Extension\NxpEasyCartComponent;

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
        \JLoader::registerNamespace('Nxp\\EasyCart\\Admin', __DIR__ . '/../src', false, false, 'psr4');

        $container->registerServiceProvider(new MVCFactory('\\Nxp\\EasyCart\\Admin'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Nxp\\EasyCart\\Admin'));

        // Expose the MVC factory under a component-specific alias for legacy lookups.
        $container->alias('mvc.factory.com_nxpeasycart', MVCFactoryInterface::class);

        $container->set(
            ComponentInterface::class,
            static function (Container $container): NxpEasyCartComponent {
                return new NxpEasyCartComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class)
                );
            }
        );
    }
};
