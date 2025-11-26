<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Payment\PaymentGatewayManager;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Component\Nxpeasycart\Administrator\Service\InvoiceService;
use Joomla\Component\Nxpeasycart\Administrator\Service\OrderService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;
use Joomla\Component\Nxpeasycart\Administrator\Service\PaymentGatewayService;
use Joomla\Component\Nxpeasycart\Administrator\Service\RateLimiter;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\SettingsService;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Session\SessionInterface;
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
        $session = $app->getSession();
        $identity = null;

        try {
            $identity = $app->getIdentity();
        } catch (\Throwable $exception) {
            $identity = null;
        }

        if (!$this->hasValidToken()) {
            $this->respond(['message' => Text::_('JINVALID_TOKEN')], 403);
        }

        $payload = $this->decodePayload($input->json->getRaw() ?? '');
        $gateway = isset($payload['gateway']) ? strtolower((string) $payload['gateway']) : 'stripe';

        if ($this->honeypotTripped($payload)) {
            $this->logSecurityEvent('checkout_honeypot', [
                'ip'    => $this->getClientIp(),
                'email' => isset($payload['email']) ? (string) $payload['email'] : '',
            ]);

            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_RATE_LIMITED')], 400);
        }

        $this->enforceCheckoutRateLimits(
            $payload,
            $gateway,
            $this->getClientIp(),
            method_exists($session, 'getId') ? (string) $session->getId() : '',
            $identity && !$identity->guest
        );

        if (!in_array($gateway, ['stripe', 'paypal', 'cod', 'bank_transfer'], true)) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_PAYMENT_GATEWAY_INVALID')], 400);
        }

        $container   = Factory::getContainer();

        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if (
            !$container->has(SessionInterface::class)
            || !$container->has(CartSessionService::class)
            || !$container->has(MailService::class)
            || !$container->has(PaymentGatewayService::class)
            || !$container->has(InvoiceService::class)
        ) {
            if (is_file($providerPath)) {
                $container->registerServiceProvider(require $providerPath);
            }
        }

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

        $cartSession = $container->get(CartSessionService::class);
        $presenter   = $this->getCartPresenter();
        $cart        = $presenter->hydrate($cartSession->current());

        if (empty($cart['items'])) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_CART_EMPTY')], 400);
        }

        $this->assertStockAvailable($cart['items'] ?? [], $container->get(DatabaseInterface::class));

        /** @var OrderService $orders */
        $orders       = $container->get(OrderService::class);
        $orderPayload = $this->buildOrderPayload($cart, $payload);
        $orderPayload['payment_method'] = $gateway;

        if ($identity && !$identity->guest) {
            $orderPayload['user_id'] = (int) ($identity->id ?? 0);

            if (empty($orderPayload['email']) && !empty($identity->email)) {
                $orderPayload['email'] = (string) $identity->email;
            }
        }

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
        $orderPayload['state'] = \in_array($gateway, ['cod', 'bank_transfer'], true)
            ? 'pending'
            : $orderPayload['state'];

        $paymentService     = $container->get(PaymentGatewayService::class);
        $paymentConfig      = $paymentService->getConfig();
        $bankTransferConfig = $paymentService->getGatewayConfig('bank_transfer');

        if ($gateway === 'cod' && isset($paymentConfig['cod']['enabled']) && !$paymentConfig['cod']['enabled']) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_PAYMENT_GATEWAY_INVALID')], 400);
        }

        if ($gateway === 'bank_transfer' && empty($paymentConfig['bank_transfer']['enabled'])) {
            $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_PAYMENT_GATEWAY_INVALID')], 400);
        }

        try {
            $order = $orders->create($orderPayload);

            // Increment coupon usage if a coupon was applied
            if (!empty($orderPayload['coupon']['id'])) {
                try {
                    $couponService = new \Joomla\Component\Nxpeasycart\Administrator\Service\CouponService(
                        $container->get(DatabaseInterface::class)
                    );
                    $couponService->incrementUsage((int) $orderPayload['coupon']['id']);
                } catch (\Throwable $couponException) {
                    // Non-fatal: log but don't block order creation
                }
            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() ?: Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_UNAVAILABLE');
            $code    = $exception instanceof RuntimeException ? 400 : 500;

            $this->respond(['message' => $message], $code);
            return;
        }

        /** @var MailService $mailer */
        $mailer = $container->get(MailService::class);
        $order['payment_method'] = $gateway;

        /** @var PaymentGatewayManager $manager */
        if ($gateway === 'cod' || $gateway === 'bank_transfer') {
            $paymentContext = [
                'method'  => $gateway,
                'details' => $gateway === 'bank_transfer'
                    ? ($bankTransferConfig ?: ($paymentConfig['bank_transfer'] ?? []))
                    : [],
            ];

            $orders->recordTransaction((int) $order['id'], [
                'gateway'      => $gateway,
                'status'       => 'pending',
                'amount_cents' => (int) $order['total_cents'],
                'payload'      => ['method' => $gateway],
            ]);

            $orderUrl = $this->buildOrderUrl($order['order_no']);

            $attachments = [];

            if ($gateway === 'bank_transfer') {
                try {
                    $invoice = $container->get(InvoiceService::class);
                    $pdf     = $invoice->generateInvoice($order, ['payment' => $paymentContext]);

                    if (!empty($pdf['content'])) {
                        $attachments[] = [
                            'name'    => $pdf['filename'] ?? ('invoice-' . ($order['order_no'] ?? 'order') . '.pdf'),
                            'content' => (string) $pdf['content'],
                            'type'    => 'application/pdf',
                        ];
                    }
                } catch (\Throwable $exception) {
                    // Non-fatal: continue without attachment if invoice rendering fails.
                }
            }

            $mailer->sendOrderConfirmation($order, [
                'payment'     => $paymentContext,
                'attachments' => $attachments,
            ]);
            $this->clearCart($cartSession, $cart);

            $this->respond([
                'order' => [
                    'id'       => $order['id'],
                    'order_no' => $order['order_no'],
                ],
                'checkout' => [
                    'mode'     => $gateway,
                    'redirect' => $orderUrl,
                    'url'      => $orderUrl,
                ],
            ]);
            return;
        }

        $manager = $container->get(PaymentGatewayManager::class);

        $preferences = [
            'success_url' => $payload['success_url'] ?? ($this->buildOrderUrl($order['order_no']) . '&status=success'),
            'cancel_url'  => $payload['cancel_url']  ?? (Uri::root() . 'index.php?option=com_nxpeasycart&view=cart'),
        ];

        $orderUrl = $this->buildOrderUrl($order['order_no']);

        $checkout = $manager->createHostedCheckout($gateway, [
            'id'       => $order['id'],
            'order_no' => $order['order_no'],
            'currency' => $order['currency'],
            'email'    => $order['email'],
            'billing'  => $order['billing'] ?? [],
            'items'    => $order['items'],
            'summary'  => [
                'total_cents' => $order['total_cents'],
            ],
        ], $preferences);

        $redirectUrl = '';

        if (\is_array($checkout)) {
            $redirectUrl = (string) ($checkout['url'] ?? $checkout['redirect'] ?? '');
        }

        // Fallback to order confirmation URL if the gateway does not provide one.
        if ($redirectUrl === '') {
            $redirectUrl        = $orderUrl;
            $checkout['url']    = $orderUrl;
            $checkout['redirect'] = $orderUrl;
        }

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
        $discountCents = (int) ($cart['summary']['discount_cents'] ?? 0);

        // Store coupon information if applied
        $couponData = null;
        if (!empty($cart['coupon'])) {
            $couponData = [
                'code'  => $cart['coupon']['code'] ?? '',
                'id'    => $cart['coupon']['id'] ?? null,
                'type'  => $cart['coupon']['type'] ?? '',
                'value' => $cart['coupon']['value'] ?? 0,
            ];
        }

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
            'discount_cents' => $discountCents,
            'total_cents'    => (int) ($cart['summary']['total_cents'] ?? 0),
            'coupon'         => $couponData,
        ];
    }

    private function buildOrderUrl(string $orderNo): string
    {
        $route = RouteHelper::getOrderRoute($orderNo, false);

        // Ensure absolute URL for redirects.
        if (str_starts_with($route, 'http://') || str_starts_with($route, 'https://')) {
            return $route;
        }

        return Uri::root() . ltrim($route, '/');
    }

    private function respond(array $payload, int $code = 200): void
    {
        $app = Factory::getApplication();
        $hasError = $code >= 400;

        if (\function_exists('http_response_code')) {
            http_response_code($code);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('Status', (string) $code, true);

        echo new JsonResponse($payload, '', $hasError);
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

    /**
     * Reset the cart after a cash-on-delivery checkout completes.
     */
    private function clearCart(CartSessionService $session, array $cart): void
    {
        try {
            $container = Factory::getContainer();
            $store     = $container->has(CartService::class) ? $container->get(CartService::class) : null;

            if ($store) {
                $store->persist([
                    'id'         => $cart['id']         ?? null,
                    'session_id' => $cart['session_id'] ?? Factory::getApplication()->getSession()->getId(),
                    'user_id'    => $cart['user_id']    ?? null,
                    'data'       => [
                        'currency' => $cart['summary']['currency'] ?? 'USD',
                        'items'    => [],
                    ],
                ]);
            }

            $session->attachToApplication();
        } catch (\Throwable $exception) {
            // Non-fatal: leave the cart intact if reset fails.
        }
    }

    private function getCartPresenter(): CartPresentationService
    {
        $container = Factory::getContainer();

        if ($container->has(CartPresentationService::class)) {
            return $container->get(CartPresentationService::class);
        }

        return new CartPresentationService($container->get(DatabaseInterface::class));
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

    /**
     * Ensure requested quantities are available before starting checkout.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function assertStockAvailable(array $items, DatabaseInterface $db): void
    {
        $variantIds = [];

        foreach ($items as $item) {
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;

            if ($variantId > 0) {
                $variantIds[] = $variantId;
            }
        }

        if (empty($variantIds)) {
            return;
        }

        $variantIds = array_values(array_unique(array_filter($variantIds)));

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('sku'),
                $db->quoteName('stock'),
                $db->quoteName('active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', array_fill(0, \count($variantIds), '?')) . ')');

        foreach ($variantIds as $index => $variantId) {
            $boundVariantId = (int) $variantId;
            $query->bind($index + 1, $boundVariantId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];
        $lookup = [];

        foreach ($rows as $row) {
            $lookup[(int) $row->id] = $row;
        }

        foreach ($items as $item) {
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;

            if ($variantId <= 0) {
                continue;
            }

            $row = $lookup[$variantId] ?? null;

            if (!$row || !(bool) $row->active) {
                $this->respond(
                    ['message' => Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK')],
                    400
                );
            }

            $requestedQty = max(1, (int) ($item['qty'] ?? 1));

            if ((int) ($row->stock ?? 0) < $requestedQty) {
                $this->respond(
                    ['message' => Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK')],
                    400
                );
            }
        }
    }

    /**
     * Apply rate limits to checkout attempts.
     *
     * @param array<string, mixed> $payload
     */
    private function enforceCheckoutRateLimits(
        array $payload,
        string $gateway,
        string $clientIp,
        string $sessionId,
        bool $isAuthenticated = false
    ): void
    {
        $limiter = $this->getRateLimiter();

        if (!$limiter) {
            return;
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $limits = $this->getRateLimitConfig();

        $checkoutWindow = $limits['checkout_window'] ?? 600;
        $offlineWindow  = $limits['offline_window']  ?? 1800;

        // Relax limits for authenticated users to reduce friction while keeping guest protection intact.
        $authFactor = $isAuthenticated ? 5 : 1;
        $relaxLimit = static function ($value, int $factor): int {
            if (!\is_numeric($value)) {
                return 0;
            }

            $limit = (int) $value;

            if ($limit <= 0) {
                return 0;
            }

            return max($limit, (int) ceil($limit * $factor));
        };
        $relaxWindow = static function ($value, int $factor): int {
            if (!\is_numeric($value)) {
                return 0;
            }

            $window = (int) $value;

            if ($window <= 0) {
                return 0;
            }

            return max($window, (int) ceil($window * $factor));
        };

        $checkoutWindow = $relaxWindow($checkoutWindow, $authFactor);
        $offlineWindow  = $relaxWindow($offlineWindow, $authFactor);
        $limits['checkout_ip_limit']      = $relaxLimit($limits['checkout_ip_limit'] ?? 0, $authFactor);
        $limits['checkout_email_limit']   = $relaxLimit($limits['checkout_email_limit'] ?? 0, $authFactor);
        $limits['checkout_session_limit'] = $relaxLimit($limits['checkout_session_limit'] ?? 0, $authFactor);
        $limits['offline_ip_limit']       = $relaxLimit($limits['offline_ip_limit'] ?? 0, $authFactor);
        $limits['offline_email_limit']    = $relaxLimit($limits['offline_email_limit'] ?? 0, $authFactor);

        $checks = [
            [
                'key'     => 'checkout:ip:' . ($clientIp !== '' ? $clientIp : 'unknown'),
                'limit'   => $limits['checkout_ip_limit'] ?? 10,
                'window'  => $checkoutWindow,
                'action'  => 'checkout_rate_limited',
            ],
            [
                'key'     => 'checkout:session:' . ($sessionId !== '' ? $sessionId : 'anon'),
                'limit'   => $limits['checkout_session_limit'] ?? 15,
                'window'  => $checkoutWindow,
                'action'  => 'checkout_rate_limited',
            ],
            [
                'key'     => $email !== '' ? 'checkout:email:' . $email : '',
                'limit'   => $limits['checkout_email_limit'] ?? 5,
                'window'  => $checkoutWindow,
                'action'  => 'checkout_rate_limited',
            ],
        ];

        if (\in_array($gateway, ['cod', 'bank_transfer'], true)) {
            $checks[] = [
                'key'    => 'checkout:offline:ip:' . ($clientIp !== '' ? $clientIp : 'unknown'),
                'limit'  => $limits['offline_ip_limit'] ?? 3,
                'window' => $offlineWindow,
                'action' => 'checkout_offline_rate_limited',
            ];

            $checks[] = [
                'key'    => $email !== '' ? 'checkout:offline:email:' . $email : '',
                'limit'  => $limits['offline_email_limit'] ?? 3,
                'window' => $offlineWindow,
                'action' => 'checkout_offline_rate_limited',
            ];
        }

        foreach ($checks as $check) {
            if ($check['key'] === '' || (int) $check['limit'] <= 0 || (int) $check['window'] <= 0) {
                continue;
            }

            if (!$limiter->hit($check['key'], (int) $check['limit'], (int) $check['window'])) {
                $this->logSecurityEvent($check['action'], ['ip' => $clientIp, 'email' => $email]);
                $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_RATE_LIMITED')], 429);
            }
        }
    }

    /**
     * Return a shared rate limiter instance.
     */
    private function getRateLimiter(): ?RateLimiter
    {
        $container = Factory::getContainer();

        if ($container->has(RateLimiter::class)) {
            try {
                return $container->get(RateLimiter::class);
            } catch (\Throwable $exception) {
                return null;
            }
        }

        try {
            if ($container->has(CacheControllerFactoryInterface::class)) {
                return new RateLimiter($container->get(CacheControllerFactoryInterface::class));
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    /**
     * Lightweight honeypot check.
     *
     * @param array<string, mixed> $payload
     */
    private function honeypotTripped(array $payload): bool
    {
        $traps = [
            'company_website',
            'website',
            'url',
            'honeypot',
        ];

        // Require presence and emptiness of all trap fields
        foreach ($traps as $trap) {
            if (!array_key_exists($trap, $payload)) {
                return true; // Missing trap field is suspicious
            }

            $raw = $payload[$trap];
            $value = is_array($raw) ? implode('', $raw) : (string) $raw;
            if (trim($value) !== '') {
                return true; // Filled trap trips the honeypot
            }
        }

        return false;
    }

    private function getClientIp(): string
    {
        $server = Factory::getApplication()->input->server;
        $ip     = (string) $server->getString('REMOTE_ADDR', '');

        return trim($ip);
    }

    /**
     * Record a security-related audit event when possible.
     */
    private function logSecurityEvent(string $action, array $context = []): void
    {
        try {
            $audit = $this->getAuditService();

            if (!$audit) {
                return;
            }

            $userId = null;

            try {
                $identity = Factory::getApplication()->getIdentity();
                $userId   = $identity && !$identity->guest ? (int) $identity->id : null;
            } catch (\Throwable $exception) {
                $userId = null;
            }

            $audit->record('security', 0, $action, $context, $userId);
        } catch (\Throwable $exception) {
            // Swallow logging failures to avoid blocking checkout.
        }
    }

    private function getAuditService(): ?AuditService
    {
        $container = Factory::getContainer();

        if ($container->has(AuditService::class)) {
            try {
                return $container->get(AuditService::class);
            } catch (\Throwable $exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Resolve rate limit configuration with sensible defaults.
     *
     * @return array<string, int>
     */
    private function getRateLimitConfig(): array
    {
        $defaults = [
            'checkout_ip_limit'      => 10,
            'checkout_email_limit'   => 5,
            'checkout_session_limit' => 15,
            'checkout_window'        => 900,
            'offline_ip_limit'       => 10,
            'offline_email_limit'    => 5,
            'offline_window'         => 14400,
        ];

        try {
            $service = $this->getSettingsService();
            $stored  = $service ? (array) $service->get('security.rate_limits', []) : [];
        } catch (\Throwable $exception) {
            $stored = [];
        }

        return [
            'checkout_ip_limit'      => $this->sanitiseLimit($stored['checkout_ip_limit'] ?? null, $defaults['checkout_ip_limit']),
            'checkout_email_limit'   => $this->sanitiseLimit($stored['checkout_email_limit'] ?? null, $defaults['checkout_email_limit']),
            'checkout_session_limit' => $this->sanitiseLimit($stored['checkout_session_limit'] ?? null, $defaults['checkout_session_limit']),
            'checkout_window'        => $this->sanitiseWindow($stored['checkout_window'] ?? null, $defaults['checkout_window']),
            'offline_ip_limit'       => $this->sanitiseLimit($stored['offline_ip_limit'] ?? null, $defaults['offline_ip_limit']),
            'offline_email_limit'    => $this->sanitiseLimit($stored['offline_email_limit'] ?? null, $defaults['offline_email_limit']),
            'offline_window'         => $this->sanitiseWindow($stored['offline_window'] ?? null, $defaults['offline_window']),
        ];
    }

    private function sanitiseLimit($value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : $default;
    }

    private function sanitiseWindow($value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : $default;
    }

    private function getSettingsService(): ?SettingsService
    {
        $container = Factory::getContainer();

        if ($container->has(SettingsService::class)) {
            try {
                return $container->get(SettingsService::class);
            } catch (\Throwable $exception) {
                return null;
            }
        }

        return null;
    }
}
