<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Session\SessionInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;

/**
 * Session-aware cart accessor for the storefront.
 *
 * @since 0.1.5
 */
class CartSessionService
{
    private const SESSION_KEY = 'com_nxpeasycart.cart_id';
    private const SESSION_HARDENED = 'com_nxpeasycart.session_hardened';

    /**
     * @var CartService
     *
     * @since 0.1.5
     */
    private CartService $carts;

    /**
     * @var SessionInterface|Session
     *
     * @since 0.1.5
     */
    private $session;

    /**
     * CartSessionService constructor.
     *
     * @param CartService               $carts   Persistent cart service
     * @param SessionInterface|Session  $session Joomla session
     *
     * @since 0.1.5
     */
    public function __construct(CartService $carts, $session)
    {
        $this->carts   = $carts;
        $this->session = $session;
    }

    /**
     * Ensure a cart exists for the current visitor and return it.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    public function current(): array
    {
        $this->ensureAutoload();
        $this->regenerateOnAuthentication();

        $app = Factory::getApplication();

        $sessionId = $this->session->getId();
        $cookieCartId = $app->input->cookie->getString('nxp_cart_id', '');
        $sessionCartId = (string) $this->session->get(self::SESSION_KEY, '');

        // Prefer the cookie-stored cart ID to keep carts across session changes.
        $cart = $cookieCartId !== '' ? $this->carts->load($cookieCartId) : null;
        $cartId = $cart['id'] ?? $cookieCartId;

        if (!$cart && $sessionCartId !== '') {
            $cart = $this->carts->load($sessionCartId);
            $cartId = $cart['id'] ?? $sessionCartId;
        }

        // Fallback: reuse a cart linked to this session.
        if (!$cart) {
            $cart = $this->carts->loadBySession($sessionId);
            $cartId = $cart['id'] ?? '';
        }

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
        $app->input->cookie->set('nxp_cart_id', $cart['id'], time() + 60 * 60 * 24 * 30, '/');

        return $cart;
    }

    /**
     * Ensure vendor autoload is available when DI bootstrapping did not load it.
     *
     * @since 0.1.5
     */
    private function ensureAutoload(): void
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class, false)) {
            return;
        }

        $runningInsideJoomla = \defined('JPATH_LIBRARIES');

        $candidates = [];

        if (\defined('JPATH_SITE')) {
            $candidates[] = JPATH_SITE . '/components/com_nxpeasycart/vendor/autoload.php';
        }

        if (\defined('JPATH_ADMINISTRATOR')) {
            $candidates[] = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php';
        }

        if (\defined('JPATH_ROOT')) {
            $candidates[] = JPATH_ROOT . '/vendor/autoload.php';
        }

        // Only use the repo-root vendor during CLI/dev (when not inside Joomla).
        if (!$runningInsideJoomla) {
            $candidates[] = dirname(__DIR__, 4) . '/vendor/autoload.php';
        }

        foreach (array_unique($candidates) as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;

                if (class_exists(\Ramsey\Uuid\Uuid::class, false)) {
                    break;
                }
            }
        }
    }

    /**
     * Bootstrap the cart and expose it via the application input for views.
     *
     * @since 0.1.5
     */
    public function attachToApplication(): array
    {
        $cart = $this->current();

        $input = Factory::getApplication()->getInput();
        $input->set('com_nxpeasycart.cart', $cart);

        return $cart;
    }

    /**
     * Clear the current cart for this session/user.
     *
     * @since 0.1.5
     */
    public function clear(): void
    {
        try {
            $cart = $this->current();

            $this->carts->persist([
                'id'         => $cart['id']         ?? null,
                'session_id' => $this->session->getId(),
                'user_id'    => $cart['user_id']    ?? null,
                'data'       => [
                    'currency' => ConfigHelper::getBaseCurrency(),
                    'items'    => [],
                ],
            ]);
        } catch (\Throwable $exception) {
            // Non-fatal: leave cart untouched if clear fails.
        }
    }

    /**
     * Regenerate the session ID once a user is authenticated to prevent fixation.
     *
     * @since 0.1.5
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
