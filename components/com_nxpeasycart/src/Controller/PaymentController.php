<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use RuntimeException;

/**
 * Front-end controller for initiating hosted checkout flows.
 */
class PaymentController extends BaseController
{
    public function checkout(): void
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $payload = $this->decodePayload($input->json->getRaw() ?? '');
        $gateway = isset($payload['gateway']) ? strtolower((string) $payload['gateway']) : 'stripe';

        if (!in_array($gateway, ['stripe', 'paypal'], true)) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_PAYMENT_GATEWAY_INVALID')], 400);
        }

        $container   = Factory::getContainer();
        $cartService = $container->get(CartSessionService::class);
        $cart        = $cartService->current();

        if (empty($cart['items'])) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_CART_EMPTY')], 400);
        }

        /** @var OrderService $orders */
        $orders       = $container->get(OrderService::class);
        $orderPayload = $this->buildOrderPayload($cart, $payload);
        $order        = $orders->create($orderPayload);

        /** @var PaymentGatewayManager $manager */
        $manager = $container->get(PaymentGatewayManager::class);

        $preferences = [
            'success_url' => $payload['success_url'] ?? ($this->buildOrderUrl($order['order_no']) . '&status=success'),
            'cancel_url'  => $payload['cancel_url']  ?? (Uri::root() . 'index.php?option=com_nxpeasycart&view=cart'),
        ];

        $checkout = $manager->createHostedCheckout($gateway, [
            'id'       => $order['id'],
            'order_no' => $order['order_no'],
            'currency' => $order['currency'],
            'email'    => $order['email'],
            'items'    => $order['items'],
            'summary'  => [
                'total_cents' => $order['total_cents'],
            ],
        ], $preferences);

        $this->respond([
            'order' => [
                'id'       => $order['id'],
                'order_no' => $order['order_no'],
            ],
            'checkout' => $checkout,
        ]);
    }

    private function decodePayload(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_INVALID_JSON'), 400);
        }

        return (array) $decoded;
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $payload
     */
    private function buildOrderPayload(array $cart, array $payload): array
    {
        $items = [];

        foreach ($cart['items'] as $item) {
            $items[] = [
                'sku'              => $item['sku']   ?? '',
                'title'            => $item['title'] ?? '',
                'qty'              => (int) ($item['qty'] ?? 1),
                'unit_price_cents' => (int) ($item['unit_price_cents'] ?? 0),
                'total_cents'      => (int) ($item['total_cents'] ?? 0),
                'currency'         => $item['currency']   ?? ($cart['summary']['currency'] ?? 'USD'),
                'product_id'       => $item['product_id'] ?? null,
                'variant_id'       => $item['variant_id'] ?? null,
                'tax_rate'         => '0.00',
            ];
        }

        $currency = $cart['summary']['currency'] ?? 'USD';

        return [
            'email'          => $payload['email']    ?? '',
            'billing'        => $payload['billing']  ?? [],
            'shipping'       => $payload['shipping'] ?? null,
            'items'          => $items,
            'currency'       => $currency,
            'state'          => 'pending',
            'subtotal_cents' => (int) ($cart['summary']['subtotal_cents'] ?? 0),
            'shipping_cents' => (int) ($payload['shipping_cents'] ?? 0),
            'tax_cents'      => (int) ($payload['tax_cents'] ?? 0),
            'discount_cents' => (int) ($payload['discount_cents'] ?? 0),
            'total_cents'    => (int) ($cart['summary']['total_cents'] ?? 0),
        ];
    }

    private function buildOrderUrl(string $orderNo): string
    {
        return Uri::root() . 'index.php?option=com_nxpeasycart&view=order&no=' . rawurlencode($orderNo);
    }

    private function respond(array $payload, int $code = 200): void
    {
        $app      = Factory::getApplication();
        $response = new JsonResponse($payload, $code);
        $app->setHeader('Content-Type', 'application/json', true);
        $app->setBody($response->toString());
        $app->sendResponse();
        $app->close();
    }
}
