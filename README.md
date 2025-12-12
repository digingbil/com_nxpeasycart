# NXP Easy Cart

NXP Easy Cart is a Joomla 5 component that prioritises a 10-minute setup, a clean administrator experience, and a bulletproof checkout. The project follows the guidance in `INSTRUCTIONS.md` with a focus on security, reliability, and simplicity.

## Overview

- Namespaced Joomla MVC structure for administrator (`Joomla\\Component\\Nxpeasycart\\Administrator`) and site (`Joomla\\Component\\Nxpeasycart\\Site`) applications, backed by a custom `EasyCartMVCFactory` so the dispatcher resolves controllers on both clients.
- Install/uninstall SQL covering the core NXP data model (`#__nxp_easycart_*` tables).
- Service provider bootstrap for Joomla's dependency injection container (no legacy `com_nxpeasycart.php` entry file).
- Cart and order services wired through the DI container, enforcing single-currency rules via the configuration helper and hydrating JSON billing/shipping payloads.
- Orders workspace in the admin SPA for filtering, viewing, and transitioning order state against the JSON API.
- JSON product API endpoints with ACL + CSRF enforcement powering the admin SPA.
- Orders JSON API backed by `OrderService` for listing, creation, and state transitions with totals + line items.
- Vue-based admin panel for catalogue management (products, variants, categories, images) plus storefront templates ready for Vue “islands”; the site dispatcher now renders live product detail pages with SEO metadata and JSON-LD.
- Storefront category, cart, checkout, and order confirmation views ship with progressive Vue islands leveraging cached shipping/tax lookups.
- Payment gateway manager negotiates hosted Stripe/PayPal sessions with webhook idempotency and automatic order confirmation email delivery.
- Admin onboarding wizard surfaces the launch checklist with a guided modal that can be reopened from the dashboard shell.
- GDPR service exposes export/anonymise endpoints and documentation alongside a component risk register and packaging checklist.
- JSON controllers share a base responder that streams the encoded payload to the client, ensuring admin API requests always return proper JSON bodies.
- Admin settings workspace manages store defaults, tax rates, and shipping rules through dedicated services and JSON endpoints consumed by the Vue SPA.
- Logs workspace exposes the audit trail stored in `#__nxp_easycart_audit`, helping administrators review order state changes and other lifecycle events.
- Admin orders fallback preloads data for the Vue SPA and orders API endpoints are emitted with absolute administrator URLs to prevent accidental front-end routing.
- Storefront view now gracefully renders the onboarding placeholder when no product slug is supplied instead of throwing a 404, keeping the landing experience clean during early development.

All custom CSS classes, data attributes, and CSS variables emitted by the component are prefixed with `nxp-ec-` / `--nxp-ec-` to keep them isolated from host templates.

### Recent Enhancements

- **Digital products & downloads (v0.1.13)**: Products can be marked digital; variants include an `is_digital` flag; digital files upload/list/delete via the admin product editor; settings tab for download limits/expiry/storage path/auto-fulfill; digital-only orders skip shipping and can auto-fulfill on payment; storefront checkout hides shipping for digital-only carts; order status page + confirmation email show tokenised download links with remaining count and expiry. Files are stored under `/media/com_nxpeasycart/downloads` with `.htaccess` deny and streamed via `DownloadController`.
- **Digital file upload security (v0.1.14)**: Comprehensive file validation and server protection for digital product uploads:
    - **47 allowed file types**: Predefined whitelist covering archives (zip, rar, 7z, tar, gz, tgz), audio (mp3, wav, flac), video (mp4, webm, mov, avi, mkv), images (jpg, png, gif, svg, webp, avif), documents (pdf, txt, rtf, doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp, csv), ebooks (epub, mobi), and installers (exe, msi, deb, rpm, dmg, app, pkg, apk, ipa).
    - **Configurable file types**: Settings → Digital Products tab includes category-grouped checkboxes (Select All/None) plus a custom extensions field for exotic types. Installers are disabled by default for security.
    - **Size validation**: Configurable max file size (default 200MB) with user-friendly error messages.
    - **Dual validation**: Both file extension and MIME type are validated against the whitelist.
    - **Server protection**: Both Apache (.htaccess) and Nginx (nginx.conf) protection files are written to the storage directory.
    - **Product type integration**: Digital Files tab in product editor is only visible when Product Type is set to "Digital".
    - See `docs/digital-products.md` for complete documentation.
- **Admin UI fixes (v0.1.14)**:
    - Fixed missing `decodePayload()` method in `DigitalfilesController` that caused errors when deleting digital files.
    - Fixed CSS overflow issue where `.nxp-ec-digital-row` elements bled beyond parent container in the product editor modal.
