# NXP Easy Cart – Architecture Overview (M0–M1)

## Component layout

- `administrator/components/com_nxpeasycart`
    - `services/provider.php`: registers the component, custom MVC factory, and dispatcher with Joomla's DI container (Joomla 5 bootstraps the component without an entry PHP file).
        - Also attaches the storefront `LandingAliasRule` during `onAfterInitialise` so menu aliases such as `/shop-landing` resolve even when a template swaps in its own site router (JA Purity IV, Helix, etc.).
    - `src/Factory/EasyCartMVCFactory.php`: extends Joomla's MVC factory to point site requests at the `Joomla\Component\Nxpeasycart\Site` namespace while keeping administrator and API traffic on `Joomla\Component\Nxpeasycart\Administrator`.
    - `nxpeasycart.xml`: component manifest (filename omits the `com_` prefix so Joomla Discover picks it up).
    - `src/`: PSR-4 namespaced administrator classes (`Joomla\Component\Nxpeasycart\Administrator\…`).
        - `Controller/ApiController.php`: task router delegating to JSON resource controllers.
        - `Controller/Api/*Controller.php`: JSON endpoints returning RFC-7807-style payloads.
        - `Model/*.php`: table-backed product storage and listing with transactional saves for variants/categories.
        - `Table/ProductTable.php`: database gateway enforcing slug uniqueness.
        - `Table/VariantTable.php`: SKU validator ensuring currency/price integrity and unique SKU constraints.
        - `Table/CategoryTable.php`: helper for slug generation and uniqueness when auto-creating categories.
    - `sql/`: install, uninstall, and future update scripts for the `#__nxp_easycart_*` tables.
    - `forms/product.xml`: Joomla form definition used to validate API payloads.
- `components/com_nxpeasycart`
    - `src/`: storefront controllers/views in the `Joomla\Component\Nxpeasycart\Site\…` namespace; the default `DisplayController` now renders product detail pages populated from the database with SEO-aware metadata.
    - `tmpl/`: server-rendered storefront layouts (product, category, cart, checkout, landing) that expose Vue "island" mount points and pass locale/currency into the islands for consistent money formatting.
    - `media/com_nxpeasycart`: built assets (hashed JS/CSS) + `joomla.asset.json` pointing to the hashed site/admin bundles; Vite emits `media/com_nxpeasycart/.vite/manifest.json` for registry updates.
    - `modules/mod_nxpeasycart_cart`: header cart summary module that consumes the cart island payload.
- Stock management & checkout guards (latest work)
    - Orders reserve/decrement stock atomically when created; products auto-disable when remaining stock is zero.
    - Admin products API exposes stock aggregates in summaries; admin list shows stock with low/unavailable warnings.
    - Storefront add-to-cart and checkout validate requested quantities against variant stock; out-of-stock flows are surfaced to the user instead of silently failing.
    - Order locking is implemented via guarded `UPDATE … WHERE stock >= :qty` to avoid overselling and stay DB-driver agnostic.

The admin view exposes a `<div id="nxp-ec-admin-app">` mount target for the upcoming Vue IIFE bundle as defined in the instructions.

## Database schema

The install script provisions the core tables required by the domain model:

- `#__nxp_easycart_products`, `#__nxp_easycart_variants`, `#__nxp_easycart_categories`, and pivot table `#__nxp_easycart_product_categories`.
- Products carry a `primary_category_id` FK so URLs and breadcrumbs can target a single canonical category path even when products belong to multiple categories.
- Order pipeline tables `#__nxp_easycart_orders`, `#__nxp_easycart_order_items`, `#__nxp_easycart_transactions`, plus `#__nxp_easycart_coupons`.
- Operational tables for compliance and pricing logic: `#__nxp_easycart_tax_rates`, `#__nxp_easycart_shipping_rules`, `#__nxp_easycart_settings`, and optional cart persistence via `#__nxp_easycart_carts`.
- `#__nxp_easycart_audit` for state-change logging.

The `config.xml` exposed through Joomla's component options captures the single-currency MVP guardrail; variant persistence now enforces that every SKU uses the configured base currency.

All relationships use InnoDB FK constraints and default to cascading deletes to keep referential integrity during early development.

## Admin API

