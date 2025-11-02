# **IMPORTANT — Product North Star**

Joomla’s big carts try to be everything; **our advantage is clarity \+ speed to first sale**. Small businesses want to: install, add a few products, connect a gateway, and start selling **today**. Every decision should serve this:

-   **10-minute setup**

-   **Clean, obvious admin**

-   **Bulletproof checkout**

-   **Predictable, low-risk updates**

-   **High quality, modular, extensible code** for all PHP, JavaScript, and Vue deliverables.

Agent reminder: when estimating scope, prioritizing tasks, or choosing trade-offs, optimize for time-to-first-sale and reliability over feature breadth.

# **0\) Guiding principles (non-negotiables)**

-   **Security**: `_JEXEC` in every PHP file; Joomla ACL checks; CSRF tokens on all forms & state-changing endpoints; strict JInput sanitization; output escaping; prepared queries only; HTTPS/SSL enforcement on checkout; no card data storage; idempotent payment & webhook flows.

-   **Reliability**: transactional order ops, audit logs, retry on webhooks, graceful failures with admin alerts.

-   **Simplicity**: MVP scope, clean admin SPA, server-rendered storefront with progressive Vue “islands.”

-   **JavaScript only**: absolutely no TypeScript. All client bundles (including Vue) must be authored in ES6 JavaScript.

-   **Modular Vue admin**: build the admin SPA with Vue single-file components, composables, and (when needed) Pinia stores—no monolithic scripts or inline templates; keep logic and translations in dedicated helpers.

-   **Autoloading**: Never use `require_once`; wire every PHP class through PSR-4 namespaces and Joomla/composer autoloaders.

-   **Standards**: PSR-4 autoloading, PSR-12 code style, PSR-16 caching; Joomla Web Asset Manager; Namespaced Joomla MVC.

-   **DB naming**: `#__nxp_easycart_*` tables (Joomla prefix \+ `nxp_easycart_…`).

---

# **1\) Project setup (Week 0\)**

**Repo**

-   `nxp-easy-cart/` (monorepo)

    -   `administrator/components/com_nxpeasycart/`

    -   `components/com_nxpeasycart/`

    -   `plugins/` (payment, shipping, tax)

    -   `media/com_nxpeasycart/` (bundles, images)

    -   `build/` (Vite configs, packaging)

    -   `tests/` (PHPUnit \+ Playwright)

    -   `tools/` (release scripts)

    -   `composer.json` (PSR-4 autoload: `Joomla\\Component\\Nxpeasycart\\` → component `src/` trees)

    -   `.editorconfig`, `.php-cs-fixer.php` (PSR-12), `.phpstan.neon`, `.gitignore`

**Composer**

-   Require: `php ^8.0`, `joomla/joomla-cms` (dev for stubs), `psr/simple-cache`, `guzzlehttp/guzzle` (webhooks/http), `ramsey/uuid` (ids), `brick/money` (money ops, optional).

**CI hooks**

-   Run PHP-CS-Fixer (PSR-12), PHPStan (level 6+), PHPUnit on PR.

---

# **2\) Joomla extension skeleton**

**Manifest**: `nxpeasycart.xml` (Discover mode requires manifest names without the `com_` prefix)

-   Admin menu entries: Dashboard, Products, Orders, Customers, Coupons, Settings, Logs.

-   SQL install/update/uninstall scripts (migrations runner).

-   Update server URL (later).

**Namespaces**

-   `Nxp\EASYCART\Admin\…` for admin MVC \+ services

-   `Nxp\EASYCART\Site\…` for frontend controllers/models/views

-   `Nxp\EASYCART\Domain\…` for core domain (Orders, Products, Money, Tax, Shipping)

-   `Nxp\EASYCART\Infra\…` for persistence, gateways, cache, logging

-   `Nxp\EASYCART\Api\…` for JSON controllers (admin SPA \+ frontend ajax)

**Assets**