- **Canonical product routing** now records a primary category on each product and generates all product URLs against that primary category path (e.g., `/shop/category/hifi/amplifiers/product-slug`), eliminating duplicate SEO paths when items belong to multiple categories.
- **Cart quantity persistence** now persists quantity changes to the database via a new `cart.update` endpoint (`CartController::update()`). When users modify item quantities on the cart page, the changes are saved immediately with optimistic UI updates and server-side stock validation. The checkout page refreshes cart data on mount via `onMounted(refreshCart)` and listens for `nxp-cart:updated` events, ensuring the Order Summary always reflects the latest cart state. Stock validation prevents over-ordering and displays user-friendly alerts when requested quantities exceed available inventory.
- **Categories workspace (admin)** surfaces a dedicated CRUD panel with slug validation, usage counts, and an indented tree so parent/child relationships are obvious at a glance. The parent selector now prevents loops by removing the current node and its descendants.
- **Product editor category selector** consumes the categories API, letting merchants multi-select existing categories and create new ones inline; the payload now posts normalised `{id, title, slug}` entries so product/category mappings stay deduplicated.
- **Database binding hardening** replaced every inline `->bind()` cast with real variables, eliminating the "Argument #2 ($value) could not be passed by reference" fatal that previously appeared during saves.
- **Shop landing menu type** now loads the custom `RootCategories` field via the field prefix and routes cleanly to `/shop` without the `?view=landing` fallback after wiring the router factory.
- **Shop landing runtime fixes** render the fallback hero/search/categories markup even before the Vue island hydrates, format money with locale-safe `NumberFormatter`, and ensure the view loads correctly regardless of autoload location.
- **Template-aware storefront** now detects the active Joomla template, exposes adapter tokens, and drives CSS variables so the landing page, buttons, and call-to-actions inherit the host theme's palette and utility classes.
- **Storefront cart flow** introduces clean `/shop/<view>/<slug>` routing, restores category rendering on Helix/T4 templates, and upgrades the product detail page to a Vue-powered add-to-cart experience that posts to the new cart controller while a companion cart summary module keeps the header pill in sync.
- **Visual customization settings** exposes a dedicated "Visual" tab in the admin settings panel where users can override storefront colors (primary, text, surface, border, muted) with live preview. The system automatically detects template defaults from Cassiopeia, Helix Ultimate, JA Purity IV, or falls back to neutral defaults. Empty fields use the detected template colors; user overrides are applied via `TemplateAdapter::applyUserOverrides()` and persist in the `#__nxp_easycart_settings` table. Color pickers show actual template defaults as placeholders for zero-confusion customization.
- **Storefront UX polish** links category/landing card media to product detail, adds quick add-to-cart on cards when a primary variant exists, carries TemplateAdapter shadows/radii into cart summary, product descriptions, and variants, and smooths add-to-cart hover effects for cross-template consistency.
- **Admin panel UX polish** converts the order/customer/coupon sidebars into modal dialogs, tightens the iconography across action buttons (FA6), and adds a one-click Active/Inactive toggle for products that reuses the existing update endpoint while keeping validation intact.
- **Coupon system** (`applyCoupon` / `removeCoupon` endpoints) now correctly handles JSON body payloads from the Vue checkout island. Key fixes:
    - CSRF validation upgraded to `Session::checkToken('request')` which validates both form tokens and `X-CSRF-Token` headers sent by the API client.
    - JSON body parsing added via `$input->json->getRaw()` to extract coupon codes from `postJson()` requests (falls back to form/query params for backwards compatibility).
    - Cart subtotal calculation fixed to compute from raw item data (`unit_price_cents * qty`) rather than the non-existent `total_cents` field in unhydrated cart items.
    - Persistence now uses `$carts->persist()` (CartService) instead of the non-existent `$session->persist()` on CartSessionService.
    - Response now uses `$presenter->hydrate()` to return the full cart with summary, replacing the non-existent `summarise()` method.
