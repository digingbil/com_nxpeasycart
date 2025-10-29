# NXP Easy Cart

NXP Easy Cart is a Joomla 5 component that prioritises a 10-minute setup, a clean administrator experience, and a bulletproof checkout. The project follows the guidance in `INSTRUCTIONS.md` with a focus on security, reliability, and simplicity.

## Overview

- Namespaced Joomla MVC structure for administrator (`Nxp\\EasyCart\\Admin\\Administrator`) and site (`Nxp\\EasyCart\\Site`) applications.
- Install/uninstall SQL covering the core NXP data model (`#__nxp_*` tables).
- Service provider bootstrap for Joomla's dependency injection container (no legacy `com_nxpeasycart.php` entry file).
- JSON product API endpoints with ACL + CSRF enforcement for the upcoming admin SPA.
- Placeholder admin SPA mount point and storefront template ready for Vue “islands”.

## Installation (local development)

1. Ensure the repository is symlinked into your Joomla instance (`/var/www/html/j5.loc`) under `administrator/components/com_nxpeasycart` and `components/com_nxpeasycart`.
2. Install PHP dependencies locally:
   ```bash
   composer install
   ```
3. Install Node dependencies for the admin SPA toolchain:
   ```bash
   npm install
   ```
4. Install the component through the Joomla extension manager or via `Discover`.
5. After installation, access the admin menu entry **NXP Easy Cart** to verify the placeholder dashboard renders.
6. Copy or symlink the generated `vendor/` directory into the Joomla instance if needed—Composer never has to run inside the live site tree.

## Tooling & dependencies

- PHP 8.0+ with `psr/simple-cache`, `guzzlehttp/guzzle`, `ramsey/uuid`, and `brick/money` as runtime deps.
- `joomla/joomla-cms` is required *only* for local tooling (listed in `require-dev`) so Composer can expose Joomla classes to IDEs and PHPStan; it must be excluded from release builds (`composer install --no-dev`).
- Development tooling: PHPStan (level 6), PHP-CS-Fixer (PSR-12), PHPUnit 10.
- `.phpstan.neon` and `.php-cs-fixer.php` are preconfigured; run `composer lint`, `composer fix`, and `composer stan` as needed.
- Vite configuration lives at `build/vite.config.admin.js`; use `npm run dev:admin` or `npm run build:admin` to compile the admin bundle into `media/com_nxpeasycart/js/admin.iife.js` and accompanying CSS.
- Vue 3 drives the admin SPA (bundled as an IIFE); install dependencies with `npm install` before running any build script.
- All frontend code (including Vue islands) must be authored in plain JavaScript—TypeScript is not permitted.

## Packaging / deployment

- Local development keeps `administrator/components/com_nxpeasycart/vendor` and `components/com_nxpeasycart/vendor` symlinked to the repository’s root `vendor/` directory for convenience.
- To produce an installable ZIP, run `composer install --no-dev --optimize-autoloader` in a clean workspace, build frontend assets (`npm run build:admin`), and include the trimmed `vendor/` directory plus component files in the package.
- Do **not** run Composer inside the live Joomla tree; copy or mirror the prepared `vendor/` folder alongside the component when deploying.
- The manifest living at `administrator/components/com_nxpeasycart/nxpeasycart.xml` follows Joomla’s discovery convention (no `com_` prefix in the filename). After copying the component into a site, use **System → Discover** or `php cli/joomla.php extension:discover` to register it, then complete the install from that screen. Joomla 5’s DI bootstrapping means no administrator entry script is required.
- When developing via symlinks, also symlink the language files into Joomla (`administrator/language/en-GB/com_nxpeasycart*.ini` and `language/en-GB/com_nxpeasycart*.ini`) so admin menu strings resolve.

## Admin SPA build

- `media/com_nxpeasycart/src/admin-main.js` bootstraps the Vue 3 admin shell which currently renders a products grid powered by the `/api.products.*` endpoints.
- CSRF tokens and API endpoints are exposed via `data-*` attributes in the admin template for the SPA to consume.
- A placeholder `media/com_nxpeasycart/js/admin.iife.js` ships with the repo so the asset loads prior to the first Vite build; running `npm run build:admin` overwrites it with the compiled Vue app. The bundled asset registers under `com_nxpeasycart.admin` and carries its CSS dependency automatically.

## Changelog summary

- **M0 – Component scaffold**: Initial manifest, service provider, admin/site controllers & views, database schema stubs, developer tooling baseline, JSON product API, and Vite-based admin bundler skeleton.