- Requests are routed through `ApiController`, which maps `task=api.{resource}.{action}` to resource-specific controllers.
- The controller now reads the raw `task` parameter (without Joomla’s dot-stripping filters) so dotted task names like `api.products.store` reach the intended API handlers.
- `AbstractJsonController` enforces ACL (`core.manage`, `core.create`, `core.edit`, `core.delete`), CSRF tokens via `Session::checkToken('request')`, and standard JSON responses.
- The shared JSON responder now echoes the encoded payload and sets `Content-Type`, guaranteeing the Vue admin fetchers receive data even when accessed via raw AJAX tools.
- `ProductsController` implements browse/store/update/destroy endpoints operating on the `#__nxp_easycart_products` table using the Joomla MVC model and table classes.
- `OrdersController` exposes paginated order listings, state transitions, and order detail retrieval; the Vue SPA consumes these endpoints via `useOrders()`.
- `ProductModel` wraps saves in DB transactions, persisting product rows alongside related variants (`#__nxp_easycart_variants`) and category assignments (`#__nxp_easycart_product_categories`), with JSON serialisation for images/options.
- Service layer classes (`CartService`, `OrderService`) encapsulate persistence for carts and orders, enforcing the single-currency guardrail via `ConfigHelper` and handling JSON serialisation/hydration for billing, shipping, line items, and stored cart payloads.
- On the storefront, `CartSessionService` maps Joomla sessions to database-backed carts and injects the hydrated cart payload into the application input for downstream controllers/views.
- API responses return hydrated products including variant collections, category metadata, image arrays, and computed summaries (variant counts, price range, currency hints).

## Admin SPA build

- Web assets are declared in `media/com_nxpeasycart/joomla.asset.json` under the handle `com_nxpeasycart.admin`.
- `media/com_nxpeasycart/src/admin-main.js` (Vue 3) is compiled through Vite (`build/vite.config.admin.js`) into `media/com_nxpeasycart/js/admin.iife.js` with CSS emitted to `media/com_nxpeasycart/css/admin.css`.
- The shell renders product and order workspaces fed by `/index.php?option=com_nxpeasycart&task=api.products.list&format=json` and `/index.php?option=com_nxpeasycart&task=api.orders.list&format=json`, featuring search/filter controls, optimistic updates, and state transitions.
- The Joomla view registers the asset handle and exposes CSRF tokens/API endpoints via `data-*` attributes on the mount node to keep the SPA stateless and CSRF-safe.
- Admin UI is composed from Vue single-file components (`src/app/App.vue`, `src/app/components`) with composables like `useProducts` and `useTranslations`, leveraging `Joomla.Text` for localisation instead of hard-coded translation objects.
- Translation helpers now normalise `%s` replacements and currency fallbacks, while the dashboard adopts Joomla 5's Font Awesome iconography for checklist states.
- `media/com_nxpeasycart/src/api.js` exposes an `ApiClient` that unifies request headers, CSRF handling, and HTTP verb helpers, with domain methods starting at `fetchProducts()`.
- Products can now be created, edited, and deleted directly from the admin grid via a reusable modal editor component that manages images, category chips, and variant tables, syncing payloads with the JSON endpoints. Orders can be searched, filtered by state, inspected, and transitioned from the same SPA.
- The settings workspace persists store metadata and the component's base currency via the JSON controllers (`api.settings.*`), focusing on General, Security, Payments, and Visual configuration tabs.
- **Tax Rates** and **Shipping Methods** are managed through dedicated first-class admin panels (`TaxPanel.vue`, `ShippingPanel.vue`) with their own views (`Tax/HtmlView.php`, `Shipping/HtmlView.php`) and submenu entries, following the modal-based CRUD pattern established by Coupons. See `docs/admin-tax-shipping.md` for details.
- An audit logs surface streams entries from `#__nxp_easycart_audit`, enabling admins to filter and review lifecycle events without leaving the SPA.
- A server-rendered orders fallback table ships inside the Joomla view so administrators can see seed data immediately; it removes itself once the Vue bundle mounts. The fallback now also preloads order data into the Vue app via data attributes so the SPA can hydrate without another request.
- Admin endpoints are emitted as absolute Joomla administrator URLs and the API client merges query parameters onto those URLs, preventing accidental site-app routing. Translation helpers now run placeholder strings through `Joomla.sprintf`, so labels like “0 items” render correctly even when the core translations supply `%s` placeholders.
- On the storefront, the product view detects missing/invalid slugs and renders the onboarding placeholder copy instead of raising a 404, allowing `/index.php?option=com_nxpeasycart` to remain functional while catalog data is still being seeded.
- `administrator/components/com_nxpeasycart/script.php` ensures the base schema is applied for installs, updates, and discover installs so environments never miss the `#__nxp_easycart_*` tables.

## Storefront build & islands