- **Security and data consistency**: storefront cart/payment endpoints now accept CSRF via form token _or_ `X-CSRF-Token`, hide internal exception messages, and pass server locale/currency into all islands to keep money formatting identical between PHP and JS.
- **Shop landing CTA toggle**: the Shop Landing menu item now includes a “Show primary CTA” toggle; when disabled, the hero CTA button is omitted on the storefront landing page while keeping the label/link settings available for later use.
- **Storefront islands refactor**: the monolithic site bundle is split into per-island modules, lazy-mounted via `IntersectionObserver`, and backed by a shared API client + utilities (CSRF injection, money formatting, retries) to reduce TTI on pages that only need one island.
- **TemplateAdapter caching**: template token resolution is memoised per request to avoid repeated palette parsing when multiple views touch template defaults.
- **Hashed site bundle**: Vite now emits hashed JS/CSS with a manifest; `joomla.asset.json` points at the hashed entry with `version: auto` for reliable cache-busting in production.
- **Order status & tracking**: checkout now emits tokenised status links (emails + redirects), the storefront exposes public status pages and an authenticated “My Orders” list, and the admin Orders panel surfaces copyable status links plus tracking fields that append fulfilment events.
- **Cart module asset loading fix**: `mod_nxpeasycart_cart` now uses the centralised `SiteAssetHelper` to register site JS/CSS, ensuring the cart summary island hydrates correctly on non-component pages (e.g. homepage) where only the module is present.
- **Security audit fixes (Critical)**: Resolved four critical vulnerabilities identified during security audit:
    - **XSS vulnerability eliminated**: Replaced `v-html` in checkout success message with safe Vue text interpolation, preventing script injection via order numbers.
    - **Stripe webhook security enforced**: Made `webhook_secret` mandatory; webhooks without valid signatures are now rejected.
    - **PayPal webhook verification implemented**: Added complete signature validation using PayPal's verification API; `webhook_id` is now mandatory.
    - **Price tampering vulnerability fixed**: Checkout and cart display now ALWAYS recalculate prices from database, never trusting cart-stored prices. Prevents attackers from manipulating cart data to purchase products at arbitrary prices.
    - **Coupon discount tampering fixed**: Coupon discounts now ALWAYS calculated from database prices, preventing revenue loss from cart price manipulation. Both coupon application and checkout recalculate discounts from authoritative database values.
    - See `docs/security-audit-fixes.md`, `docs/security-price-tampering-fix.md`, and `docs/security-coupon-discount-fix.md` for complete details, testing procedures, and deployment requirements.
- **Email templates**: Transactional email system now supports three order lifecycle notifications:
    - **order_confirmation**: Sent immediately after successful payment capture.
    - **order_shipped**: Triggered when admin adds tracking info or transitions order to 'fulfilled' state; includes carrier, tracking number, and tracking URL.
    - **order_refunded**: Triggered when order transitions to 'refunded' state; includes refund amount and support contact.
    - Templates live in `administrator/components/com_nxpeasycart/templates/email/` and use Joomla's configured mailer.
    - **Full order breakdown**: All email templates now display complete pricing (subtotal, tax with inclusive/exclusive labels, shipping, discount, total) for transparency and consistency across customer communications.
    - **Fully translatable**: All static strings use Joomla language constants (`COM_NXPEASYCART_EMAIL_*`) for easy localisation.
- **Email sending controls**: Administrators can now control how order notification emails are sent:
    - **Auto-send setting**: New "Auto-send order emails" checkbox in Settings → General (default: off). When enabled, shipped/refunded emails are sent automatically on state transitions.
    - **Manual send buttons**: Order details panel shows "Send shipped email" / "Send refunded email" buttons for fulfilled/refunded orders, allowing manual email dispatch regardless of auto-send setting.
    - **Re-send capability**: Buttons show "Re-send" label when an email of that type was previously sent (tracked via audit trail).
    - **Audit logging**: All manually-sent emails are logged with `manual: true` context for compliance tracking.
- **GDPR compliance (Article 17 & 20)**: Built-in data export and anonymisation endpoints:
    - `GdprService::exportByEmail()` returns all orders, line items, and transactions for a given email (JSON format for data portability).
    - `GdprService::anonymiseByEmail()` replaces PII (email, billing, shipping, tracking) with anonymous hash while preserving order data for accounting.
    - Admin API: `GET ?option=com_nxpeasycart&task=api.gdpr.export&email=...` (requires `core.manage`), `POST ?option=com_nxpeasycart&task=api.gdpr.anonymise` with JSON `{"email":"..."}` (requires `core.admin` + CSRF).
    - See `docs/gdpr.md` for implementation details and compliance checklist.
- **Localized currency formatting**: Prices now display according to locale conventions (e.g., `1.390,00 ден.` for Macedonian, `€1,234.56` for US English):
    - **Automatic locale detection**: By default, the formatting locale is derived from Joomla's site language setting.
    - **Store-level override**: Administrators can set a custom "Price display locale" in Settings → General to force a specific format regardless of site language (e.g., `mk-MK` for Macedonian formatting).
    - **Centralised MoneyHelper**: All price formatting across PHP (templates, models, emails, invoices) and JavaScript (Vue islands) now flows through a single `MoneyHelper::format()` method that respects the locale resolution order.
    - **Both locale formats accepted**: Settings accept both Joomla-style (`mk-MK`) and ICU-style (`mk_MK`) locale codes.
    - See `docs/currency-localization.md` for implementation details.

