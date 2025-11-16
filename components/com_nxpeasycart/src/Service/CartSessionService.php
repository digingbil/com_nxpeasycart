<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\SessionInterface;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;

/**
 * Session-aware cart accessor for the storefront.
 */
class CartSessionService
{
    private const SESSION_KEY = 'com_nxpeasycart.cart_id';
    private const SESSION_HARDENED = 'com_nxpeasycart.session_hardened';

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
        $this->carts   = $carts;
        $this->session = $session;
    }

    /**
     * Ensure a cart exists for the current visitor and return it.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $this->regenerateOnAuthentication();

        $cartId = (string) $this->session->get(self::SESSION_KEY, '');
        $cart   = $cartId !== '' ? $this->carts->load($cartId) : null;

        $sessionId = $this->session->getId();

        if (!$cart) {
            $cart = $this->carts->persist([
                'id'         => $cartId !== '' ? $cartId : null,
                'session_id' => $sessionId,
                'data'       => [
                    'currency' => ConfigHelper::getBaseCurrency(),
                    'items'    => [],
                ],
            ]);
        } elseif (($cart['session_id'] ?? null) !== $sessionId) {
            $cart = $this->carts->persist([
                'id'         => $cart['id'],
                'session_id' => $sessionId,
                'user_id'    => $cart['user_id'] ?? null,
                'data'       => $cart['data']    ?? [],
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

    /**
     * Regenerate the session ID once a user is authenticated to prevent fixation.
     */
    private function regenerateOnAuthentication(): void
    {
        if ($this->session->get(self::SESSION_HARDENED, false)) {
            return;
        }

        try {
            $user = Factory::getApplication()->getIdentity();
        } catch (\Throwable $exception) {
            return;
        }

        if (!$user || $user->guest) {
            return;
        }

        try {
            if ($this->session->fork()) {
                $this->session->set(self::SESSION_HARDENED, true);
            }
        } catch (\Throwable $exception) {
            // Non-fatal: keep the current session but avoid blocking cart access.
        }
    }
}
