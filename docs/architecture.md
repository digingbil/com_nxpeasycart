# NXP Easy Cart – Architecture Overview (M0)

## Component layout

- `administrator/components/com_nxpeasycart`
  - `com_nxpeasycart.php`: entry point enforcing `_JEXEC` and wiring the controller.
  - `services/provider.php`: registers the component, MVC factory, and dispatcher with Joomla's DI container.
  - `nxpeasycart.xml`: component manifest (filename omits the `com_` prefix so Joomla Discover picks it up).
  - `src/`: PSR-4 namespaced administrator classes (`Nxp\EasyCart\Admin\…`).
    - `Controller/ApiController.php`: task router delegating to JSON resource controllers.
    - `Controller/Api/*Controller.php`: JSON endpoints returning RFC-7807-style payloads.
    - `Model/ProductModel.php` + `Model/ProductsModel.php`: table-backed product storage and listing.
    - `Table/ProductTable.php`: database gateway enforcing slug uniqueness.
  - `sql/`: install, uninstall, and future update scripts for the `#__nxp_*` tables.
  - `forms/product.xml`: Joomla form definition used to validate API payloads.
- `components/com_nxpeasycart`
  - `com_nxpeasycart.php`: site entry point.
  - `src/`: storefront controllers/views in the `Nxp\EasyCart\Site\…` namespace.

The admin view exposes a `<div id="nxp-admin-app">` mount target for the upcoming Vue IIFE bundle as defined in the instructions.

## Database schema

The install script provisions the core tables required by the domain model:

- `#__nxp_products`, `#__nxp_variants`, `#__nxp_categories`, and pivot table `#__nxp_product_categories`.
- Order pipeline tables `#__nxp_orders`, `#__nxp_order_items`, `#__nxp_transactions`, plus `#__nxp_coupons`.
- `#__nxp_audit` for state-change logging.

All relationships use InnoDB FK constraints and default to cascading deletes to keep referential integrity during early development.

## Admin API

- Requests are routed through `ApiController`, which maps `task=api.{resource}.{action}` to resource-specific controllers.
- `AbstractJsonController` enforces ACL (`core.manage`, `core.create`, `core.edit`, `core.delete`), CSRF tokens via `Session::checkToken('request')`, and standard JSON responses.
- `ProductsController` currently implements browse/store/update/destroy endpoints operating on the `#__nxp_products` table using the Joomla MVC model and table classes.

## Admin SPA build

- Web assets are declared in `media/com_nxpeasycart/joomla.asset.json` under the handle `com_nxpeasycart.admin`.
- `media/com_nxpeasycart/src/admin-main.js` is compiled through Vite (`build/vite.config.admin.js`) into `media/com_nxpeasycart/js/admin.iife.js` with CSS emitted to `media/com_nxpeasycart/css/admin.css`.
- The Joomla view registers the asset handle and exposes CSRF tokens/API endpoints via `data-*` attributes on the mount node to keep the SPA stateless and CSRF-safe.

## Next steps

1. Extend the admin SPA to consume the product API and replace the placeholder UI.
2. Broaden JSON controllers/models to cover orders, customers, and configuration entities.
3. Introduce domain services (money, inventory, orders) backed by the schema created here.
