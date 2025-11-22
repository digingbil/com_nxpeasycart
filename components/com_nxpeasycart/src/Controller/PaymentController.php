<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Session\SessionInterface;
use Joomla\CMS\Session\Session;
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

        if (!$this->hasValidToken()) {
            $this->respond(['message' => Text::_('JINVALID_TOKEN')], 403);
        }

        $payload = $this->decodePayload($input->json->getRaw() ?? '');
        $gateway = isset($payload['gateway']) ? strtolower((string) $payload['gateway']) : 'stripe';

        if (!in_array($gateway, ['stripe', 'paypal', 'cod'], true)) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_PAYMENT_GATEWAY_INVALID')], 400);
        }

        $container   = Factory::getContainer();

        if (!$container->has(SessionInterface::class)) {
            $container->share(
                SessionInterface::class,
                static function (): SessionInterface {
                    if (method_exists(Factory::class, 'getSession')) {
                        return Factory::getSession();
                    }

                    return Factory::getApplication()->getSession();
                }
            );
        }

        if (!$container->has(CartSessionService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                $container->registerServiceProvider(require $providerPath);
            }
        }

        $cartService = $container->get(CartSessionService::class);
        $cart        = $cartService->current();

        if (empty($cart['items'])) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_CART_EMPTY')], 400);
        }

        /** @var OrderService $orders */
        $orders       = $container->get(OrderService::class);
        $orderPayload = $this->buildOrderPayload($cart, $payload);

        $shippingCents = $this->resolveShippingAmount(
            isset($payload['shipping_rule_id']) ? (int) $payload['shipping_rule_id'] : null,
            (int) ($cart['summary']['subtotal_cents'] ?? 0)
        );
        $tax = $this->calculateTaxAmount($payload, (int) ($orderPayload['subtotal_cents'] ?? 0));

        $orderPayload['shipping_cents'] = $shippingCents;
        $orderPayload['tax_cents']      = $tax['amount'];
        $orderPayload['discount_cents'] = $orderPayload['discount_cents'] ?? 0;
        $orderPayload['tax_inclusive']  = $tax['inclusive'];
        $orderPayload['items']          = $this->applyTaxRateToItems(
            $orderPayload['items'] ?? [],
            $tax['rate']
        );
        $orderPayload['state'] = $gateway === 'cod' ? 'pending' : $orderPayload['state'];

        $order = $orders->create($orderPayload);

        /** @var PaymentGatewayManager $manager */
        if ($gateway === 'cod') {
            $orders->recordTransaction((int) $order['id'], [
                'gateway'      => 'cod',
                'status'       => 'pending',
                'amount_cents' => (int) $order['total_cents'],
                'payload'      => ['method' => 'cash_on_delivery'],
            ]);

            $orderUrl = $this->buildOrderUrl($order['order_no']);

            $this->respond([
                'order' => [
                    'id'       => $order['id'],
                    'order_no' => $order['order_no'],
                ],
                'checkout' => [
                    'mode'     => 'cod',
                    'redirect' => $orderUrl,
                    'url'      => $orderUrl,
                ],
            ]);
        }

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

    /**
     * Verify a CSRF token via header or request payload.
     */
    private function hasValidToken(): bool
    {
        $input       = Factory::getApplication()->getInput();
        $headerToken = (string) $input->server->getString('HTTP_X_CSRF_TOKEN', '');
        $sessionToken = Session::getFormToken();

        if ($headerToken !== '' && hash_equals($sessionToken, $headerToken)) {
            return true;
        }

        return Session::checkToken('post');
    }

    /**
     * Calculate tax amount based on configured rates and billing address.
     *
     * @return array{amount: int, rate: float, inclusive: bool}
     */
    private function calculateTaxAmount(array $payload, int $subtotal): array
    {
        $billing = $payload['billing'] ?? [];
        $country = strtoupper(trim((string) ($billing['country'] ?? '')));
        $region  = strtolower(trim((string) ($billing['region'] ?? '')));

        try {
            $service = $this->getTaxService();
            $result  = $service->paginate([], 100, 0);
            $rates   = $result['items'] ?? [];
        } catch (\Throwable $exception) {
            $rates = [];
        }

        usort(
            $rates,
            static fn ($a, $b) => ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0)
        );

        $matches = array_values(array_filter($rates, static function ($rate) use ($country, $region) {
            $rateCountry = strtoupper((string) ($rate['country'] ?? ''));
            $rateRegion  = strtolower((string) ($rate['region'] ?? ''));

            if ($rateCountry !== '' && $country !== '' && $rateCountry !== $country) {
                return false;
            }

            if ($rateRegion !== '') {
                return $region !== '' && $rateRegion === $region;
            }

            if ($rateCountry !== '' && $country === '') {
                return false;
            }

            return true;
        }));

        $globalRates = array_values(array_filter($rates, static function ($rate) {
            $rateCountry = strtoupper((string) ($rate['country'] ?? ''));
            $rateRegion  = strtolower((string) ($rate['region'] ?? ''));

            return $rateCountry === '' && $rateRegion === '';
        }));

        $selected = $matches[0] ?? ($globalRates[0] ?? null);

        if (!$selected || empty($selected['rate'])) {
            return [
                'amount'    => 0,
                'rate'      => 0.0,
                'inclusive' => false,
            ];
        }

        $percentage = (float) $selected['rate'];
        $inclusive  = !empty($selected['inclusive']);

        $tax = $inclusive
            ? (int) round($subtotal - ($subtotal / (1 + ($percentage / 100))))
            : (int) round($subtotal * ($percentage / 100));

        return [
            'amount'    => $tax,
            'rate'      => $percentage,
            'inclusive' => $inclusive,
        ];
    }

    /**
     * Resolve shipping amount from configured rules.
     */
    private function resolveShippingAmount(?int $ruleId, int $subtotal): int
    {
        if (!$ruleId) {
            return 0;
        }

        try {
            $service = $this->getShippingService();
            $rule    = $service->get($ruleId);
        } catch (\Throwable $exception) {
            return 0;
        }

        if (!$rule || (isset($rule['active']) && !$rule['active'])) {
            return 0;
        }

        if (
            ($rule['type'] ?? '') === 'free_over'
            && isset($rule['threshold_cents'])
            && $rule['threshold_cents'] !== null
            && $subtotal >= (int) $rule['threshold_cents']
        ) {
            return 0;
        }

        return (int) ($rule['price_cents'] ?? 0);
    }

    /**
     * Apply a tax rate to each order item.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function applyTaxRateToItems(array $items, float $rate): array
    {
        $formatted = $rate > 0 ? sprintf('%.2f', $rate) : '0.00';

        return array_map(
            static function ($item) use ($formatted) {
                if (!\is_array($item)) {
                    return $item;
                }

                $item['tax_rate'] = $item['tax_rate'] ?? $formatted;

                return $item;
            },
            $items
        );
    }

    private function getShippingService(): ShippingRuleService
    {
        $container = Factory::getContainer();

        if ($container->has(ShippingRuleService::class)) {
            return $container->get(ShippingRuleService::class);
        }

        return new ShippingRuleService($container->get(DatabaseInterface::class));
    }

    private function getTaxService(): TaxService
    {
        $container = Factory::getContainer();

        if ($container->has(TaxService::class)) {
            return $container->get(TaxService::class);
        }

        return new TaxService($container->get(DatabaseInterface::class));
    }
}
