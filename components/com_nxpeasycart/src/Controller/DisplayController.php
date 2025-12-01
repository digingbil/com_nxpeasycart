<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

/**
 * Default site controller for routing storefront views.
 *
 * @since 0.1.5
 */
class DisplayController extends BaseController
{
    /**
     * Default view name.
     *
     * @var string
     *
     * @since 0.1.5
     */
    protected $default_view = 'category';

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function display($cachable = false, $urlparams = [])
    {
        $container = Factory::getContainer();

        if (!$container->has(CartSessionService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                $provider = require $providerPath;
                $container->registerServiceProvider($provider);
            }
        }

        if (!$container->has(\Joomla\Session\SessionInterface::class)) {
            $container->set(
                \Joomla\Session\SessionInterface::class,
                Factory::getApplication()->getSession()
            );
        }

        try {
            $container
                ->get(CartSessionService::class)
                ->attachToApplication();
        } catch (\Throwable $exception) {
            // Cart bootstrap failures should not block the storefront; swallow and continue.
        }

        return parent::display($cachable, $urlparams);
    }
}
