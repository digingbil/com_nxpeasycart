<?php

namespace Nxp\EasyCart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\SessionInterface;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;
use Nxp\EasyCart\Admin\Administrator\Service\CartService;

/**
 * Session-aware cart accessor for the storefront.
 */
class CartSessionService
{
    private const SESSION_KEY = 'com_nxpeasycart.cart_id';

    /**
     * @var CartService
     */
    private CartService $carts;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * CartSessionService constructor.
     *
     * @param CartService       $carts   Persistent cart service
     * @param SessionInterface  $session Joomla session
     */
    public function __construct(CartService $carts, SessionInterface $session)
    {
        $this->carts = $carts;
        $this->session = $session;
    }

    /**
     * Ensure a cart exists for the current visitor and return it.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $cartId = (string) $this->session->get(self::SESSION_KEY, '');
        $cart = $cartId !== '' ? $this->carts->load($cartId) : null;

        $sessionId = $this->session->getId();

        if (!$cart) {
            $cart = $this->carts->persist([
                'id' => $cartId !== '' ? $cartId : null,
                'session_id' => $sessionId,
                'data' => [
                    'currency' => ConfigHelper::getBaseCurrency(),
                    'items' => [],
                ],
            ]);
        } elseif (($cart['session_id'] ?? null) !== $sessionId) {
            $cart = $this->carts->persist([
                'id' => $cart['id'],
                'session_id' => $sessionId,
                'user_id' => $cart['user_id'] ?? null,
                'data' => $cart['data'] ?? [],
            ]);
        }

        $this->session->set(self::SESSION_KEY, $cart['id']);

        return $cart;
    }

    /**
     * Bootstrap the cart and expose it via the application input for views.
     */
    public function attachToApplication(): array
    {
        $cart = $this->current();

        $input = Factory::getApplication()->getInput();
        $input->set('com_nxpeasycart.cart', $cart);

        return $cart;
    }
}