### Performance Optimizations (Admin SPA)

- **Cache-first data strategy** with 5-minute TTL across all admin composables (Products, Orders, Categories, Settings, Coupons, Tax, Shipping, Customers, Logs, Dashboard) reduces redundant API calls by 60-80% and enables instant tab switching when returning to previously loaded panels.
- **Performance tracking** logs fetch durations and cache hit/miss ratios to the console (`[NXP EC Performance]` and `[NXP EC Cache]` markers) for debugging slow endpoints and monitoring optimization effectiveness.
- **Skeleton loaders** replace blank canvases during data loads across all major panels (Products, Orders, Categories, Customers, Logs) with smooth pulse animations, providing immediate visual feedback and professional loading states.
- **Last updated timestamps** appear at the bottom of each panel showing relative time ("2 minutes ago") or full timestamp, confirming data freshness and cache age to administrators.
- **Layout shift elimination** on the admin header prevents the onboarding button from causing reflows by using CSS Grid layout and `v-show` instead of `v-if`, achieving near-zero Cumulative Layout Shift (CLS < 0.001).
- **Prefetch utilities** (optional) enable background loading of adjacent panels based on navigation patterns, preloading likely-next destinations after the main panel finishes loading.

See `docs/performance-optimization.md` for complete implementation details, cache configuration examples, and performance metrics.

### Mobile-Responsive Admin UI

The admin panel is fully responsive and optimized for tablet and mobile devices:

- **Card-based tables**: At ≤768px, all data tables transform into mobile-friendly card layouts with labeled fields, eliminating horizontal scrolling.
- **Touch-friendly targets**: All buttons, checkboxes, and form inputs meet the 44px minimum touch target size recommended by iOS Human Interface Guidelines.
- **Scrollable navigation**: Tab navigation becomes horizontally scrollable on mobile with momentum scrolling.
- **Full-screen modals**: Order details, product editor, and other modals expand to full-screen on small devices.
- **Stacked layouts**: Panel headers, action buttons, and form controls stack vertically on mobile for easier interaction.
- **iOS zoom prevention**: Form inputs use 16px font size to prevent Safari auto-zoom on focus.

See `docs/mobile-responsive-admin.md` for complete implementation details, breakpoint specifications, and testing guidelines.

### Currency decision (MVP)

For speed to first sale, the storefront ships as single-currency by default:

- Base currency is a required setting; allowed currencies are locked to the base for MVP.
- Variants and orders must use the base currency (validated at product save and checkout).
- If display-only currency estimates are introduced later, they must be clearly labelled as estimates; all carts and orders settle in the base currency.

See “3.1) Single-currency MVP guardrails (ship fast)” in `INSTRUCTIONS.md` for details and the upgrade path to full multi-currency.

## Installation (local development)

1. Ensure the repository is symlinked into your Joomla instance (`/var/www/html/j5.loc`) under `administrator/components/com_nxpeasycart` and `components/com_nxpeasycart`.
2. Install PHP dependencies locally:
    ```bash
    composer install
    ```
3. Build the trimmed runtime vendor that Joomla should load (prevents the dev autoloader from hijacking the CMS) and keep dev stubs out of Joomla:

    ```bash
    php tools/build-runtime-vendor.php
    ```

    - Never copy the repo root `vendor/` into `administrator/components/com_nxpeasycart` or the site component. The root vendor contains `joomla/joomla-cms` for dev tooling only.
    - Keep the repo-root vendor intact for IDE/PHPStan/PHPUnit, but **only** the trimmed/runtime vendor should live under the Joomla component paths.
    - Run `php tools/guard-runtime-vendor.php` to verify no `joomla/joomla-cms` stubs slipped into the component vendor paths.

4. Install Node dependencies for the admin SPA toolchain:
    ```bash
    npm install
    ```
5. Install the component through the Joomla extension manager or via `Discover`.
6. After installation, access the admin menu entry **NXP Easy Cart** to verify the placeholder dashboard renders.
7. Open **System → Manage → Extensions → NXP Easy Cart → Options** and set the store base currency (default `USD`); product variants are validated against this single-currency guardrail.
8. Joomla loads runtime dependencies from `administrator/components/com_nxpeasycart/vendor`. Rebuild this folder whenever composer.json changes by re-running `php tools/build-runtime-vendor.php`; avoid pointing Joomla at the repo-root `vendor/` (it includes dev-only packages like `joomla/joomla-cms`).

## Tooling & dependencies