- `build/vite.config.site.js` emits hashed site bundles + manifest (`media/com_nxpeasycart/.vite/manifest.json`); `media/com_nxpeasycart/joomla.asset.json` references the hashed entry with `version: auto` for cache-busting.
- The storefront JS is split into per-island modules (product/cart-button, category, cart, cart-summary, checkout, landing) loaded on demand via dynamic `import()` and lazy-mounted via `IntersectionObserver`.
- Shared site utilities provide CSRF-aware API client, locale-aware money formatting, and payload parsing; locale/currency are passed from server-rendered templates via `data-nxp-*` attributes to keep PHP/JS output in sync.
- Template theme tokens are resolved through `TemplateAdapter` and memoised per request to avoid re-parsing template params when multiple views request defaults.
- Menu usage: the Store Category menu type uses "Category" to target a single category (leave blank for all products). "Root Categories" only applies when Category is blank—it limits the "all products" listing and filter chips to those selected roots; otherwise it is ignored.
- **Checkout floating summary**: The checkout island includes a floating order summary bar that appears at the bottom of the viewport when the main totals section scrolls out of view. Uses `IntersectionObserver` for efficient scroll detection and CSS transitions for smooth animations. See `docs/checkout-floating-summary.md` for implementation details.

### Categories & product editing refresh

- The admin SPA now ships with a dedicated **Categories** workspace backed by `CategoriesController`. The table renders an indented hierarchy (parent/child depth is shown via padding) plus usage counts, making it obvious which categories feed active products. The parent selector filters out the current node and its descendants, preventing cyclical trees.
- Product editing consumes the categories API: existing categories appear in a multi-select, while new names can still be created inline. The form posts normalised `{id, title, slug}` payloads so the backend can reuse existing rows or create new ones without duplicating pivot entries.
- All database bindings were refactored to pass concrete variables into Joomla’s query builder. This removed the sporadic `Argument #2 ($value) could not be passed by reference` fatal that previously surfaced when saving products, variants, or categories.

### Product payload (admin API)

The `/api.products.*` endpoints exchange JSON objects similar to:

```json
{
    "id": 12,
    "title": "T-Shirt",
    "slug": "t-shirt",
    "short_desc": "Classic crew neck",
    "long_desc": "<p>Organic cotton.</p>",
    "active": true,
    "images": ["https://cdn.example.com/products/t-shirt-front.jpg"],
    "categories": [{ "id": 3, "title": "Apparel", "slug": "apparel" }],
    "variants": [
        {
            "id": 27,
            "sku": "TS-RED-M",
            "price_cents": 2599,
            "price": "25.99",
            "currency": "USD",
            "stock": 42,
            "weight": "0.300",
            "active": true,
            "options": [
                { "name": "Colour", "value": "Red" },
                { "name": "Size", "value": "M" }
            ]
        }
    ],
    "summary": {
        "variants": {
            "count": 3,
            "price_min_cents": 2599,
            "price_max_cents": 2999,
            "currency": "USD",
            "multiple_currencies": false
        }
    }
}
```

Variant payloads accept `price` (major units), `currency`, `stock`, `weight`, `active`, and optional option arrays. Categories now arrive as normalised objects (not free-form strings). If an item carries an `id`, the backend reuses the existing category; otherwise a clean slug is generated and a new record is created. The admin UI still enforces at least one variant per product before save.

## Next steps

1. Wire payment gateway configuration and checkout workflows, building on the existing settings infrastructure.
2. Implement storefront cart and checkout surfaces that consume the tax, shipping, and coupon services created for the admin.
3. Expand automated coverage around the new admin APIs (settings, tax, shipping, logs) with PHPUnit and Vue component tests.

## Testing reference

- Automated testing strategy lives in `docs/testing.md` and covers PHPUnit suites, API contract runs, Vue unit tests, and Playwright end-to-end coverage.

## Payments & Caching (M2 refresh)

- `PaymentGatewayManager` brokers hosted checkout sessions via dedicated Stripe/PayPal adapters, records webhook events with idempotency keys, decrements variant stock on `paid`, and triggers order confirmation email delivery through `MailService`.
- `PaymentGatewayService` persists configuration in the component settings table, masking secrets for the Vue admin while retaining raw values for the gateway drivers.
- `CacheService` wraps Joomla's callback cache controller; storefront checkout reuses it to memoise shipping/tax datasets and reduce repetitive queries.
- `MailService` renders PHP-based templates (see `templates/email/order_confirmation.php`) and uses Joomla's configured mailer for transactional notifications.
- GDPR requests are served by `GdprService`, exposing export/anonymise endpoints wired into the admin API controller.

## Cart Quantity Persistence (M3 enhancement)

