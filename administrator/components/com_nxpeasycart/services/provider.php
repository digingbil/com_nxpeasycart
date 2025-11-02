<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Session\SessionInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Nxp\EasyCart\Admin\Administrator\Extension\NxpEasyCartComponent;
use Nxp\EasyCart\Admin\Administrator\Factory\EasyCartMVCFactory;
use Nxp\EasyCart\Admin\Administrator\Payment\PaymentGatewayManager;
use Nxp\EasyCart\Admin\Administrator\Service\CacheService;
use Nxp\EasyCart\Admin\Administrator\Service\CartService;
use Nxp\EasyCart\Admin\Administrator\Service\GdprService;
use Nxp\EasyCart\Admin\Administrator\Service\MailService;
use Nxp\EasyCart\Admin\Administrator\Service\OrderService;
use Nxp\EasyCart\Admin\Administrator\Service\PaymentGatewayService;
use Nxp\EasyCart\Admin\Administrator\Service\SettingsService;
use Nxp\EasyCart\Site\Service\CartPresentationService;
use Nxp\EasyCart\Site\Service\CartSessionService;

return new class () implements ServiceProviderInterface {
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
            SettingsService::class,
            static fn (Container $container): SettingsService => new SettingsService($container->get(DatabaseInterface::class))
        );

        $container->set(
            PaymentGatewayService::class,
            static fn (Container $container): PaymentGatewayService => new PaymentGatewayService(
                $container->get(SettingsService::class)
            )
        );

        $container->set(
            MailService::class,
            static fn (Container $container): MailService => new MailService(
                $container->get(MailerFactoryInterface::class)->createMailer()
            )
        );

        $container->set(
            PaymentGatewayManager::class,
            static fn (Container $container): PaymentGatewayManager => new PaymentGatewayManager(
                $container->get(PaymentGatewayService::class),
                $container->get(OrderService::class),
                $container->get(MailService::class)
            )
        );

        $container->set(
            CartSessionService::class,
            static fn (Container $container): CartSessionService => new CartSessionService(
                $container->get(CartService::class),
                $container->get(SessionInterface::class)
            )
        );

        $container->set(
            CartPresentationService::class,
            static fn (Container $container): CartPresentationService => new CartPresentationService(
                $container->get(DatabaseInterface::class)
            )
        );

        $container->set(
            CacheService::class,
            static fn (Container $container): CacheService => new CacheService(
                $container->get(CacheControllerFactoryInterface::class)
            )
        );

        $container->set(
            GdprService::class,
            static fn (Container $container): GdprService => new GdprService(
                $container->get(DatabaseInterface::class)
            )
        );
    }
};
