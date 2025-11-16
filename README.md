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

### Performance Optimizations (Admin SPA)

- **Cache-first data strategy** with 5-minute TTL across all admin composables (Products, Orders, Categories, Settings, Coupons, Tax, Shipping, Customers, Logs, Dashboard) reduces redundant API calls by 60-80% and enables instant tab switching when returning to previously loaded panels.
- **Performance tracking** logs fetch durations and cache hit/miss ratios to the console (`[NXP EC Performance]` and `[NXP EC Cache]` markers) for debugging slow endpoints and monitoring optimization effectiveness.
- **Skeleton loaders** replace blank canvases during data loads across all major panels (Products, Orders, Categories, Customers, Logs) with smooth pulse animations, providing immediate visual feedback and professional loading states.
- **Last updated timestamps** appear at the bottom of each panel showing relative time ("2 minutes ago") or full timestamp, confirming data freshness and cache age to administrators.
- **Layout shift elimination** on the admin header prevents the onboarding button from causing reflows by using CSS Grid layout and `v-show` instead of `v-if`, achieving near-zero Cumulative Layout Shift (CLS < 0.001).
- **Prefetch utilities** (optional) enable background loading of adjacent panels based on navigation patterns, preloading likely-next destinations after the main panel finishes loading.

See `docs/performance-optimization.md` for complete implementation details, cache configuration examples, and performance metrics.

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
3. Build the trimmed runtime vendor that Joomla should load (prevents the dev autoloader from hijacking the CMS):
    ```bash
    php tools/build-runtime-vendor.php
    ```
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

## Storefront cart

- Frontend requests resolve the active cart through `CartSessionService`, which ties the Joomla session to the `#__nxp_easycart_carts` table and guarantees the payload uses the configured base currency.
- The session helper exposes the hydrated cart on the application input (`com_nxpeasycart.cart`) so upcoming cart/checkout views can consume a consistent structure.
- The default storefront view is now the catalogue grid: `DisplayController` targets the category view, showing “All products” with category filters and progressive enhancement via the site bundle.
- Product editor exposes a “Featured” toggle; any flagged products surface in the landing page spotlight row, with recent additions filling the remaining sections automatically.
- Category menu chips allow quick filtering between taxonomy slugs, while fallbacks keep navigation and product tiles rendering without JavaScript.
- Checkout integrates Stripe/PayPal hosted sessions when configured; fallback direct order creation remains for offline capture. Webhooks hydrate transactions, decrement inventory on `paid`, and trigger confirmation email templates.
- Payment webhook endpoints are exposed at `index.php?option=com_nxpeasycart&task=webhook.stripe` and `…task=webhook.paypal`, each funneled through `PaymentGatewayManager` with idempotency safeguards and audit logging.
- Order confirmation emails use PHP templates under `administrator/components/com_nxpeasycart/templates/email/`, delivered via Joomla’s configured mailer after successful payment capture.

- Admin orders view now includes a lightweight PHP fallback table so seeded data is visible even before the Vue bundle is rebuilt; the fallback strips itself once the SPA mounts.
- SPA endpoints now use absolute admin URLs plus scoped query merging, avoiding `Invalid controller class` errors and keeping detail panels in sync with preloaded data.
- The component’s product view uses the existing placeholder copy whenever a specific product cannot be resolved, so hitting `/index.php?option=com_nxpeasycart` stays within the guided onboarding flow.

## Testing

- See `docs/testing.md` for the current automation blueprint covering PHPUnit (unit/integration), API contract runs, Vue unit tests, and Playwright E2E journeys.
- Risk register lives at `docs/risk-register.md`; packaging workflow is documented in `docs/packaging.md`.
- Risk register lives at `docs/risk-register.md`; packaging workflow is documented in `docs/packaging.md`.

## Changelog summary

- **M0 – Component scaffold**: Initial manifest, service provider, admin/site controllers & views, database schema stubs, developer tooling baseline, JSON product API, and Vite-based admin bundler skeleton.
- **M1 – Products CRUD**: Admin SPA delivers full product management (images, categories, variants) with validated JSON endpoints and improved UX.
- **Bugfix – Admin product save routing**: API router now preserves dotted task names (`api.products.store` etc.), restoring the ability to create products from the Vue admin.
