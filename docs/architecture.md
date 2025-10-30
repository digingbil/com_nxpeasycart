# NXP Easy Cart – Architecture Overview (M0–M1)

## Component layout

-   `administrator/components/com_nxpeasycart`
    -   `services/provider.php`: registers the component, custom MVC factory, and dispatcher with Joomla's DI container (Joomla 5 bootstraps the component without an entry PHP file).
    -   `src/Administrator/Factory/EasyCartMVCFactory.php`: extends Joomla's MVC factory to point site requests at the `Nxp\EasyCart\Site` namespace while keeping administrator and API traffic on `Nxp\EasyCart\Admin\Administrator`.
    -   `nxpeasycart.xml`: component manifest (filename omits the `com_` prefix so Joomla Discover picks it up).
    -   `src/Administrator/`: PSR-4 namespaced administrator classes (`Nxp\EasyCart\Admin\Administrator\…`).
        -   `Controller/ApiController.php`: task router delegating to JSON resource controllers.
        -   `Controller/Api/*Controller.php`: JSON endpoints returning RFC-7807-style payloads.
        -   `Model/*.php`: table-backed product storage and listing with transactional saves for variants/categories.
        -   `Table/ProductTable.php`: database gateway enforcing slug uniqueness.
        -   `Table/VariantTable.php`: SKU validator ensuring currency/price integrity and unique SKU constraints.
        -   `Table/CategoryTable.php`: helper for slug generation and uniqueness when auto-creating categories.
    -   `sql/`: install, uninstall, and future update scripts for the `#__nxp_easycart_*` tables.
    -   `forms/product.xml`: Joomla form definition used to validate API payloads.
-   `components/com_nxpeasycart`
    -   `src/`: storefront controllers/views in the `Nxp\EasyCart\Site\…` namespace; the default `DisplayController` now renders product detail pages populated from the database with SEO-aware metadata.

The admin view exposes a `<div id="nxp-admin-app">` mount target for the upcoming Vue IIFE bundle as defined in the instructions.

## Database schema

The install script provisions the core tables required by the domain model:

-   `#__nxp_easycart_products`, `#__nxp_easycart_variants`, `#__nxp_easycart_categories`, and pivot table `#__nxp_easycart_product_categories`.
-   Order pipeline tables `#__nxp_easycart_orders`, `#__nxp_easycart_order_items`, `#__nxp_easycart_transactions`, plus `#__nxp_easycart_coupons`.
-   Operational tables for compliance and pricing logic: `#__nxp_easycart_tax_rates`, `#__nxp_easycart_shipping_rules`, `#__nxp_easycart_settings`, and optional cart persistence via `#__nxp_easycart_carts`.
-   `#__nxp_easycart_audit` for state-change logging.

The `config.xml` exposed through Joomla's component options captures the single-currency MVP guardrail; variant persistence now enforces that every SKU uses the configured base currency.

All relationships use InnoDB FK constraints and default to cascading deletes to keep referential integrity during early development.

## Admin API

-   Requests are routed through `ApiController`, which maps `task=api.{resource}.{action}` to resource-specific controllers.
-   `AbstractJsonController` enforces ACL (`core.manage`, `core.create`, `core.edit`, `core.delete`), CSRF tokens via `Session::checkToken('request')`, and standard JSON responses.
-   The shared JSON responder now echoes the encoded payload and sets `Content-Type`, guaranteeing the Vue admin fetchers receive data even when accessed via raw AJAX tools.
-   `ProductsController` implements browse/store/update/destroy endpoints operating on the `#__nxp_easycart_products` table using the Joomla MVC model and table classes.
-   `OrdersController` exposes paginated order listings, state transitions, and order detail retrieval; the Vue SPA consumes these endpoints via `useOrders()`.
-   `ProductModel` wraps saves in DB transactions, persisting product rows alongside related variants (`#__nxp_easycart_variants`) and category assignments (`#__nxp_easycart_product_categories`), with JSON serialisation for images/options.
-   Service layer classes (`CartService`, `OrderService`) encapsulate persistence for carts and orders, enforcing the single-currency guardrail via `ConfigHelper` and handling JSON serialisation/hydration for billing, shipping, line items, and stored cart payloads.
-   On the storefront, `CartSessionService` maps Joomla sessions to database-backed carts and injects the hydrated cart payload into the application input for downstream controllers/views.
-   API responses return hydrated products including variant collections, category metadata, image arrays, and computed summaries (variant counts, price range, currency hints).

