<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use RuntimeException;

/**
 * Webhook receiver for external payment gateways.
 *
 * @since 0.1.5
 */
class WebhookController extends BaseController
{
    public function stripe(): void
    {
        $this->handleGateway('stripe');
    }

    public function paypal(): void
    {
        $this->handleGateway('paypal');
    }

    private function handleGateway(string $gateway): void
    {
        $app = $this->app ?? Factory::getApplication();
        $container = Factory::getContainer();

        // Ensure component services are registered when Joomla skips the provider (e.g. webhooks hitting site directly).
        if (!$container->has(\Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                $provider = require $providerPath;
                $container->registerServiceProvider($provider);
            }
        }

        // Fallback wiring in case the provider is still missing (e.g. webhook boot without component init)
        $this->ensureGatewayServices($container);

        $payload = file_get_contents('php://input') ?: '';
        $context = $this->collectHeaders($app);

        /** @var PaymentGatewayManager $manager */
        $manager = $container->get(PaymentGatewayManager::class);

        try {
            $event    = $manager->handleWebhook($gateway, $payload, $context);
            $response = new JsonResponse(['status' => 'ok', 'event' => $event]);
            $code     = 200;
        } catch (RuntimeException $exception) {
            $response = new JsonResponse(
                ['status' => 'error', 'message' => $exception->getMessage()],
                null,
                true
            );
            $code = 400;
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('Status', (string) $code, true);

        if (\function_exists('http_response_code')) {
            http_response_code($code);
        }

        echo $response;
        $app->close();
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    private function collectHeaders(CMSApplicationInterface $app): array
    {
        if (!method_exists($app, 'getInput')) {
            return [];
        }

        $server = $app->getInput()->server;

        $headers = [
            // Use RAW to avoid stripping characters from signatures (e.g., +, /, =)
            'Stripe-Signature'         => $server->get('HTTP_STRIPE_SIGNATURE', $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', 'RAW'),
            'PayPal-Transmission-Id'   => $server->get('HTTP_PAYPAL_TRANSMISSION_ID', $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '', 'RAW'),
            'PayPal-Transmission-Sig'  => $server->get('HTTP_PAYPAL_TRANSMISSION_SIG', $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '', 'RAW'),
            'PayPal-Cert-Url'          => $server->get('HTTP_PAYPAL_CERT_URL', $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '', 'RAW'),
            'PayPal-Auth-Algo'         => $server->get('HTTP_PAYPAL_AUTH_ALGO', $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '', 'RAW'),
            'PayPal-Transmission-Time' => $server->get('HTTP_PAYPAL_TRANSMISSION_TIME', $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '', 'RAW'),
        ];

        return array_filter($headers, static fn ($value) => $value !== '');
    }

    /**
     * Ensure payment gateway services are available even if the component provider was skipped.
     *
     * @since 0.1.5
     */
    private function ensureGatewayServices(Container $container): void
    {
        if (!$container->has(SettingsService::class) && $container->has(DatabaseInterface::class)) {
            $container->set(
                SettingsService::class,
                static fn (Container $c) => new SettingsService($c->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(PaymentGatewayService::class) && $container->has(SettingsService::class)) {
            $container->set(
                PaymentGatewayService::class,
                static fn (Container $c) => new PaymentGatewayService($c->get(SettingsService::class))
            );
        }

        if (!$container->has(OrderService::class) && $container->has(DatabaseInterface::class)) {
            $container->set(
                OrderService::class,
                static fn (Container $c) => new OrderService($c->get(DatabaseInterface::class))
            );
        }

        if (!$container->has(MailService::class) && $container->has(MailerFactoryInterface::class)) {
            $container->set(
                MailService::class,
                static fn (Container $c) => new MailService($c->get(MailerFactoryInterface::class)->createMailer())
            );
        }

        if (!$container->has(PaymentGatewayManager::class)
            && $container->has(PaymentGatewayService::class)
            && $container->has(OrderService::class)
            && $container->has(MailService::class)
        ) {
            $container->set(
                PaymentGatewayManager::class,
                static fn (Container $c) => new PaymentGatewayManager(
                    $c->get(PaymentGatewayService::class),
                    $c->get(OrderService::class),
                    $c->get(MailService::class)
                )
            );
        }
    }

}
