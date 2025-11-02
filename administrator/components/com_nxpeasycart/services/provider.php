<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory as ComponentRouterFactory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\SiteRouter;
use Joomla\Session\SessionInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Component\Nxpeasycart\Administrator\Extension\NxpEasyCartComponent;
use Joomla\Component\Nxpeasycart\Administrator\Factory\EasyCartMVCFactory;
use Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager;
use Joomla\Component\Nxpeasycart\Administrator\Service\CacheService;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Component\Nxpeasycart\Administrator\Service\GdprService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Site\Service\Router as EasyCartRouter;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

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
        \JLoader::registerNamespace('Joomla\\Component\\Nxpeasycart\\Administrator', __DIR__ . '/../src/Administrator', false, false, 'psr4');
        \JLoader::registerNamespace('Joomla\\Component\\Nxpeasycart\\Site', JPATH_SITE . '/components/com_nxpeasycart/src', false, false, 'psr4');

        $namespace = '\\Joomla\\Component\\Nxpeasycart\\Administrator';

        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));
        $container->registerServiceProvider(new ComponentRouterFactory('\\Joomla\\Component\\Nxpeasycart'));

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
                $component = new NxpEasyCartComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class)
                );

                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                if ($container->has(RouterFactoryInterface::class)) {
                    $component->setRouterFactory($container->get(RouterFactoryInterface::class));
                }

                return $component;
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

        if (!$container->has(SiteRouter::class)) {
            $container->set(
                SiteRouter::class,
                static function (Container $container): EasyCartRouter {
                    $app = $container->has(CMSApplicationInterface::class)
                        ? $container->get(CMSApplicationInterface::class)
                        : JoomlaFactory::getApplication();

                    if (method_exists($app, 'isClient') && !$app->isClient('site')) {
                        $app = JoomlaFactory::getApplication('site');
                    }

                    return new EasyCartRouter($app, $app->getMenu());
                }
            );
        }

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