- PHP 8.0+ with `psr/simple-cache`, `guzzlehttp/guzzle`, `ramsey/uuid`, and `brick/money` as runtime deps.
- `joomla/joomla-cms` is required _only_ for local tooling (listed in `require-dev`) so Composer can expose Joomla classes to IDEs and PHPStan; it must be excluded from release builds (`composer install --no-dev`).
- Development tooling: PHPStan (level 6), PHP-CS-Fixer (PSR-12), PHPUnit 10.
- `.phpstan.neon` and `.php-cs-fixer.php` are preconfigured; run `composer lint`, `composer fix`, and `composer stan` as needed.
- Vite configuration lives at `build/vite.config.admin.js`; use `npm run dev:admin` or `npm run build:admin` to compile the admin bundle into `media/com_nxpeasycart/js/admin.iife.js` and accompanying CSS. Run `npm run build:site` to generate the storefront island bundle (`media/com_nxpeasycart/js/site.iife.js`).
- Vue 3 drives the admin SPA (bundled as an IIFE); install dependencies with `npm install` before running any build script.
- All frontend code (including Vue islands) must be authored in plain JavaScript—TypeScript is not permitted.
- During development the Joomla Web Asset Manager loads the admin bundle via `media/com_nxpeasycart/joomla.asset.json`. If Joomla skips the manifest (common with symlinked installs) the view now queues the JS/CSS explicitly; a hard refresh should always load `media/com_nxpeasycart/js/admin.iife.js`.
- When debugging SPA boot issues, open DevTools → Network and verify the admin bundle returns HTTP 200 and the console logs `[NXP Easy Cart] Booting admin SPA`. Any 403/404 responses point to ACL or token misconfiguration in the JSON controllers.

## Packaging / deployment

- Local development uses the root `vendor/` directory directly for tooling, but Joomla should load the trimmed runtime tree under `administrator/components/com_nxpeasycart/vendor` (generated via `php tools/build-runtime-vendor.php`). When packaging for release, copy that trimmed `vendor/` folder into the bundle alongside the component files.
- Before packaging or copying files into Joomla, run `php tools/guard-runtime-vendor.php` to ensure no `joomla/joomla-cms` stubs are present in the runtime vendor.
- To produce an installable ZIP, run `composer install --no-dev --optimize-autoloader` in a clean workspace, build frontend assets (`npm run build:admin`), and include the trimmed `vendor/` directory plus component files in the package.
- Do **not** run Composer inside the live Joomla tree; copy or mirror the prepared `vendor/` folder alongside the component when deploying.
- The manifest living at `administrator/components/com_nxpeasycart/nxpeasycart.xml` follows Joomla’s discovery convention (no `com_` prefix in the filename). After copying the component into a site, use **System → Discover** or `php cli/joomla.php extension:discover` to register it, then complete the install from that screen. Joomla 5’s DI bootstrapping means no administrator entry script is required.
- A lightweight installer script (`administrator/components/com_nxpeasycart/script.php`) replays the base schema during install/update/discover so the `#__nxp_easycart_*` tables are always provisioned.
- Language strings are split by client: administrator strings live in `administrator/language/en-GB/com_nxpeasycart*.ini`, storefront strings live in `language/en-GB/com_nxpeasycart.ini`. Add new backend strings to the admin file (use `.sys.ini` only for install/discover labels) and keep storefront copy in the site file. When developing via symlinks, point Joomla to these paths (e.g. `/var/www/html/j5.loc/administrator/language/en-GB/com_nxpeasycart*.ini` and `/var/www/html/j5.loc/language/en-GB/com_nxpeasycart.ini`) so translations resolve.

## Admin SPA build

- `media/com_nxpeasycart/src/admin-main.js` bootstraps the Vue 3 admin shell which renders a products workspace powered by the `/api.products.*` endpoints.
- CSRF tokens and API endpoints are exposed via `data-*` attributes in the admin template for the SPA to consume.
- A placeholder `media/com_nxpeasycart/js/admin.iife.js` ships with the repo so the asset loads prior to the first Vite build; running `npm run build:admin` overwrites it with the compiled Vue app. The bundled asset registers under `com_nxpeasycart.admin` and carries its CSS dependency automatically.
- Current build output: **246.86 kB** raw (**68.09 kB** gzipped) for admin.iife.js, **18.33 kB** raw (**3.83 kB** gzipped) for admin.css.
- Vue single-file components (`src/app/App.vue`, `src/app/components`) and composables (`src/app/composables`) keep the admin bundle modular, with `useTranslations` delegating to `Joomla.Text` instead of hard-coded dictionaries.
- All admin composables now include performance tracking (`usePerformance`) and cache-first data strategies with configurable TTL, logging fetch times and cache hit ratios to the console for debugging and optimization.
- The dashboard surface now normalises translation placeholders, applies currency-aware metrics, renders Font Awesome checklist icons, and links directly into the SPA settings workspace for base-currency updates. The onboarding wizard can be reopened from the shell header.
- The admin products panel now includes create/edit/delete flows with image management, category tagging, and variant tables, backed by shared composables and the JSON API.
- Component configuration exposes the single-currency guardrail; the admin editor reflects the configured currency and server-side validation ensures every variant uses it.
- Payments tab manages Stripe/PayPal credentials via the `usePayments` composable, persisting masked secrets through `PaymentGatewayService` and validating against the webhook-capable gateway manager.
- Vue SPA assets are registered via `media/com_nxpeasycart/joomla.asset.json`; ensure the manifest is discovered (`Joomla\CMS\Helper\WebAssetHelper::getRegistry()->addRegistryFile(...)`) or manually import it to avoid "Unknown asset" errors during development.
- Runtime autoload guardrails (production safety): inside Joomla we do **not** fall back to the repo-root `vendor/` (which contains dev-only `joomla/joomla-cms` stubs) when bootstrapping cart services. `CartSessionService` and `CartController` only load autoloaders from the packaged component vendors (admin/site) or `JPATH_ROOT/vendor`; the repo vendor is used only in CLI/dev contexts when Joomla isn’t running. This prevents T4/template breakage caused by loading dev stubs in production.

