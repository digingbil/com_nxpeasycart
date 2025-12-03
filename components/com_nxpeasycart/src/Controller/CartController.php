<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\Session\SessionInterface;
use Joomla\Component\Nxpeasycart\Administrator\Service\CartService;
use Joomla\Component\Nxpeasycart\Administrator\Service\RateLimiter;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Site\Service\CartPresentationService;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;

/**
 * AJAX controller handling cart mutations on the storefront.
 *
 * @since 0.1.5
 */
class CartController extends BaseController
{
    /**
     * Append a product or variant to the active cart session.
     *
     * @return void
     *
     * @throws \Throwable When persistence fails mid-transaction.
     *
     * @since 0.1.5
     */
    public function add(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $this->enforceRateLimit();

        $input     = $app->input;
        $productId = $input->getInt('product_id');
        $variantId = $input->getInt('variant_id');
        $qty       = max(1, $input->getInt('qty', 1));

        if ($productId <= 0 && $variantId <= 0) {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $this->ensureCartServices($container);
        $db        = $container->get(DatabaseInterface::class);
        $carts     = $container->get(CartService::class);
        $session   = new CartSessionService(
            $carts,
            Factory::getApplication()->getSession()
        );
        $presenter = $container->get(CartPresentationService::class);

        try {
            $variant = $this->loadVariant($db, $variantId);

            if (!$variant) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_VARIANT_NOT_FOUND'), true);
                $app->close();
            }

            $variantProductId = (int) $variant->product_id;

            if ($productId > 0 && $productId !== $variantProductId) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
                $app->close();
            }

            $productId = $variantProductId;
            $product   = $this->loadProduct($db, $productId);

            if (!$product) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_PRODUCT_NOT_FOUND'), true);
                $app->close();
            }

            $cart    = $session->current();
            $payload = $cart['data'] ?? [];
            $items   = \is_array($payload['items'] ?? null) ? $payload['items'] : [];

            $existingQty = 0;

            foreach ($items as $existing) {
                if ((int) ($existing['variant_id'] ?? 0) === $variantId) {
                    $existingQty += (int) ($existing['qty'] ?? 0);
                }
            }

            $desiredQty = $existingQty + $qty;

            if ((int) ($variant->stock ?? 0) < $desiredQty) {
                echo new JsonResponse(
                    null,
                    Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'),
                    true
                );
                $app->close();
            }

            $items = $this->upsertCartItem(
                $items,
                $product,
                $variant,
                $qty
            );

            $payload['items'] = $items;

            $joomlaSession = Factory::getApplication()->getSession();

            $persisted = $carts->persist([
                'id'         => $cart['id']         ?? null,
                'session_id' => $cart['session_id'] ?? $joomlaSession->getId(),
                'user_id'    => $cart['user_id']    ?? null,
                'data'       => $payload,
            ]);

            $hydrated = $presenter->hydrate($persisted);

            // Re-attach the latest cart payload for downstream observers.
            try {
                $session->attachToApplication();
            } catch (\Throwable $exception) {
                // Non-fatal.
            }