-   Register via Web Asset Manager:

    -   `nxp-admin` (IIFE bundle \+ CSS)

    -   `nxp-frontend` (small enhancement bundle)

**\_JEXEC** / ACL / CSRF\*\*

-   Every entrypoint: `_JEXEC` guard.

-   Controllers enforce `$user->authorise('core.manage', 'com_nxpeasycart')` etc.

-   Forms & API: `Session::checkToken()` \+ Joomla token header on XHR.

---

# **3\) Database model (prefix `#__nxp_easycart_…`)**

-   `#__nxp_easycart_products`
    `(id PK, slug, title, short_desc, long_desc, images JSON, active TINYINT, created, created_by, modified, modified_by)`

-   `#__nxp_easycart_variants`
    `(id PK, product_id FK, sku UNIQUE, price_cents INT, currency CHAR(3), stock INT, options JSON, weight DECIMAL(10,3), active)`

-   `#__nxp_easycart_categories`
    `(id PK, slug, title, parent_id, sort)`

-   `#__nxp_easycart_product_categories`
    `(product_id, category_id, PRIMARY KEY(product_id,category_id))`

-   `#__nxp_easycart_orders`
    `(id PK, order_no UNIQUE, user_id NULL, email, billing JSON, shipping JSON, subtotal_cents, tax_cents, shipping_cents, discount_cents, total_cents, currency, state ENUM('cart','pending','paid','fulfilled','refunded','canceled'), locale, created, modified)`

-   `#__nxp_easycart_order_items`
    `(id PK, order_id FK, product_id, variant_id, sku, title, qty, unit_price_cents, tax_rate DECIMAL(5,2), total_cents)`

-   `#__nxp_easycart_transactions`
    `(id PK, order_id FK, gateway, ext_id, status, amount_cents, payload JSON, event_idempotency_key VARCHAR(128), created)`

-   `#__nxp_easycart_coupons`
    `(id PK, code UNIQUE, type ENUM('percent','fixed'), value DECIMAL(10,2), min_total_cents, start, end, max_uses, times_used, active)`

-   `#__nxp_easycart_tax_rates`
    `(id PK, country, region, rate DECIMAL(5,2), inclusive TINYINT, priority)`

-   `#__nxp_easycart_shipping_rules`
    `(id PK, name, type ENUM('flat','free_over'), price_cents, threshold_cents, regions JSON, active)`

-   `#__nxp_easycart_audit`
    `(id PK, actor, action, object_type, object_id, meta JSON, created)`

-   `#__nxp_easycart_settings`
    `(key PK, value TEXT)` (for simple K/V; sensitive values via Joomla secrets)

-   `#__nxp_easycart_carts` (optional, if DB-backed cart)
    `(id PK UUID, user_id NULL, session_id, data JSON, updated)`

**Indexes**: slugs, sku, order_no, transactions (ext_id, idempotency), foreign keys for integrity.
**Charset**: `utf8mb4` \+ `utf8mb4_unicode_ci`.

---

# **3.1) Single-currency MVP guardrails (ship fast)**

To maximise “clarity + speed to first sale,” run the storefront in a single currency initially. Add these guardrails to avoid surprises and keep a clean upgrade path to multi-currency later:

1. Store settings (required)

-   Base currency: one required setting (e.g., `USD`).
-   Allowed currencies: hard-lock to `[base_currency]` only for MVP.

2. Consistency enforcement (validation)

-   Variants: `variants.currency` must equal `base_currency` when creating/updating products.
-   Orders: all orders must be created in `base_currency`; reject/normalize any other currency at cart/checkout.

3. UX copy (clarity for buyers)

-   Always display the currency symbol/code alongside prices and totals.
-   If you later show “estimated” converted prices, label them clearly as estimates and always settle the cart/order in the base currency.

Notes

-   This keeps pricing, accounting, taxes, and gateway setup simple for MVP.
-   The schema already stores `currency` on variants and orders, so you can add multi-currency later without data migrations (e.g., per-currency price lists or FX conversion with rate locking).