## Storefront cart

- Frontend requests resolve the active cart through `CartSessionService`, which ties the Joomla session to the `#__nxp_easycart_carts` table and guarantees the payload uses the configured base currency.
- The session helper exposes the hydrated cart on the application input (`com_nxpeasycart.cart`) so upcoming cart/checkout views can consume a consistent structure.
- **Cart quantity updates** are persisted immediately via `CartController::update()`, which accepts `variant_id`, `product_id`, and `qty` parameters. Stock availability is validated server-side before persisting changes. On successful update, the cart island dispatches a `nxp-cart:updated` custom event that other components (checkout, cart summary module) listen for to stay synchronized. If stock is insufficient, users receive an alert and the cart refreshes to show the actual available quantity.
- The default storefront view is now the catalogue grid: `DisplayController` targets the category view, showing "All products" with category filters and progressive enhancement via the site bundle.
- Product editor exposes a "Featured" toggle; any flagged products surface in the landing page spotlight row, with recent additions filling the remaining sections automatically.
- Category menu chips allow quick filtering between taxonomy slugs, while fallbacks keep navigation and product tiles rendering without JavaScript.
- Checkout integrates Stripe/PayPal hosted sessions when configured; fallback direct order creation remains for offline capture. Webhooks hydrate transactions, decrement inventory on `paid`, and trigger confirmation email templates. The checkout page refreshes cart data on mount and subscribes to `nxp-cart:updated` events, ensuring quantity changes made on the cart page are reflected immediately when navigating to checkout.
- Payment webhook endpoints are exposed at `index.php?option=com_nxpeasycart&task=webhook.stripe` and `…task=webhook.paypal`, each funneled through `PaymentGatewayManager` with idempotency safeguards and audit logging.
- Order confirmation emails use PHP templates under `administrator/components/com_nxpeasycart/templates/email/`, delivered via Joomla's configured mailer after successful payment capture.

- Admin orders view now includes a lightweight PHP fallback table so seeded data is visible even before the Vue bundle is rebuilt; the fallback strips itself once the SPA mounts.
- SPA endpoints now use absolute admin URLs plus scoped query merging, avoiding `Invalid controller class` errors and keeping detail panels in sync with preloaded data.
- The component’s product view uses the existing placeholder copy whenever a specific product cannot be resolved, so hitting `/index.php?option=com_nxpeasycart` stays within the guided onboarding flow.

## Asset builds

- Site/admin bundles are built with Vite from `media/com_nxpeasycart/src`. After `npm run build:site`, the hashed filename must be written to `media/com_nxpeasycart/joomla.asset.json` so Joomla loads the newest bundle. This is automated via the `postbuild:site` hook, which runs `npm run sync:assets` (see `tools/sync-asset-manifest.js`). If you copy files manually or skip the hook, run `npm run sync:assets` to avoid stale scripts.

## Plugin Events (Extensibility)

The component dispatches Joomla plugin events at key points in the order lifecycle, enabling third-party integrations without modifying core code. System plugins can subscribe to these events for notifications, CRM sync, analytics, or custom validation.

| Event                                | Trigger Point                                   | Arguments                                  |
| ------------------------------------ | ----------------------------------------------- | ------------------------------------------ |
| `onNxpEasycartBeforeCheckout`        | Before order creation during checkout           | `cart`, `payload`, `gateway`               |
| `onNxpEasycartAfterOrderCreate`      | After a new order is saved                      | `order`, `gateway`                         |
| `onNxpEasycartAfterOrderStateChange` | After an order state transition                 | `order`, `fromState`, `toState`, `actorId` |
| `onNxpEasycartAfterPaymentComplete`  | After successful payment confirmation (webhook) | `order`, `transaction`, `gateway`          |

