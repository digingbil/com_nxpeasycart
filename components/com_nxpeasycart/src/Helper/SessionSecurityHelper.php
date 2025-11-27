<?php

/**
 * @package     NXP Easy Cart
 * @subpackage  Site
 * @copyright   Copyright (C) NXP. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Component\Nxpeasycart\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Session security helper for post-checkout protection.
 *
 * Provides session regeneration functionality to prevent session fixation
 * attacks after checkout completion. This helper should be called on
 * order confirmation pages to ensure the session is regenerated when
 * the user returns from external payment gateways (Stripe, PayPal).
 *
 * @since  1.0.0
 */
class SessionSecurityHelper
{
    /**
     * Session flag indicating checkout was completed.
     *
     * @var string
     */
    private const FLAG_CHECKOUT_COMPLETED = 'nxp_ec_checkout_completed';

    /**
     * Session flag indicating session was already regenerated.
     *
     * @var string
     */
    private const FLAG_SESSION_REGENERATED = 'nxp_ec_session_regenerated';

    /**
     * Check if session should be regenerated after checkout and do so if needed.
     *
     * This method should be called on order confirmation/success pages.
     * It checks for a flag set during checkout and regenerates the session
     * ID if the session hasn't already been regenerated.
     *
     * This protects against session fixation attacks where an attacker
     * might have pre-set a session ID before the user completed checkout.
     *
     * @return bool True if session was regenerated, false otherwise
     *
     * @since  1.0.0
     */
    public static function regenerateIfNeeded(): bool
    {
        try {
            $app = Factory::getApplication();
            $session = $app->getSession();

            // Check if a checkout was recently completed
            $checkoutCompleted = (bool) $session->get(self::FLAG_CHECKOUT_COMPLETED, false);
            $alreadyRegenerated = (bool) $session->get(self::FLAG_SESSION_REGENERATED, false);

            // If checkout completed but session not yet regenerated, do it now
            if ($checkoutCompleted && !$alreadyRegenerated) {
                return self::performRegeneration($session);
            }

            // Also regenerate on first visit to order confirmation page
            // This catches cases where user returns from external gateway
            // and the payment controller's session regeneration didn't fire
            // (e.g., webhook processed the order before user returned)
            if (self::isFirstOrderPageVisit($session)) {
                return self::performRegeneration($session);
            }

            return false;
        } catch (\Throwable $exception) {
            // Non-fatal: log but don't break the page
            return false;
        }
    }

    /**
     * Mark checkout as completed.
     *
     * This should be called when an order is successfully created,
     * before the user is redirected to the order confirmation page.
     *
     * @return void
     *
     * @since  1.0.0
     */
    public static function markCheckoutCompleted(): void
    {
        try {
            $session = Factory::getApplication()->getSession();
            $session->set(self::FLAG_CHECKOUT_COMPLETED, true);
            $session->set(self::FLAG_SESSION_REGENERATED, false);
        } catch (\Throwable $exception) {
            // Non-fatal
        }
    }

    /**
     * Clear all checkout-related session flags.
     *
     * @return void
     *
     * @since  1.0.0
     */
    public static function clearFlags(): void
    {
        try {
            $session = Factory::getApplication()->getSession();
            $session->clear(self::FLAG_CHECKOUT_COMPLETED);
            $session->clear(self::FLAG_SESSION_REGENERATED);
            $session->clear('nxp_ec_order_page_visited');
        } catch (\Throwable $exception) {
            // Non-fatal
        }
    }

    /**
     * Perform the actual session regeneration.
     *
     * @param  \Joomla\Session\SessionInterface  $session  The session object
     *
     * @return bool True if regeneration succeeded
     *
     * @since  1.0.0
     */
    private static function performRegeneration($session): bool
    {
        try {
            // Mark as regenerated first to prevent loops on failure
            $session->set(self::FLAG_SESSION_REGENERATED, true);
            $session->clear(self::FLAG_CHECKOUT_COMPLETED);

            // Regenerate session ID while preserving session data
            if (method_exists($session, 'regenerate')) {
                $session->regenerate(true);
            } elseif (\function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }

            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /**
     * Check if this is the first visit to the order page in this session.
     *
     * @param  \Joomla\Session\SessionInterface  $session  The session object
     *
     * @return bool True if first visit
     *
     * @since  1.0.0
     */
    private static function isFirstOrderPageVisit($session): bool
    {
        $visited = (bool) $session->get('nxp_ec_order_page_visited', false);

        if (!$visited) {
            $session->set('nxp_ec_order_page_visited', true);
            return true;
        }

        return false;
    }
}
