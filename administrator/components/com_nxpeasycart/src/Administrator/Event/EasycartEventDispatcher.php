<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Event;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\DispatcherInterface;

/**
 * Event dispatcher helper for NxpEasycart component events.
 *
 * Provides a consistent way to dispatch plugin events throughout the component.
 * Third-party plugins can subscribe to these events to extend functionality.
 *
 * Available events:
 * - onNxpEasycartBeforeCheckout: Fired before order creation during checkout
 * - onNxpEasycartAfterOrderCreate: Fired after a new order is saved
 * - onNxpEasycartAfterOrderStateChange: Fired after an order state transition
 * - onNxpEasycartAfterPaymentComplete: Fired after successful payment confirmation
 */
class EasycartEventDispatcher
{
    private static ?DispatcherInterface $dispatcher = null;

    /**
     * Get the Joomla event dispatcher.
     */
    private static function getDispatcher(): ?DispatcherInterface
    {
        if (self::$dispatcher !== null) {
            return self::$dispatcher;
        }

        try {
            $app = Factory::getApplication();

            if (method_exists($app, 'getDispatcher')) {
                self::$dispatcher = $app->getDispatcher();
            }
        } catch (\Throwable $exception) {
            // Application not available, events will be skipped
        }

        return self::$dispatcher;
    }

    /**
     * Dispatch an event to all subscribed plugins.
     *
     * @param string $eventName The event name (e.g., 'onNxpEasycartAfterOrderCreate')
     * @param array<string, mixed> $arguments Event arguments passed to subscribers
     * @return AbstractEvent|null The dispatched event or null if dispatch failed
     */
    public static function dispatch(string $eventName, array $arguments = []): ?AbstractEvent
    {
        $dispatcher = self::getDispatcher();

        if (!$dispatcher) {
            return null;
        }

        // Ensure system plugins are imported for event handling
        PluginHelper::importPlugin('system');

        try {
            $event = new class($eventName, $arguments) extends AbstractEvent {
                public function __construct(string $name, array $arguments = [])
                {
                    parent::__construct($name, $arguments);
                }
            };

            $dispatcher->dispatch($eventName, $event);

            return $event;
        } catch (\Throwable $exception) {
            // Swallow dispatch failures to avoid blocking core functionality
            return null;
        }
    }

    /**
     * Fire before checkout processing begins.
     *
     * Allows plugins to validate, modify cart data, or block checkout.
     * To block checkout, throw a RuntimeException with an error message.
     *
     * @param array<string, mixed> $cart The cart data being checked out
     * @param array<string, mixed> $payload The checkout request payload
     * @param string $gateway The selected payment gateway
     * @return AbstractEvent|null
     */
    public static function beforeCheckout(array $cart, array $payload, string $gateway): ?AbstractEvent
    {
        return self::dispatch('onNxpEasycartBeforeCheckout', [
            'cart'    => $cart,
            'payload' => $payload,
            'gateway' => $gateway,
        ]);
    }

    /**
     * Fire after a new order is created.
     *
     * Allows plugins to perform post-order actions like:
     * - Sending notifications to external systems
     * - Triggering CRM updates
     * - Custom analytics tracking
     *
     * @param array<string, mixed> $order The newly created order
     * @param string|null $gateway The payment gateway used (null for offline)
     * @return AbstractEvent|null
     */
    public static function afterOrderCreate(array $order, ?string $gateway = null): ?AbstractEvent
    {
        return self::dispatch('onNxpEasycartAfterOrderCreate', [
            'order'   => $order,
            'gateway' => $gateway,
        ]);
    }

    /**
     * Fire after an order state transition.
     *
     * Allows plugins to react to order state changes like:
     * - pending -> paid (payment confirmed)
     * - paid -> fulfilled (order shipped)
     * - paid -> refunded (refund processed)
     *
     * @param array<string, mixed> $order The order after state change
     * @param string $fromState The previous state
     * @param string $toState The new state
     * @param int|null $actorId The user ID who triggered the change (null for system)
     * @return AbstractEvent|null
     */
    public static function afterOrderStateChange(
        array $order,
        string $fromState,
        string $toState,
        ?int $actorId = null
    ): ?AbstractEvent {
        return self::dispatch('onNxpEasycartAfterOrderStateChange', [
            'order'     => $order,
            'fromState' => $fromState,
            'toState'   => $toState,
            'actorId'   => $actorId,
        ]);
    }

    /**
     * Fire after successful payment completion.
     *
     * This event is triggered when a payment is confirmed as successful,
     * typically via webhook from Stripe/PayPal or after manual confirmation.
     *
     * Allows plugins to:
     * - Send payment confirmation emails
     * - Trigger fulfillment workflows
     * - Update external inventory systems
     * - Record analytics
     *
     * @param array<string, mixed> $order The paid order
     * @param array<string, mixed> $transaction The transaction details
     * @param string $gateway The payment gateway that processed the payment
     * @return AbstractEvent|null
     */
    public static function afterPaymentComplete(
        array $order,
        array $transaction,
        string $gateway
    ): ?AbstractEvent {
        return self::dispatch('onNxpEasycartAfterPaymentComplete', [
            'order'       => $order,
            'transaction' => $transaction,
            'gateway'     => $gateway,
        ]);
    }

    /**
     * Reset the cached dispatcher (useful for testing).
     */
    public static function reset(): void
    {
        self::$dispatcher = null;
    }
}