### Example Plugin

```php
// plugins/system/myeasycart/myeasycart.php
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemMyeasycart extends CMSPlugin
{
    public function onNxpEasycartAfterOrderCreate($event)
    {
        $order = $event->getArgument('order');
        // Send to CRM, analytics, etc.
    }

    public function onNxpEasycartBeforeCheckout($event)
    {
        $cart = $event->getArgument('cart');
        // Validate or throw RuntimeException to block checkout
    }

    public function onNxpEasycartAfterOrderStateChange($event)
    {
        $order = $event->getArgument('order');
        $toState = $event->getArgument('toState');

        if ($toState === 'fulfilled') {
            // Trigger shipping notification
        }
    }

    public function onNxpEasycartAfterPaymentComplete($event)
    {
        $order = $event->getArgument('order');
        $transaction = $event->getArgument('transaction');
        // Update inventory system, send webhook to fulfillment, etc.
    }
}
```

Events are dispatched via `EasycartEventDispatcher` (see `src/Event/EasycartEventDispatcher.php`). All event handlers are non-blocking—exceptions in plugins are swallowed to prevent blocking core functionality.

## Testing & Security

- See `docs/testing.md` for the current automation blueprint covering PHPUnit (unit/integration), API contract runs, Vue unit tests, and Playwright E2E journeys.
- **Comprehensive unit test suite** covers critical services:
    - `OrderStateMachineTest` (8 tests): State transitions, invalid states, terminal states, audit logging.
    - `RateLimiterTest` (7 tests): Hit counting, window expiry, memory fallback, edge cases.
    - `CartServiceTest` (8 tests): Persistence, loading, currency migration, session handling.
    - `CheckoutSecurityTest` (10 tests): Price recalculation, tax accuracy, total validation, integer overflow, concurrent checkout.
    - `GdprServiceTest` (10 tests): Email validation, anonymisation, PII removal, data portability compliance.
- Run tests with: `./vendor/bin/phpunit tests/Unit`
- Security audit fixes and webhook configuration requirements are documented in `docs/security-audit-fixes.md`.
- GDPR compliance is documented in `docs/gdpr.md`.
- Risk register lives at `docs/risk-register.md`; packaging workflow is documented in `docs/packaging.md`.

## Changelog summary

- **M0 – Component scaffold**: Initial manifest, service provider, admin/site controllers & views, database schema stubs, developer tooling baseline, JSON product API, and Vite-based admin bundler skeleton.
- **M1 – Products CRUD**: Admin SPA delivers full product management (images, categories, variants) with validated JSON endpoints and improved UX.
- **Bugfix – Admin product save routing**: API router now preserves dotted task names (`api.products.store` etc.), restoring the ability to create products from the Vue admin.
- **Security & rate limiting (Phase 1 MVP)**: Implemented `RateLimiter` service with PSR-16 cache backing, wired into cart and payment controllers with configurable limits per IP, email, and session. Admin settings panel now includes Security tab for managing rate limits with proper persistence. Fixed missing `checkout_session_limit` field that caused settings to reset on save, and corrected `show()` method to read stored values directly instead of re-normalizing them.
- **Security audit fixes (Critical vulnerabilities resolved)**: Fixed XSS vulnerability in checkout success message by replacing `v-html` with safe text interpolation. Enforced mandatory webhook signature validation for Stripe (webhook_secret required) and implemented complete PayPal webhook verification using their API (webhook_id required). Fixed price tampering vulnerability by implementing complete price recalculation from database at checkout and cart display. Fixed coupon discount tampering by recalculating discounts from database prices at both coupon application and checkout. Cart data is never trusted for pricing or discount calculations - database is the single source of truth. All attack vectors eliminated. See `docs/security-audit-fixes.md`, `docs/security-price-tampering-fix.md`, and `docs/security-coupon-discount-fix.md`.
- **PayPal webhook & payment flow improvements (v0.1.8)**:
    - **Payment method tracking**: Orders now store `payment_method` field (stripe, paypal, cod, bank_transfer) for better reporting and conditional UI logic.
    - **PayPal auto-capture on webhook**: When `CHECKOUT.ORDER.APPROVED` webhook arrives, the system automatically captures the payment via PayPal's capture API, then records the transaction with `paid` status to transition the order immediately.
    - **PayPal sandbox PENDING handling**: PayPal sandbox often returns `PENDING` capture status even for successful payments. The webhook handler now detects successful captures (when `external_id` changes from order ID to capture ID) and marks orders as paid regardless of the sandbox's PENDING quirk.
    - **User-friendly pending notice**: Order confirmation page displays a prominent amber notice for PayPal orders still in pending state, instructing customers to refresh after ~1 minute while the webhook processes.
    - See `docs/paypal-webhook-flow.md` for complete PayPal integration details.
