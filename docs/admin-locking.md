# Admin check-in / check-out

This component now uses Joomla-style locking for products, categories, and orders, mirroring core behaviour and adding force check-in for privileged users.

## Backend changes
- Schema: `administrator/components/com_nxpeasycart/sql/install.mysql.utf8.sql` adds `checked_out` and `checked_out_time` columns to products, categories, and orders (also shipped via `administrator/components/com_nxpeasycart/sql/updates/mysql/0.1.15.sql`).
- Manifest: `administrator/components/com_nxpeasycart/nxpeasycart.xml` version bumped to include the migration.
- Products: `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php` supports `checkout`/`checkin` (with `force=1` for admins); list payloads include lock metadata. `ProductModel`/`ProductsModel` hydrate `checked_out` fields.
- Categories: `administrator/components/com_nxpeasycart/src/Controller/Api/CategoriesController.php` adds `checkout`/`checkin` (with `force=1` for admins) and returns lock metadata. `CategoriesModel` selects lock columns.
- Orders: `administrator/components/com_nxpeasycart/src/Service/OrderService.php` enforces locks, exposes `checkout`/`checkin($force)`, and guards state transitions. `administrator/components/com_nxpeasycart/src/Controller/Api/OrdersController.php` wires the endpoints and lock-aware error responses.
- Language: lock/force-checkin strings in `administrator/language/en-GB/com_nxpeasycart.ini`.

## Frontend (admin SPA)
- Endpoints and user data exposed via `administrator/components/com_nxpeasycart/tmpl/app/default.php`; JS bootstrapping in `media/com_nxpeasycart/src/admin-main.js` and `media/com_nxpeasycart/src/api.js` handles checkout/checkin with optional `force`.
- Composables: products (`media/com_nxpeasycart/src/app/composables/useProducts.js`), categories (`.../useCategories.js`), and orders (`.../useOrders.js`) expose `checkoutX`, `checkinX`, and `forceCheckinX` helpers plus lock state in composable state.
- UI: components show lock badges and block destructive actions when locked by others; on attempting to edit/view a locked record, admins get a confirmation prompt to force check-in:
  - Products: `media/com_nxpeasycart/src/app/components/ProductPanel.vue` and `.../ProductTable.vue`
  - Categories: `media/com_nxpeasycart/src/app/components/CategoryPanel.vue`
  - Orders: `media/com_nxpeasycart/src/app/components/OrdersPanel.vue`
- App wiring: `media/com_nxpeasycart/src/app/App.vue` passes lock endpoints/current user into panels.

## Usage
- Normal check-in/out: SPA calls `task=api.{products|categories|orders}.checkout` when opening; closing editors/details triggers `checkin`.
- Force check-in: pass `force=1` to `checkin` endpoints (SPA prompts before doing this). Require `core.manage` on the component.
- API: `POST ?option=com_nxpeasycart&task=api.products.checkin&id=123&force=1&format=json` (similarly for categories/orders) clears locks regardless of owner when caller is authorised and CSRF token is supplied.
