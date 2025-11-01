<?php

namespace Nxp\EasyCart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Site\Service\CartPresentationService;
use Nxp\EasyCart\Site\Service\CartSessionService;

/**
 * Cart view model for storefront.
 */
class CartModel extends BaseDatabaseModel
{
    private ?array $cart = null;

    private ?CartPresentationService $presentation = null;

    /**
     * Retrieve the hydrated cart payload for the current visitor.
     */
    public function getCart(): array
    {
        if ($this->cart !== null) {
            return $this->cart;
        }

        $container = Factory::getContainer();
        $cartService = $container->get(CartSessionService::class);
        $cart = $cartService->current();
        $cart = $this->getPresentationService()->hydrate($cart);

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
