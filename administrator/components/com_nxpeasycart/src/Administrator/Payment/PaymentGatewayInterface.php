<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Payment;

\defined('_JEXEC') or die;

/**
 * Contract for payment gateway drivers.
 *
 * @since 0.1.5
 */
interface PaymentGatewayInterface
{
    /**
     * Create a hosted checkout session or intent.
     *
     * @param array<string, mixed> $order       Sanitised order payload
     * @param array<string, mixed> $preferences Gateway-specific preferences (success URL, cancel URL, metadata, etc.)
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    public function createHostedCheckout(array $order, array $preferences = []): array;

    /**
     * Handle an incoming webhook payload.
     *
     * @param string               $payload Raw request body
     * @param array<string, mixed> $context Headers / query params for signature verification
     *
     * @return array<string, mixed> Normalised event details
     *
     * @since 0.1.5
     */
    public function handleWebhook(string $payload, array $context = []): array;
}