- **CartController::update()** provides a new `task=cart.update` endpoint that accepts `variant_id` (or `product_id`) and `qty` parameters via POST with CSRF token validation. The method:
    - Validates stock availability against `#__nxp_easycart_variants.stock` before allowing quantity increases
    - Updates the cart item quantity in the database (`#__nxp_easycart_carts.data` JSON payload)
    - Returns the hydrated cart via `CartPresentationService` for immediate UI synchronization
    - Dispatches server-side errors (e.g., out-of-stock) as JSON responses with user-friendly messages
- **cart.js island** implements optimistic UI updates with server persistence:
    - `updateQty()` immediately updates the local reactive state and recalculates totals for instant feedback
    - Sends async POST to the `cart.update` endpoint with the new quantity
    - On success, applies the server-returned cart state and dispatches a `nxp-cart:updated` custom event
    - On failure (e.g., insufficient stock), shows an alert and refreshes the cart from `cart.summary` to revert to actual state
    - All quantity changes persist to the database, ensuring cart state survives page navigation
- **checkout.js island** listens for cart updates:
    - `onMounted(refreshCart)` fetches fresh cart data via `cart.summary` endpoint when the checkout page loads, preventing stale quantities from server-rendered templates
    - Subscribes to `nxp-cart:updated` window events to react to quantity changes made on other pages/tabs
    - Uses reactive `cartItems.splice()` to ensure Vue detects array mutations and re-renders the Order Summary
- **Stock validation flow**:
    1. User changes quantity on cart page (e.g., 2 → 4)
    2. cart.js sends POST to `cart.update` with CSRF token
    3. `CartController::update()` queries variant stock from database
    4. If `requested_qty > available_stock`, returns `{success: false, message: "This product is currently out of stock."}`
    5. cart.js shows alert and refreshes cart to display maximum available quantity
    6. If stock check passes, cart is persisted and hydrated cart returned
    7. `nxp-cart:updated` event notifies checkout/cart-summary modules
- **Language strings** added to `com_nxpeasycart.ini`:
    - `COM_NXPEASYCART_ERROR_CART_ITEM_NOT_FOUND` for missing cart items during update
    - All cart error messages use Joomla Text constants for i18n compliance

## Coupon System (M3 enhancement)

The coupon system allows customers to apply discount codes during checkout. Coupons are managed in the admin panel and validated/applied via storefront endpoints.

### Endpoints

- **`task=cart.applyCoupon`** – Applies a coupon code to the current cart session
- **`task=cart.removeCoupon`** – Removes the applied coupon from the cart

### Implementation Details

- **CSRF validation** uses `Session::checkToken('request')` which validates:
    - Form tokens in POST body (traditional Joomla form submissions)
    - `X-CSRF-Token` header (API client `postJson()` requests)
- **JSON body parsing** for `postJson()` requests:

    ```php
    $raw = $input->json->getRaw();
    if ($raw !== null && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['code'])) {
            $code = strtoupper(trim((string) $json['code']));
        }
    }
    // Falls back to $input->getString('code') for form submissions
    ```

- **Cart subtotal calculation** from raw (unhydrated) cart items:

    ```php
    foreach ($items as $item) {
        $unitPrice = (int) ($item['unit_price_cents'] ?? 0);
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $subtotalCents += $unitPrice * $qty;
    }
    ```

    This is necessary because raw cart items stored in `#__nxp_easycart_carts.data` only contain `unit_price_cents` and `qty`; the `total_cents` field is computed during hydration by `CartPresentationService`.

- **Cart persistence** uses `CartService::persist()` with the full cart structure:

    ```php
    $carts->persist([
        'id'         => $cart['id'],
        'session_id' => $cart['session_id'] ?? Factory::getApplication()->getSession()->getId(),
        'user_id'    => $cart['user_id'] ?? null,
        'data'       => $payload,  // includes coupon data
    ]);
    ```

- **Response hydration** returns the full cart with summary via `CartPresentationService::hydrate()`:
    ```php
    $updatedCart = $session->current();
    $hydrated = $presenter->hydrate($updatedCart);
    echo new JsonResponse([
        'cart'    => $hydrated,
        'message' => Text::_('COM_NXPEASYCART_SUCCESS_COUPON_APPLIED'),
    ]);
    ```

### Coupon Validation (CouponService)

The `CouponService::validate()` method checks:

1. Coupon exists and is active
2. Start/end date validity
3. Usage limits (`max_uses` vs `times_used`)
4. Minimum order total (`min_total_cents` vs cart subtotal)

Discount calculation supports:

- **Percent** – `(subtotalCents * value) / 100`
- **Fixed** – `value * 100` (converted to cents)

Discount is capped at the cart subtotal to prevent negative totals.

### Vue Integration (checkout.js)

The checkout island manages coupon state:

