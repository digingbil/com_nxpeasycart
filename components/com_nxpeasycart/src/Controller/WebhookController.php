<?php

namespace Nxp\EasyCart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Nxp\EasyCart\Admin\Administrator\Payment\PaymentGatewayManager;
use RuntimeException;

/**
 * Webhook receiver for external payment gateways.
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

        $payload = file_get_contents('php://input') ?: '';
        $context = $this->collectHeaders($app);

        /** @var PaymentGatewayManager $manager */
        $manager = Factory::getContainer()->get(PaymentGatewayManager::class);

        try {
            $event = $manager->handleWebhook($gateway, $payload, $context);
            $response = new JsonResponse(['status' => 'ok', 'event' => $event]);
        } catch (RuntimeException $exception) {
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 400);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setBody($response->toString());
        $app->sendResponse();
        $app->close();
    }

    /**
     * @return array<string, mixed>
     */
    private function collectHeaders(CMSApplicationInterface $app): array
    {
        if (!method_exists($app, 'getInput')) {
            return [];
        }

        $server = $app->getInput()->server;

        $headers = [
            'Stripe-Signature' => $server->getString('HTTP_STRIPE_SIGNATURE', ''),
            'PayPal-Transmission-Id' => $server->getString('HTTP_PAYPAL_TRANSMISSION_ID', ''),
            'PayPal-Transmission-Sig' => $server->getString('HTTP_PAYPAL_TRANSMISSION_SIG', ''),
            'PayPal-Cert-Url' => $server->getString('HTTP_PAYPAL_CERT_URL', ''),
            'PayPal-Auth-Algo' => $server->getString('HTTP_PAYPAL_AUTH_ALGO', ''),
            'PayPal-Transmission-Time' => $server->getString('HTTP_PAYPAL_TRANSMISSION_TIME', ''),
        ];

        return array_filter($headers, static fn ($value) => $value !== '');
    }
}