## Admin SPA build

-   Web assets are declared in `media/com_nxpeasycart/joomla.asset.json` under the handle `com_nxpeasycart.admin`.
-   `media/com_nxpeasycart/src/admin-main.js` (Vue 3) is compiled through Vite (`build/vite.config.admin.js`) into `media/com_nxpeasycart/js/admin.iife.js` with CSS emitted to `media/com_nxpeasycart/css/admin.css`.
-   The shell renders product and order workspaces fed by `/index.php?option=com_nxpeasycart&task=api.products.list&format=json` and `/index.php?option=com_nxpeasycart&task=api.orders.list&format=json`, featuring search/filter controls, optimistic updates, and state transitions.
-   The Joomla view registers the asset handle and exposes CSRF tokens/API endpoints via `data-*` attributes on the mount node to keep the SPA stateless and CSRF-safe.
-   Admin UI is composed from Vue single-file components (`src/app/App.vue`, `src/app/components`) with composables like `useProducts` and `useTranslations`, leveraging `Joomla.Text` for localisation instead of hard-coded translation objects.
-   `media/com_nxpeasycart/src/api.js` exposes an `ApiClient` that unifies request headers, CSRF handling, and HTTP verb helpers, with domain methods starting at `fetchProducts()`.
-   Products can now be created, edited, and deleted directly from the admin grid via a reusable modal editor component that manages images, category chips, and variant tables, syncing payloads with the JSON endpoints. Orders can be searched, filtered by state, inspected, and transitioned from the same SPA.
-   A server-rendered orders fallback table ships inside the Joomla view so administrators can see seed data immediately; it removes itself once the Vue bundle mounts. The fallback now also preloads order data into the Vue app via data attributes so the SPA can hydrate without another request.
-   Admin endpoints are emitted as absolute Joomla administrator URLs and the API client merges query parameters onto those URLs, preventing accidental site-app routing. Translation helpers now run placeholder strings through `Joomla.sprintf`, so labels like “0 items” render correctly even when the core translations supply `%s` placeholders.
-   On the storefront, the product view detects missing/invalid slugs and renders the onboarding placeholder copy instead of raising a 404, allowing `/index.php?option=com_nxpeasycart` to remain functional while catalog data is still being seeded.
-   `administrator/components/com_nxpeasycart/script.php` ensures the base schema is applied for installs, updates, and discover installs so environments never miss the `#__nxp_easycart_*` tables.

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
  "categories": [
    {"id": 3, "title": "Apparel", "slug": "apparel"}
  ],
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
        {"name": "Colour", "value": "Red"},
        {"name": "Size", "value": "M"}
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

Variant payloads accept `price` (major units), `currency`, `stock`, `weight`, `active`, and optional option arrays. Category strings are auto-created with clean slugs; repeated saves deduplicate by slug. The admin UI enforces at least one variant per product before save.

## Next steps

1. Extend the admin surface to cover orders, carts, and customer management (API + SPA routes).
2. Add configuration endpoints for payment gateway settings and surface them in the admin UI.
3. Introduce domain services (money, inventory, orders) backed by the schema created here, including stock reservation rules and audit logging.

## Testing reference

- Automated testing strategy lives in `docs/testing.md` and covers PHPUnit suites, API contract runs, Vue unit tests, and Playwright end-to-end coverage.
