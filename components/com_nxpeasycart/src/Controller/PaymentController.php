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
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\ShippingRuleService;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\Component\Nxpeasycart\Administrator\Event\EasycartEventDispatcher;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;
use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Session\SessionInterface;
use RuntimeException;

/**
 * Front-end controller for initiating hosted checkout flows.
 *
 * @since 0.1.5
 */
class PaymentController extends BaseController
{
    use CsrfValidation;
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

        if (!$this->hasValidCsrfToken()) {
            $this->respond(['message' => Text::_('JINVALID_TOKEN')], 403);
            return;
        }

        // Enforce HTTPS for checkout (configurable via component params)
        if (!$this->isSecureConnection()) {
            $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_nxpeasycart');
            $enforceHttps = $params->get('enforce_https_checkout', true);

            if ($enforceHttps) {
                $this->logSecurityEvent('checkout_insecure_connection', [
                    'ip' => $this->getClientIp(),
                ]);
                $this->respond(['message' => Text::_('COM_NXPEASYCART_ERROR_HTTPS_REQUIRED')], 403);
                return;
            }
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

        // Dispatch plugin event: onNxpEasycartBeforeCheckout
        // Plugins can throw RuntimeException to block checkout
        try {
            EasycartEventDispatcher::beforeCheckout($cart, $payload, $gateway);
        } catch (RuntimeException $exception) {
            $this->respond(['message' => $exception->getMessage()], 400);
            return;
        }

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

        $subtotal = (int) ($orderPayload['subtotal_cents'] ?? 0);
        $discount = max(0, (int) ($orderPayload['discount_cents'] ?? 0));
        $requiresShipping = !empty($orderPayload['has_physical']);

        if ($requiresShipping) {
            $shippingCents = $this->resolveShippingAmount(
                isset($payload['shipping_rule_id']) ? (int) $payload['shipping_rule_id'] : null,
                $subtotal
            );
        } else {
            $shippingCents            = 0;
            $orderPayload['shipping'] = null;
        }
        // Tax must be calculated on subtotal AFTER discounts to avoid overcharging gateways
        $taxableSubtotal = max(0, $subtotal - $discount);
        $tax = $this->calculateTaxAmount($payload, $taxableSubtotal);

        $orderPayload['shipping_cents'] = $shippingCents;
        $orderPayload['tax_cents']      = $tax['amount'];
        $orderPayload['tax_rate']       = $tax['rate'];
        // discount_cents already calculated in buildOrderPayload() from database prices
        $orderPayload['tax_inclusive']  = $tax['inclusive'];
        $orderPayload['items']          = $this->applyTaxRateToItems(
            $orderPayload['items'] ?? [],
            $tax['rate']
        );

        // Recalculate total with final shipping, tax, and discount
        $taxAmount = $tax['inclusive'] ? 0 : $tax['amount'];

        $orderPayload['total_cents'] = $subtotal + $shippingCents + $taxAmount - $discount;
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

        // Re-validate coupon at checkout with actual email (for per-user limits)
        // This is necessary because when the coupon was applied to cart, we didn't have the guest's email yet
        if (!empty($orderPayload['coupon']['code'])) {
            $couponService = new \Joomla\Component\Nxpeasycart\Administrator\Service\CouponService(
                $container->get(DatabaseInterface::class)
            );

            $checkoutUserId = $identity && !$identity->guest ? (int) $identity->id : null;
            $checkoutEmail  = !empty($orderPayload['email']) ? (string) $orderPayload['email'] : null;

            // Determine if cart has sale items for coupon validation
            $cartHasSaleItems = false;
            foreach ($cart['items'] ?? [] as $cartItem) {
                if (!empty($cartItem['sale_active'])) {
                    $cartHasSaleItems = true;
                    break;
                }
            }

            $couponValidation = $couponService->validate(
                (string) $orderPayload['coupon']['code'],
                $subtotal,
                $cartHasSaleItems,
                $checkoutUserId,
                $checkoutEmail
            );

            if (!$couponValidation['valid']) {
                $this->respond(['message' => $couponValidation['error']], 400);
                return;
            }
        }

        try {
            $order = $orders->create($orderPayload);

            // Increment coupon usage if a coupon was applied
            if (!empty($orderPayload['coupon']['id'])) {
                try {
                    $couponService = new \Joomla\Component\Nxpeasycart\Administrator\Service\CouponService(
                        $container->get(DatabaseInterface::class)
                    );

                    // Increment global usage counter
                    $couponService->incrementUsage((int) $orderPayload['coupon']['id']);

                    // Record per-user usage for limit tracking
                    $orderUserId = isset($order['user_id']) && $order['user_id'] > 0
                        ? (int) $order['user_id']
                        : null;
                    $orderEmail = !empty($order['email']) ? (string) $order['email'] : null;

                    $couponService->recordUsage(
                        (int) $orderPayload['coupon']['id'],
                        (int) $order['id'],
                        $orderUserId,
                        $orderEmail
                    );
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

        $publicToken = isset($order['public_token']) ? (string) $order['public_token'] : '';

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

            $orderUrl = $this->buildOrderUrl($order['order_no'], $publicToken);

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
                    'public_token' => $publicToken,
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
            'success_url' => $this->sanitizeRedirectUrl(
                $payload['success_url'] ?? null,
                $this->buildOrderUrl($order['order_no'], $publicToken) . '&status=success'
            ),
            'cancel_url'  => $this->sanitizeRedirectUrl(
                $payload['cancel_url'] ?? null,
                Uri::root() . 'index.php?option=com_nxpeasycart&view=cart&canceled=1'
            ),
        ];

        $orderUrl = $this->buildOrderUrl($order['order_no'], $publicToken);

        $checkout = $manager->createHostedCheckout($gateway, [
            'id'       => $order['id'],
            'order_no' => $order['order_no'],
            'currency' => $order['currency'],
            'email'    => $order['email'],
            'billing'  => $order['billing'] ?? [],
            'items'    => $order['items'],
            'summary'  => [
                'subtotal_cents' => (int) ($order['subtotal_cents'] ?? 0),
                'shipping_cents' => (int) ($order['shipping_cents'] ?? 0),
                'tax_cents'      => (int) ($order['tax_cents'] ?? 0),
                'tax_inclusive'  => !empty($order['tax_inclusive']),
                'discount_cents' => (int) ($order['discount_cents'] ?? 0),
                'total_cents'    => (int) ($order['total_cents'] ?? 0),
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
                'public_token' => $publicToken,
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
     *
     * @since 0.1.5
     */
    private function buildOrderPayload(array $cart, array $payload): array
    {
        $container = Factory::getContainer();
        $db        = $container->get(DatabaseInterface::class);
        $items     = [];
        $hasDigital  = false;
        $hasPhysical = false;

        // SECURITY: Recalculate ALL prices from database - never trust cart prices
        // Collect all variant IDs first for batch query (performance optimization)
        $variantIds = array_map(
            static fn ($item) => (int) ($item['variant_id'] ?? 0),
            $cart['items']
        );

        // Single batch query for all variants
        $variantsLookup = $this->loadVariantsForCheckoutBatch($db, $variantIds);

        foreach ($cart['items'] as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $qty       = max(1, (int) ($item['qty'] ?? 1));

            if ($variantId <= 0) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'));
            }

            // Get variant from batch lookup
            $variant = $variantsLookup[$variantId] ?? null;

            if (!$variant) {
                throw new RuntimeException(
                    Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_NOT_FOUND', $variantId)
                );
            }

            if (!(bool) $variant->active || (isset($variant->product_active) && !(bool) $variant->product_active)) {
                throw new RuntimeException(
                    Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_INACTIVE', $variant->sku ?? $variantId)
                );
            }

            $productType = isset($variant->product_type) ? strtolower((string) $variant->product_type) : 'physical';
            $isDigital   = ((int) ($variant->is_digital ?? 0) === 1) || $productType === 'digital';

            // Use database price, NOT cart price
            $unitPriceCents = (int) ($variant->price_cents ?? 0);
            $totalCents     = $unitPriceCents * $qty;
            $currency       = ConfigHelper::getBaseCurrency();

            $items[] = [
                'sku'              => $variant->sku ?? '',
                'title'            => $variant->sku ?? ($item['title'] ?? ''),
                'qty'              => $qty,
                'unit_price_cents' => $unitPriceCents,  // FROM DATABASE
                'total_cents'      => $totalCents,       // RECALCULATED
                'currency'         => $currency,
                'product_id'       => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'variant_id'       => $variantId,
                'tax_rate'         => '0.00',
                'is_digital'       => $isDigital,
            ];

            if ($isDigital) {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }
        }

        // Recalculate subtotal from database prices
        $subtotalCents = array_reduce($items, static fn($sum, $item) => $sum + ($item['total_cents'] ?? 0), 0);

        $currency = ConfigHelper::getBaseCurrency();

        // SECURITY: Recalculate coupon discount from database subtotal, not cart-stored discount
        $discountCents = 0;
        $couponData = null;

        if (!empty($cart['coupon'])) {
            $couponData = [
                'code'  => $cart['coupon']['code'] ?? '',
                'id'    => $cart['coupon']['id'] ?? null,
                'type'  => $cart['coupon']['type'] ?? '',
                'value' => $cart['coupon']['value'] ?? 0,
            ];

            // Recalculate discount based on REAL subtotal from database
            $discountCents = $this->calculateCouponDiscount(
                $couponData,
                $subtotalCents
            );
        }

        // Capture frontend language for localised emails/invoices
        $locale = 'en-GB';

        try {
            $locale = Factory::getApplication()->getLanguage()->getTag();
        } catch (\Throwable $e) {
            // Fallback to en-GB if language detection fails
        }

        return [
            'email'          => $payload['email']    ?? '',
            'billing'        => $payload['billing']  ?? [],
            'shipping'       => $payload['shipping'] ?? null,
            'items'          => $items,
            'currency'       => $currency,
            'state'          => 'pending',
            'subtotal_cents' => $subtotalCents,  // RECALCULATED FROM DATABASE
            'shipping_cents' => 0,  // Will be set later by resolveShippingAmount()
            'tax_cents'      => 0,  // Will be set later by calculateTaxAmount()
            'discount_cents' => $discountCents,  // RECALCULATED FROM DATABASE
            'total_cents'    => 0,  // Will be recalculated after shipping and tax
            'coupon'         => $couponData,
            'locale'         => $locale,
            'has_digital'    => $hasDigital,
            'has_physical'   => $hasPhysical,
        ];
    }

    private function buildOrderUrl(string $orderNo, ?string $publicToken = null): string
    {
        $route = RouteHelper::getOrderRoute($orderNo, false, $publicToken);

        // Ensure absolute URL for redirects.
        if (str_starts_with($route, 'http://') || str_starts_with($route, 'https://')) {
            return $route;
        }

        return Uri::root() . ltrim($route, '/');
    }

    /**
     * Ensure redirect targets stay on the current origin; fall back when invalid.
     *
     * @since 0.1.5
     */
    private function sanitizeRedirectUrl(?string $url, string $fallback): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        $root = Uri::root();
        $rootParts = parse_url($root);

        // Normalise relative paths to absolute using the site root.
        if (str_starts_with($url, '/')) {
            $url = rtrim($root, '/') . $url;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($rootParts['host'])) {
            return $fallback;
        }

        $sameHost  = strcasecmp((string) $parts['host'], (string) $rootParts['host']) === 0;
        $sameProto = empty($parts['scheme']) || empty($rootParts['scheme'])
            ? true
            : strcasecmp((string) $parts['scheme'], (string) $rootParts['scheme']) === 0;

        if (!$sameHost || !$sameProto) {
            return $fallback;
        }

        return $url;
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
     * Calculate tax amount based on configured rates and billing address.
     *
     * @return array{amount: int, rate: float, inclusive: bool}
     *
     * @since 0.1.5
     */
    private function calculateTaxAmount(array $payload, int $subtotal): array
    {
        $billing = $payload['billing'] ?? [];
        // Use country_code (2-letter ISO) for tax matching, not the display name
        $country = strtoupper(trim((string) ($billing['country_code'] ?? $billing['country'] ?? '')));
        $region  = strtolower(trim((string) ($billing['region_code'] ?? $billing['region'] ?? '')));

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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     * Reset the cart after a successful checkout and regenerate the session ID
     * to prevent session fixation attacks.
     *
     * @since 0.1.5
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
                        'currency' => ConfigHelper::getBaseCurrency(),
                        'items'    => [],
                    ],
                ]);
            }

            $session->attachToApplication();

            // SECURITY: Regenerate session ID after successful checkout to prevent
            // session fixation attacks. The cart is now cleared, so a new session
            // ID ensures any attacker who knew the old session cannot hijack it.
            $this->regenerateSession();
        } catch (\Throwable $exception) {
            // Non-fatal: leave the cart intact if reset fails.
        }
    }