- `couponCode` ref – bound to the coupon input field via `v-model`
- `coupon` ref – stores the applied coupon object from the server
- `couponMessage` ref – displays success/error feedback
- `couponLoading` ref – disables UI during API calls

The `applyCoupon()` function:

1. Validates input is not empty
2. Sends POST to `endpoints.applyCoupon` with `{ code }` JSON body
3. Updates `coupon` ref on success and refreshes cart items
4. Displays server error messages on failure

### Language Strings

- `COM_NXPEASYCART_ERROR_COUPON_CODE_REQUIRED` – "Please enter a coupon code."
- `COM_NXPEASYCART_ERROR_COUPON_EMPTY_CART` – "Cannot apply coupon to an empty cart."
- `COM_NXPEASYCART_ERROR_COUPON_MIN_TOTAL` – "Minimum order of %s required for this coupon."
- `COM_NXPEASYCART_SUCCESS_COUPON_APPLIED` – "Coupon applied!"
- `COM_NXPEASYCART_SUCCESS_COUPON_REMOVED` – "Coupon removed."

## Plugin Events (Extensibility)

The component dispatches Joomla plugin events at key lifecycle points to enable third-party integrations. These events are fired via `EasycartEventDispatcher` and can be subscribed to by system plugins.

### Available Events

| Event                                | Location                                 | Trigger                 | Arguments                                  |
| ------------------------------------ | ---------------------------------------- | ----------------------- | ------------------------------------------ |
| `onNxpEasycartBeforeCheckout`        | `PaymentController::checkout()`          | Before order creation   | `cart`, `payload`, `gateway`               |
| `onNxpEasycartAfterOrderCreate`      | `OrderService::create()`                 | After order saved to DB | `order`, `gateway`                         |
| `onNxpEasycartAfterOrderStateChange` | `OrderService::transitionState()`        | After status change     | `order`, `fromState`, `toState`, `actorId` |
| `onNxpEasycartAfterPaymentComplete`  | `PaymentGatewayManager::handleWebhook()` | After payment confirmed | `order`, `transaction`, `gateway`          |

### Event Dispatcher

The `EasycartEventDispatcher` helper class (`src/Event/EasycartEventDispatcher.php`) provides static methods for dispatching each event:

```php
use Joomla\Component\Nxpeasycart\Administrator\Event\EasycartEventDispatcher;

// Before checkout (plugins can throw RuntimeException to block)
EasycartEventDispatcher::beforeCheckout($cart, $payload, $gateway);

// After order creation
EasycartEventDispatcher::afterOrderCreate($order, $gateway);

// After state transition
EasycartEventDispatcher::afterOrderStateChange($order, $fromState, $toState, $actorId);

// After payment success
EasycartEventDispatcher::afterPaymentComplete($order, $transaction, $gateway);
```

### Plugin Example

A system plugin can subscribe to these events:

```php
// plugins/system/myeasycart/myeasycart.php
namespace MyCompany\Plugin\System\MyEasycart;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class MyEasycart extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onNxpEasycartAfterOrderCreate'      => 'handleOrderCreate',
            'onNxpEasycartAfterOrderStateChange' => 'handleStateChange',
            'onNxpEasycartAfterPaymentComplete'  => 'handlePaymentComplete',
            'onNxpEasycartBeforeCheckout'        => 'handleBeforeCheckout',
        ];
    }

    public function handleOrderCreate($event): void
    {
        $order = $event->getArgument('order');
        // Send to CRM, trigger analytics, etc.
    }

    public function handleStateChange($event): void
    {
        $order = $event->getArgument('order');
        $toState = $event->getArgument('toState');

        if ($toState === 'fulfilled') {
            // Send shipping notification
        }
    }

    public function handlePaymentComplete($event): void
    {
        $order = $event->getArgument('order');
        $transaction = $event->getArgument('transaction');
        // Update external inventory, send to fulfillment API
    }

    public function handleBeforeCheckout($event): void
    {
        $cart = $event->getArgument('cart');
        // Validate cart or throw RuntimeException to block checkout
    }
}
```

### Design Decisions

- **Non-blocking**: All event dispatches are wrapped in try/catch to prevent plugin errors from blocking core functionality.
- **System plugins**: Events import system plugins via `PluginHelper::importPlugin('system')` before dispatch.
- **State transitions**: The `onNxpEasycartAfterOrderStateChange` event fires for all transitions including webhook-triggered `paid` states, admin-triggered `fulfilled` states, and refunds.
- **Checkout blocking**: Only `onNxpEasycartBeforeCheckout` can block the flow by throwing a `RuntimeException`; other events are purely informational.