- **Order security & integrity hardening (v0.1.9)**:
    - **Order state machine guards**: Implemented strict state transition validation via `VALID_TRANSITIONS` constant in `OrderService`. Invalid transitions (e.g., `paid→pending`, `canceled→paid`) are rejected with user-friendly error messages instead of silently succeeding. Terminal states (`refunded`, `canceled`) cannot transition to any other state.
    - **Webhook amount variance detection**: `PaymentGatewayManager::checkAmountVariance()` compares webhook payment amounts against order totals (1 cent tolerance). Mismatches automatically flag orders for review with detailed metadata, protecting against price manipulation attacks.
    - **Order review flag system**: New `needs_review` and `review_reason` columns allow orders to be flagged for manual review. Flagged orders display a "Review" badge in the admin panel with the specific reason (e.g., `payment_amount_mismatch`).
    - **Stale order cleanup task**: New Joomla 5 Scheduled Task plugin (`plg_task_nxpeasycartcleanup`) automatically cancels abandoned pending orders and releases reserved stock. Configurable threshold (1-720 hours, default 48) with enable/disable toggle in Settings → General.
    - **Graceful error handling**: Invalid state transitions return HTTP 400 with specific error messages displayed in the admin UI instead of throwing critical 500 errors.
    - **Admin panel UX fix**: Error alerts now display alongside data tables instead of replacing them, ensuring users can still see and interact with data while addressing errors.
    - See `docs/order-state-machine.md` for complete state machine documentation and transition rules.
- **Manual transaction recording (v0.1.9)**: Administrators can now manually record payments for offline payment methods:
    - **COD (Cash on Delivery)**: Record payment when cash is collected upon delivery.
    - **Bank Transfer**: Confirm payment when transfer is received and verified.
    - **UI integration**: "Record Payment" section appears in order details when order is `pending` and payment method is `cod` or `bank_transfer`.
    - **Flexible recording**: Amount defaults to order total but can be adjusted; optional reference (receipt/bank ref) and note fields for audit trail.
    - **Auto state transition**: Recording a payment automatically transitions the order from `pending` to `paid`.
    - **Audit logging**: All manual payments are logged with `order.payment.manual` action including recorder ID, amount, and reference.
    - See `docs/manual-transactions.md` for complete documentation.
- **Tax & Shipping admin architecture refactor (v0.1.10)**: Tax Rates and Shipping Methods have been extracted from the Settings panel into dedicated first-class admin workspaces:
    - **Standalone menu items**: Tax Rates and Shipping Methods now appear as top-level submenu items alongside Orders, Products, Categories, etc.
    - **Modal-based CRUD**: Both panels follow the established modal dialog pattern (like Coupons) for add/edit forms instead of inline editing.
    - **Dedicated views**: New `Tax/HtmlView.php` and `Shipping/HtmlView.php` with corresponding templates route through the Vue SPA via `appSection`.
    - **Settings cleanup**: The Settings panel now focuses purely on configuration (General, Security, Payments, Visual) without CRUD list management.
    - See `docs/admin-tax-shipping.md` for implementation details.
- **Checkout floating summary bar (v0.1.10)**: Enhanced checkout UX with a sticky order summary that follows the user:
    - **Automatic visibility**: A floating bar appears at the bottom of the viewport when the main order totals scroll out of view.
    - **Real-time totals**: Displays Subtotal, Shipping (if > 0), Tax (if applicable), and Total with the same formatting as the main summary.
    - **Smooth animations**: Slides up/down with CSS transitions when visibility changes.
    - **Responsive design**: Adapts layout for mobile screens (< 600px) with stacked total row.
    - **IntersectionObserver**: Uses modern browser API for efficient scroll detection without performance impact.
    - See `docs/checkout-floating-summary.md` for implementation details.
- **Order confirmation page improvements (v0.1.10)**: The public order status page now displays full pricing breakdown:
    - **Complete totals**: Shows Subtotal, Shipping, Discount (if applied), Tax (with rate and inclusive indicator), and Total.
    - **Tax label formatting**: Tax displays as "Tax (18% incl.)" for inclusive rates or "Tax (18%)" for exclusive, matching checkout behavior.
    - **Order tax persistence**: Orders now store `tax_rate` and `tax_inclusive` fields so the original tax configuration is preserved for display.
    - **Tax matching fix**: Backend now correctly uses `country_code` (2-letter ISO) instead of country display name for tax rate matching during checkout.