            echo new JsonResponse(
                ['cart' => $hydrated],
                Text::_('COM_NXPEASYCART_PRODUCT_ADDED_TO_CART')
            );
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            echo new JsonResponse(
                null,
                $exception->getMessage(),
                true
            );
        }

        $app->close();
    }

    /**
     * Remove a line item from the active cart session.
     *
     * @return void
     *
     * @since 0.1.5
     */
    public function remove(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $this->enforceRateLimit();

        $input     = $app->input;
        $variantId = $input->getInt('variant_id');
        $productId = $input->getInt('product_id');

        if ($variantId <= 0 && $productId <= 0) {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $this->ensureCartServices($container);

        $carts   = $container->get(CartService::class);
        $session = new CartSessionService(
            $carts,
            Factory::getApplication()->getSession()
        );
        $presenter = $container->get(CartPresentationService::class);

        try {
            $cart    = $session->current();
            $payload = $cart['data'] ?? [];
            $items   = \is_array($payload['items'] ?? null) ? $payload['items'] : [];

            $items = array_values(array_filter($items, function ($item) use ($variantId, $productId) {
                $itemVariantId = (int) ($item['variant_id'] ?? 0);
                $itemProductId = (int) ($item['product_id'] ?? 0);

                if ($variantId > 0 && $itemVariantId === $variantId) {
                    return false;
                }

                if ($productId > 0 && $itemProductId === $productId) {
                    return false;
                }

                return true;
            }));

            $payload['items'] = $items;

            $joomlaSession = Factory::getApplication()->getSession();

            $persisted = $carts->persist([
                'id'         => $cart['id']         ?? null,
                'session_id' => $cart['session_id'] ?? $joomlaSession->getId(),
                'user_id'    => $cart['user_id']    ?? null,
                'data'       => $payload,
            ]);

            $hydrated = $presenter->hydrate($persisted);

            echo new JsonResponse(['cart' => $hydrated]);
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            echo new JsonResponse(
                null,
                $exception->getMessage(),
                true
            );
        }

        $app->close();
    }

    /**
     * Update the quantity of an existing cart item.
     *
     * @return void
     *
     * @since 0.1.5
     */
    public function update(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('post')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $this->enforceRateLimit();

        $input     = $app->input;
        $variantId = $input->getInt('variant_id');
        $productId = $input->getInt('product_id');
        $qty       = max(1, $input->getInt('qty', 1));

        if ($variantId <= 0 && $productId <= 0) {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_INVALID_REQUEST'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $this->ensureCartServices($container);

        $carts     = $container->get(CartService::class);
        $session   = new CartSessionService(
            $carts,
            Factory::getApplication()->getSession()
        );
        $presenter = $container->get(CartPresentationService::class);
        $db        = $container->get(DatabaseInterface::class);

        try {
            $cart    = $session->current();
            $payload = $cart['data'] ?? [];
            $items   = \is_array($payload['items'] ?? null) ? $payload['items'] : [];

            $updated = false;

            foreach ($items as $index => $item) {
                $itemVariantId = (int) ($item['variant_id'] ?? 0);
                $itemProductId = (int) ($item['product_id'] ?? 0);

                if (($variantId > 0 && $itemVariantId === $variantId) ||
                    ($productId > 0 && $itemProductId === $productId)) {

                    // Check stock availability
                    if ($variantId > 0) {
                        $variant = $this->loadVariant($db, $variantId);
                        if ($variant && (int) ($variant->stock ?? 0) < $qty) {
                            echo new JsonResponse(
                                null,
                                Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'),
                                true
                            );
                            $app->close();
                        }
                    }

                    $items[$index]['qty'] = $qty;
                    $items[$index]['unit_price_cents'] = (int) ($item['unit_price_cents'] ?? 0);
                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_ITEM_NOT_FOUND'), true);
                $app->close();
            }

            $payload['items'] = $items;

            $joomlaSession = Factory::getApplication()->getSession();

            $persisted = $carts->persist([
                'id'         => $cart['id']         ?? null,
                'session_id' => $cart['session_id'] ?? $joomlaSession->getId(),
                'user_id'    => $cart['user_id']    ?? null,
                'data'       => $payload,
            ]);

            $hydrated = $presenter->hydrate($persisted);

            echo new JsonResponse(['cart' => $hydrated]);
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            echo new JsonResponse(
                null,
                $exception->getMessage(),
                true
            );
        }

        $app->close();
    }

    /**
     * Return the current cart summary for the active visitor.
     *
     * @return void
     *
     * @throws \Throwable When cart retrieval fails.
     *
     * @since 0.1.5
     */
    public function summary(): void
    {
        $app       = Factory::getApplication();
        $container = Factory::getContainer();
        $this->ensureCartServices($container);

        try {
            $session   = new CartSessionService(
                $container->get(CartService::class),
                Factory::getApplication()->getSession()
            );
            $presenter = $container->get(CartPresentationService::class);

            $cart = $presenter->hydrate($session->current());

            echo new JsonResponse(['cart' => $cart]);
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::ERROR, 'com_nxpeasycart.cart');
            $message = (defined('JDEBUG') && JDEBUG)
                ? $exception->getMessage()
                : Text::_('COM_NXPEASYCART_ERROR_CART_GENERIC');

            echo new JsonResponse(null, $message, true);
        }

        $app->close();
    }

    /**
     * Fetch a single variant row ensuring it is active.
     *
     * @param DatabaseInterface $db        Database connector
     * @param int               $variantId Variant identifier
     *
     * @return object|null Active variant row or null when missing
     *
     * @since 0.1.5
     */
    private function loadVariant(DatabaseInterface $db, int $variantId): ?object
    {
        if ($variantId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER)
            ->where($db->quoteName('active') . ' = 1');

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Fetch the associated product ensuring it is published.
     *
     * @param DatabaseInterface $db        Database connector
     * @param int               $productId Product identifier
     *
     * @return object|null Active product row or null when missing
     *
     * @since 0.1.5
     */
    private function loadProduct(DatabaseInterface $db, int $productId): ?object
    {
        if ($productId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__nxp_easycart_products'))
            ->where($db->quoteName('id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->where(
                $db->quoteName('active') . ' IN (:activeStatus, :outOfStockStatus)'
            );

        $activeStatus = ProductStatus::ACTIVE;
        $outOfStockStatus = ProductStatus::OUT_OF_STOCK;
        $query->bind(':activeStatus', $activeStatus, ParameterType::INTEGER);
        $query->bind(':outOfStockStatus', $outOfStockStatus, ParameterType::INTEGER);

        $db->setQuery($query);

        $product = $db->loadObject();

        if (!$product) {
            return null;
        }

        $status = ProductStatus::normalise($product->active ?? ProductStatus::INACTIVE);

        if (!ProductStatus::isPurchasable($status)) {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'), true, 400);
            Factory::getApplication()->close();
        }

        return $product;
    }

    /**
     * Ensure cart-related services are registered when the provider was not executed.
     *
     * @since 0.1.5
     */
    private function ensureCartServices(Container $container): void
    {
        $this->ensureUuidAutoload();

        if (!$container->has(CartService::class)) {
            $container->set(
                CartService::class,
                static fn (Container $container): CartService => new CartService(
                    $container->get(DatabaseInterface::class)
                )
            );
        }

        if (!$container->has(CartPresentationService::class)) {
            $container->set(
                CartPresentationService::class,
                static fn (Container $container): CartPresentationService => new CartPresentationService(
                    $container->get(DatabaseInterface::class)
                )
            );
        }
    }

    /**
     * Ensure the Ramsey UUID autoloader is available for CartService.
     *
     * @since 0.1.5
     */
    private function ensureUuidAutoload(): void
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class, false)) {
            return;
        }

        $runningInsideJoomla = \defined('JPATH_LIBRARIES');

        $candidates = [
            JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/vendor/autoload.php',
            JPATH_SITE . '/components/com_nxpeasycart/vendor/autoload.php',
            // Fallbacks relative to this file / Joomla root
            dirname(__DIR__, 3) . '/vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            JPATH_ROOT . '/vendor/autoload.php',
        ];

        if (!$runningInsideJoomla) {
            // Local repository vendor (development) when not booted inside Joomla.
            $candidates[] = dirname(__DIR__, 4) . '/vendor/autoload.php';
        }

        foreach ($candidates as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;

                if (class_exists(\Ramsey\Uuid\Uuid::class, false)) {
                    return;
                }
            }
        }
    }

    /**
     * Merge or append the variant line into the existing cart items array.
     *
     * @param array<int, array<string, mixed>> $items
     * @param object                           $product
     * @param object                           $variant
     * @param int                              $qty
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function upsertCartItem(array $items, object $product, object $variant, int $qty): array
    {
        $baseCurrency = ConfigHelper::getBaseCurrency();
        $unitPrice    = (int) ($variant->price_cents ?? 0);
        $options      = [];

        if (!empty($variant->options)) {
            $decoded = json_decode((string) $variant->options, true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                $options = array_values(
                    array_filter(
                        $decoded,
                        static fn ($option) => \is_array($option) && isset($option['name'], $option['value'])
                    )
                );
            }
        }

        foreach ($items as $index => $item) {
            if ((int) ($item['variant_id'] ?? 0) === (int) $variant->id) {
                $existingQty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;
                $items[$index]['qty']              = $existingQty + $qty;
                $items[$index]['unit_price_cents'] = $unitPrice;
                $items[$index]['currency']         = $baseCurrency;
                $items[$index]['title']            = $variant->sku ?? $product->title;
                $items[$index]['options']          = $options;

                return $items;
            }
        }

        $items[] = [
            'product_id'       => (int) $product->id,
            'variant_id'       => (int) $variant->id,
            'title'            => $variant->sku ?? $product->title,
            'qty'              => $qty,
            'unit_price_cents' => $unitPrice,
            'currency'         => $baseCurrency,
            'options'          => $options,
        ];

        return $items;
    }

    /**
     * Throttle cart mutations to reduce bot abuse.
     *
     * @since 0.1.5
     */
    private function enforceRateLimit(): void
    {
        $limiter = $this->getRateLimiter();

        if (!$limiter) {
            return;
        }

        $app       = Factory::getApplication();
        $sessionId = $app->getSession()->getId();
        $ip        = $this->getClientIp();
        $key       = sprintf(
            'cart:mutate:ip:%s:session:%s',
            $ip !== '' ? $ip : 'unknown',
            $sessionId !== '' ? $sessionId : 'anon'
        );

        if ($limiter->hit($key, 60, 600)) {
            return;
        }

        http_response_code(429);
        echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_RATE_LIMITED'), true);
        $app->close();
    }

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

    private function getClientIp(): string
    {
        $server = Factory::getApplication()->input->server;

        return trim((string) $server->getString('REMOTE_ADDR', ''));
    }

    /**
     * Apply a coupon code to the current cart.
     *
     * @return void
     *
     * @since 0.1.5
     */
    public function applyCoupon(): void
    {
        $app = Factory::getApplication();

        // CSRF protection - check 'request' to also validate X-CSRF-Token header
        if (!Session::checkToken('request')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $input = $app->input;

        // Try to read code from JSON body first (for postJson calls)
        $code = '';
        $raw  = $input->json->getRaw();

        if ($raw !== null && $raw !== '') {
            $json = json_decode($raw, true);
            if (\is_array($json) && isset($json['code'])) {
                $code = strtoupper(trim((string) $json['code']));
            }
        }

        // Fall back to form/query parameter
        if ($code === '') {
            $code = strtoupper(trim((string) $input->getString('code', '')));
        }

        if ($code === '') {
            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_COUPON_CODE_REQUIRED'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $this->ensureCartServices($container);
        $db        = $container->get(DatabaseInterface::class);
        $carts     = $container->get(CartService::class);
        $session   = new CartSessionService(
            $carts,
            Factory::getApplication()->getSession()
        );
        $presenter = $container->get(CartPresentationService::class);

        try {
            $cart    = $session->current();
            $payload = $cart['data'] ?? [];
            $items   = \is_array($payload['items'] ?? null) ? $payload['items'] : [];

            if (empty($items)) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_COUPON_EMPTY_CART'), true);
                $app->close();
            }

            // SECURITY: Calculate cart subtotal from DATABASE PRICES, not cart-stored prices
            // This prevents coupon discount manipulation via cart price tampering
            // Collect all variant IDs first for batch query (performance optimization)
            $variantIds = array_map(
                static fn ($item) => (int) ($item['variant_id'] ?? 0),
                $items
            );

            // Single batch query for all variants
            $variantsLookup = $this->loadVariantsForCouponBatch($db, $variantIds);

            $subtotalCents = 0;
            foreach ($items as $item) {
                $variantId = (int) ($item['variant_id'] ?? 0);
                $qty       = max(1, (int) ($item['qty'] ?? 1));

                if ($variantId <= 0) {
                    continue;
                }

                // Get variant from batch lookup
                $variant = $variantsLookup[$variantId] ?? null;

                if (!$variant || !(bool) $variant->active) {
                    continue; // Skip inactive variants
                }

                $unitPrice = (int) ($variant->price_cents ?? 0);
                $subtotalCents += $unitPrice * $qty;
            }

            if ($subtotalCents <= 0) {
                echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_COUPON_EMPTY_CART'), true);
                $app->close();
            }

            // Get coupon service
            $couponService = new \Joomla\Component\Nxpeasycart\Administrator\Service\CouponService($db);
            $validation    = $couponService->validate($code, $subtotalCents);

            if (!$validation['valid']) {
                echo new JsonResponse(null, $validation['error'], true);
                $app->close();
            }

            // Store coupon in cart session
            $payload['coupon'] = [
                'code'           => $validation['coupon']['code'],
                'id'             => $validation['coupon']['id'],
                'type'           => $validation['coupon']['type'],
                'value'          => $validation['coupon']['value'],
                'discount_cents' => $validation['discount_cents'],
            ];

            $carts->persist([
                'id'         => $cart['id'],
                'session_id' => $cart['session_id'] ?? Factory::getApplication()->getSession()->getId(),
                'user_id'    => $cart['user_id'] ?? null,
                'data'       => $payload,
            ]);

            // Return updated cart with summary
            $updatedCart = $session->current();
            $hydrated    = $presenter->hydrate($updatedCart);

            echo new JsonResponse([
                'cart'    => $hydrated,
                'message' => Text::_('COM_NXPEASYCART_SUCCESS_COUPON_APPLIED'),
            ]);
            $app->close();
        } catch (\Throwable $exception) {
            Log::add(
                sprintf('Cart coupon apply failed: %s', $exception->getMessage()),
                Log::ERROR,
                'com_nxpeasycart'
            );

            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_GENERIC'), true);
            $app->close();
        }
    }

    /**
     * Remove the applied coupon from the current cart.
     *
     * @return void
     *
     * @since 0.1.5
     */
    public function removeCoupon(): void
    {
        $app = Factory::getApplication();

        // CSRF protection - check 'request' to also validate X-CSRF-Token header
        if (!Session::checkToken('request')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $app->close();
        }

        $container = Factory::getContainer();
        $this->ensureCartServices($container);
        $carts     = $container->get(CartService::class);
        $session   = new CartSessionService(
            $carts,
            Factory::getApplication()->getSession()
        );
        $presenter = $container->get(CartPresentationService::class);

        try {
            $cart    = $session->current();
            $payload = $cart['data'] ?? [];

            // Remove coupon from payload
            unset($payload['coupon']);

            $carts->persist([
                'id'         => $cart['id'],
                'session_id' => $cart['session_id'] ?? Factory::getApplication()->getSession()->getId(),
                'user_id'    => $cart['user_id'] ?? null,
                'data'       => $payload,
            ]);

            // Return updated cart with summary
            $updatedCart = $session->current();
            $hydrated    = $presenter->hydrate($updatedCart);

            echo new JsonResponse([
                'cart'    => $hydrated,
                'message' => Text::_('COM_NXPEASYCART_SUCCESS_COUPON_REMOVED'),
            ]);
            $app->close();
        } catch (\Throwable $exception) {
            Log::add(
                sprintf('Cart coupon removal failed: %s', $exception->getMessage()),
                Log::ERROR,
                'com_nxpeasycart'
            );

            echo new JsonResponse(null, Text::_('COM_NXPEASYCART_ERROR_CART_GENERIC'), true);
            $app->close();
        }
    }

    /**
     * Load variant with current price from database for coupon calculation.
     * SECURITY: This method ensures coupon discounts are calculated based on
     * database prices, not cart-stored prices that could be tampered with.
     *
     * @param DatabaseInterface $db
     * @param int $variantId
     * @return object|null
     *
     * @since 0.1.5
     */
    private function loadVariantForCoupon(DatabaseInterface $db, int $variantId): ?object
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('price_cents'),
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
     * SECURITY: This method ensures coupon discounts are calculated based on
     * database prices, not cart-stored prices. Batch version for performance.
     *
     * @param DatabaseInterface $db
     * @param array<int> $variantIds
     * @return array<int, object> Keyed by variant ID
     *
     * @since 0.1.11
     */
    private function loadVariantsForCouponBatch(DatabaseInterface $db, array $variantIds): array
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
                $db->quoteName('id'),
                $db->quoteName('price_cents'),
                $db->quoteName('active'),
            ])
            ->from($db->quoteName('#__nxp_easycart_variants'));

        $placeholders = [];

        foreach ($variantIds as $index => $variantId) {
            $placeholder = ':variantId' . $index;
            $placeholders[] = $placeholder;
            $boundId = (int) $variantId;
            $query->bind($placeholder, $boundId, ParameterType::INTEGER);
        }

        $query->where($db->quoteName('id') . ' IN (' . implode(',', $placeholders) . ')');

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
}