---

# **4\) Security blueprint (baked into dev tasks)**

-   **Input**: Read via `Factory::getApplication()->input` (JInput), filter types (`getString`, `getInt`, `getCmd`, `getArray` with input filters).

-   **Output**: Escape via `HTMLHelper::_('string.truncate', …)`, `Text::_`, or `htmlspecialchars` as needed.

-   **CSRF**: `JHtml::_('form.token')` in forms; `X-CSRF-Token`/`X-Joomla-Token` for APIs.

-   **Sessions**: use Joomla session for cart id; optionally mirror to `#__nxp_easycart_carts`.

-   **HTTPS**: middleware/guard on checkout (redirect if not HTTPS).

-   **Permissions**: Joomla ACL assets per controller/task; deny by default.

-   **Headers**: send `Content-Security-Policy` (no inline where possible), `SameSite=Lax` cookies; secure cookies.

-   **Payment**: tokenized/hosted (Stripe/PayPal) → PCI SAQ-A scope.

-   **Idempotency**: store and check gateway event ids \+ app-generated idempotency keys.

-   **Rate limiting**: soft limit cart updates & coupon attempts (cache counters).

-   **Anti-XSS in descriptions**: restrict allowed HTML (Joomla filter ruleset).

---

# **5\) Domain logic & state machine**

**Order states**: `cart → pending → paid → fulfilled → refunded | canceled`

-   Transition guards; no shipping before `paid`.

-   All transitions logged in `#__nxp_easycart_audit`.

**Rounding rules**

-   Use minor units (`*_cents INT`).

-   Round at **line item**, then sum; document discount-tax interaction.

**Inventory**

-   Reserve on `pending` with timeout **or** decrement on `paid`. MVP: decrement on `paid` to avoid race complexity.

---

# **6\) Admin SPA (Vue 3 as IIFE)**

**Build**

-   Vite → IIFE target, single ES6 entry `admin-main.js` (no TypeScript).

-   Code split per route (Dashboard, Products, Orders, Settings).

-   Register bundle in Web Asset Manager (`media/com_nxpeasycart/js/admin.iife.js`).

**Mount**

-   Admin layout view `administrator/components/com_nxpeasycart/tmpl/app/default.php` with a `<div id="nxp-admin-app">` \+ token meta.

**API (admin)**

-   JSON controllers under `administrator/components/com_nxpeasycart/src/Controller/Api/*Controller.php`

    -   `GET /api/products`, `POST /api/products`, `PATCH /api/products/{id}`, etc.

    -   Enforce ACL \+ CSRF; return RFC-7807-style errors.

**Features**

-   Onboarding wizard

-   Products CRUD (variants, images, categories)

-   Orders list/detail (transactions timeline)f

-   Coupons/Taxes/Shipping rules

– Settings (gateways, email templates, currencies) - Base currency (single-currency MVP)

-   Logs (failed webhooks, audit)

---

# **7\) Frontend (server-rendered \+ Vue islands)**

**Routing**

-   `index.php?option=com_nxpeasycart&view=product&slug=…`

-   `view=category`, `view=cart`, `view=checkout`, `view=order&no=…`

**SEO**

-   Canonical URLs; breadcrumbs; `Schema.org` (`Product`, `Offer`, `BreadcrumbList`).

-   OpenGraph/Twitter tags; image alt text.

**Templates (PHP) with enhancements**

-   Product page: server price/stock; Vue island for variant picker & live price.

-   Mini cart: small Vue component; Cart/Checkout forms server-rendered with progressive enhancement.

-   Cart persistence: session id \+ cookie; sync with `#__nxp_easycart_carts` if enabled.

---

## **8\) Payments (Stripe \+ PayPal)**

**\[Gateway interface, webhook handling, idempotency, retry logic\]**

---

## **9\) Caching & performance**

**\[PSR-16 cache, DB indices, session persistence, optimization measures\]**

---

## **10\) GDPR & privacy**