    /**
     * Regenerate the session ID to prevent session fixation attacks.
     * Called after successful checkout completion.
     *
     * @since 0.1.5
     */
    private function regenerateSession(): void
    {
        try {
            $app = Factory::getApplication();
            $joomlaSession = $app->getSession();

            // Mark checkout as completed before regeneration
            $joomlaSession->set('nxp_ec_checkout_completed', true);

            // Regenerate session ID while preserving session data
            // This prevents session fixation attacks where an attacker
            // pre-sets a session ID and waits for the user to complete checkout
            if (method_exists($joomlaSession, 'regenerate')) {
                $joomlaSession->regenerate(true);
            } elseif (\function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
            }

            // Log the session regeneration for audit
            $this->logSecurityEvent('checkout.session_regenerated', [
                'message' => 'Session ID regenerated after successful checkout',
            ]);
        } catch (\Throwable $exception) {
            // Non-fatal: log but don't block checkout completion
        }
    }

    /**
     * Check if the current request is over a secure HTTPS connection.
     * Handles reverse proxy/load balancer scenarios via forwarding headers.
     *
     * @since 0.1.5
     */
    private function isSecureConnection(): bool
    {
        $uri = Uri::getInstance();

        // Direct HTTPS check
        if ($uri->isSsl()) {
            return true;
        }

        // Check for reverse proxy/load balancer forwarding headers
        $input = Factory::getApplication()->input;
        $forwardedProto = strtolower((string) $input->server->getString('HTTP_X_FORWARDED_PROTO', ''));
        $forwardedSsl = strtolower((string) $input->server->getString('HTTP_X_FORWARDED_SSL', ''));

        if ($forwardedProto === 'https' || $forwardedSsl === 'on') {
            return true;
        }

        return false;
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
     *
     * @since 0.1.5
     */
    private function assertStockAvailable(array $items, DatabaseInterface $db): void
    {
        $variantIds = [];

        foreach ($items as $item) {
            if (!empty($item['is_digital'])) {
                continue;
            }

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
            ->from($db->quoteName('#__nxp_easycart_variants'));

        // Use whereIn for safe parameterized IN clause
        $query->whereIn($db->quoteName('id'), $variantIds);

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
            $productTitle = $item['title'] ?? $item['product_title'] ?? ($row->sku ?? Text::_('COM_NXPEASYCART_UNKNOWN_PRODUCT'));

            if (!$row || !(bool) $row->active) {
                $this->respond(
                    ['message' => Text::sprintf('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK_NAMED', $productTitle)],
                    400
                );
            }

            $requestedQty = max(1, (int) ($item['qty'] ?? 1));
            $availableStock = (int) ($row->stock ?? 0);

            if ($availableStock < $requestedQty) {
                $this->respond(
                    ['message' => Text::sprintf('COM_NXPEASYCART_PRODUCT_INSUFFICIENT_STOCK', $productTitle, $availableStock)],
                    400
                );
            }
        }
    }

    /**
     * Apply rate limits to checkout attempts.
     *
     * @param array<string, mixed> $payload
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
     */
    private function honeypotTripped(array $payload): bool
    {
        $traps = [
            'company_website',
            'website',
            'url',
            'honeypot',
        ];

        // Backward-compatible honeypot: if any trap field is present and non-empty, trip.
        // If traps are absent (older clients/cached JS), do not trip.
        $foundTrap = false;
        foreach ($traps as $trap) {
            if (array_key_exists($trap, $payload)) {
                $foundTrap = true;
                $raw = $payload[$trap];
                $value = is_array($raw) ? implode('', $raw) : (string) $raw;
                if (trim($value) !== '') {
                    return true; // Filled trap trips the honeypot
                }
            }
        }

        // No traps found or all present traps were empty => allow
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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

    /**
     * Load variant with current price from database for checkout.
     * SECURITY: This method ensures prices are ALWAYS fetched from database,
     * never trusted from cart data.
     *
     * @param DatabaseInterface $db
     * @param int $variantId
     * @return object|null
     *
     * @since 0.1.5
     */
    private function loadVariantForCheckout(DatabaseInterface $db, int $variantId): ?object
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('product_id'),
                $db->quoteName('sku'),
                $db->quoteName('price_cents'),
                $db->quoteName('currency'),
                $db->quoteName('stock'),
                $db->quoteName('active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $result = $db->loadObject();
            return $result ?: null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Load multiple variants with current prices from database in a single query.
     * SECURITY: This method ensures prices are ALWAYS fetched from database,
     * never trusted from cart data. Batch version for performance.
     *
     * @param DatabaseInterface $db
     * @param array<int> $variantIds
     * @return array<int, object> Keyed by variant ID
     *
     * @since 0.1.11
     */
    private function loadVariantsForCheckoutBatch(DatabaseInterface $db, array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_filter(
            array_map('intval', $variantIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($variantIds)) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('v.id'),
                $db->quoteName('v.product_id'),
                $db->quoteName('v.sku'),
                $db->quoteName('v.price_cents'),
                $db->quoteName('v.currency'),
                $db->quoteName('v.stock'),
                $db->quoteName('v.active'),
                $db->quoteName('v.is_digital'),
                $db->quoteName('p.product_type', 'product_type'),
                $db->quoteName('p.active', 'product_active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants', 'v'))
            ->join(
                'INNER',
                $db->quoteName('#__nxp_easycart_products', 'p')
                . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('v.product_id')
            )
            ->whereIn($db->quoteName('v.id'), $variantIds);

        $db->setQuery($query);

        try {
            $rows = $db->loadObjectList() ?: [];
            $lookup = [];

            foreach ($rows as $row) {
                $lookup[(int) $row->id] = $row;
            }

            return $lookup;
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * Calculate coupon discount from database subtotal.
     * SECURITY: This method ensures discount is calculated from real database prices,
     * not cart-stored prices that could be tampered with.
     *
     * @param array<string, mixed> $coupon
     * @param int $subtotalCents
     * @return int
     *
     * @since 0.1.5
     */
    private function calculateCouponDiscount(array $coupon, int $subtotalCents): int
    {
        $type  = $coupon['type'] ?? '';
        $value = (int) ($coupon['value'] ?? 0);

        if ($value <= 0) {
            return 0;
        }

        switch ($type) {
            case 'percent':
            case 'percentage':
                // Calculate percentage discount
                return (int) round(($subtotalCents * $value) / 100);

            case 'fixed':
                // Apply fixed amount discount (cannot exceed subtotal)
                return min($value, $subtotalCents);

            default:
                return 0;
        }
    }
}
