<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

/**
 * Cart view model for storefront.
 *
 * @since 0.1.5
 */
class CartModel extends BaseDatabaseModel
{
    private ?array $cart = null;

    private ?CartPresentationService $presentation = null;

    /**
     * Retrieve the hydrated cart payload for the current visitor.
     *
     * @since 0.1.5
     */
    public function getCart(): array
    {
        if ($this->cart !== null) {
            return $this->cart;
        }

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

        $cartService = $container->get(CartSessionService::class);
        $cart        = $cartService->current();
        $cart        = $this->getPresentationService()->hydrate($cart);

        $this->cart = $cart;

        return $this->cart;
    }

    private function getPresentationService(): CartPresentationService
    {
        if ($this->presentation instanceof CartPresentationService) {
            return $this->presentation;
        }

        $container = Factory::getContainer();

        if ($container->has(CartPresentationService::class)) {
            $this->presentation = $container->get(CartPresentationService::class);

            return $this->presentation;
        }

        $this->presentation = new CartPresentationService($container->get(DatabaseInterface::class));

        return $this->presentation;
    }
}