**\[Export/anonymize, consent handling, DPA references\]**

---

## **11\) Email & docs**

**\[Email templates, SMTP integration, onboarding docs, troubleshooting\]**

---

## **12\) Testing strategy**

**\[Unit, integration, API, E2E, manual security checks\]**

---

## **13\) Milestones & deliverables**

**\[M0–M5 schedule and expected outputs\]**

---

## **14\) Definitions of Done (DoD)**

**\[Security, SEO, Payments, Performance, GDPR — as detailed above\]**

---

## **15\) Example code stubs**

**\[Composer autoload \+ ProductController \+ OrderState example\]**

---

## **16\) Packaging & release**

**\[Packaging steps, versioning, changelog, update server\]**

---

## **17\) Risk register & mitigations**

**\[Gateway misconfig, webhook failures, double charge, performance, conflicts\]**

---

## **18\) Next actions**

**\[Scaffold → CRUD → Cart/Orders → Payments → SEO → GDPR — actionable checklist\]**

---

# **20) Composer & packaging policy**

-   Runtime dependencies are limited to the component’s needs (`psr/simple-cache`, `guzzlehttp/guzzle`, `ramsey/uuid`, `brick/money`).
-   `joomla/joomla-cms` stays in `require-dev`—install it locally for tooling, but exclude it from release artifacts (`composer install --no-dev --optimize-autoloader`).
-   During development, the component bootstrapper will look for `vendor/autoload.php` in the component tree and fall back to the repository root, so no additional vendor symlinks are required. When building a release, copy the optimised `vendor/` directory into the package.
-   Symlink the language files into Joomla (`administrator/language/en-GB/com_nxpeasycart*.ini` and `language/en-GB/com_nxpeasycart*.ini`) to avoid missing translations when working from a symlinked component.
-   For deployment, generate a clean build (`composer install --no-dev`, `npm run build:admin`) and package the trimmed `vendor/` directory alongside component files. Never execute Composer inside `/var/www/html/j5.loc`.
-   The component manifest is stored as `administrator/components/com_nxpeasycart/nxpeasycart.xml` (filename without the `com_` prefix) so Joomla Discover can detect it. After deploying files, run **System → Discover** (or `php cli/joomla.php extension:discover`) and then complete the install from the Discover list.

---

# **19\) Repository, environment & operational policy ✅**

**Local development environment**

-   **Local Joomla 5 install path:**
    **`/var/www/html/j5.loc`**

-   **Local site URL:**
    **`http://j5.loc`**

-   **Credentials:**
    **Stored securely in `configuration.php` (already present in local Joomla setup).**
    **⚠️ Do not expose or hardcode credentials elsewhere.**

**Working scope**

-   **You work only in:**

    -   **The local repo (the component’s development directory)**

    -   **The Joomla site at `/var/www/html/j5.loc`**

-   **Do not roam the filesystem or access directories outside these paths.**

-   **You can select and perform CRUD only inside `/var/www/html/j5.loc` using Joomla APIs or DB connections defined in `configuration.php`.**

**Git & versioning**

-   **Local repo is symlinked to Joomla (`administrator/components/com_nxpeasycart` and `components/com_nxpeasycart`).**

-   **Commit policy:**

    -   **Commit only after major milestones (M0–M5).**

    -   **Use meaningful commit messages summarizing milestone completion (e.g., _“M2 complete — Cart & Orders implemented”_).**

    -   **Each commit should correspond to a documented, verified milestone.**

**Documentation discipline**

-   **Maintain a `README.md` at the repo root with:**

    -   **Overview**

    -   **Install instructions**

    -   **Dependencies**

    -   **Changelog summary**

-   **Add/update inline developer docs (`/docs/`) after each major milestone:**

    -   **Explain new architecture blocks**

    -   **Reference DB schema changes**

    -   **API endpoints (admin & site)**

    -   **Security checklist updates**

-   **Ensure each documented step is pushed to the repo with the corresponding commit.**
