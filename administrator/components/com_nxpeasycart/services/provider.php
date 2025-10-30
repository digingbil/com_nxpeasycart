<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Session\SessionInterface;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Nxp\EasyCart\Admin\Administrator\Factory\EasyCartMVCFactory;
use Nxp\EasyCart\Admin\Administrator\Extension\NxpEasyCartComponent;
use Nxp\EasyCart\Admin\Administrator\Service\CartService;
use Nxp\EasyCart\Admin\Administrator\Service\OrderService;
use Nxp\EasyCart\Site\Service\CartSessionService;

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
        \JLoader::registerNamespace('Nxp\\EasyCart\\Site', JPATH_SITE . '/components/com_nxpeasycart/src', false, false, 'psr4');

        $namespace = '\\Nxp\\EasyCart\\Admin';

        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));

        $container->set(
            MVCFactoryInterface::class,
            static function (Container $container) use ($namespace): MVCFactoryInterface {
                $factory = new EasyCartMVCFactory($namespace);
                $factory->setFormFactory($container->get(FormFactoryInterface::class));
                $factory->setDispatcher($container->get(DispatcherInterface::class));
                $factory->setDatabase($container->get(DatabaseInterface::class));

                if ($container->has(SiteRouter::class)) {
                    $factory->setSiteRouter($container->get(SiteRouter::class));
                }

                if ($container->has(CacheControllerFactoryInterface::class)) {
                    $factory->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));
                }

                if ($container->has(UserFactoryInterface::class)) {
                    $factory->setUserFactory($container->get(UserFactoryInterface::class));
                }

                if ($container->has(MailerFactoryInterface::class)) {
                    $factory->setMailerFactory($container->get(MailerFactoryInterface::class));
                }

                return $factory;
            }
        );

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

        $container->set(
            CartService::class,
            static fn (Container $container): CartService => new CartService($container->get(DatabaseInterface::class))
        );

        $container->set(
            OrderService::class,
            static fn (Container $container): OrderService => new OrderService($container->get(DatabaseInterface::class))
        );

        $container->set(
            CartSessionService::class,
            static fn (Container $container): CartSessionService => new CartSessionService(
                $container->get(CartService::class),
                $container->get(SessionInterface::class)
            )
        );
    }
};
