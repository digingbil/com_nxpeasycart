# Uninstallation and Data Removal

## Important Warning

**Uninstalling NXP Easy Cart will permanently delete ALL component data from your database.**

This includes:

- **Orders** - All order history, line items, and transaction records
- **Products** - Your entire product catalogue, variants, and images
- **Categories** - All category structures
- **Customers** - Order history linked to customer emails
- **Coupons** - All discount codes and their usage history
- **Settings** - Store configuration, tax rates, shipping rules
- **Audit logs** - Complete activity history

## Before Uninstalling

If you may need this data in the future, **export it first**:

1. **Database backup**: Create a full backup of your Joomla database before uninstalling
2. **GDPR export**: Use the admin GDPR export feature to download customer order data
3. **Manual export**: Export the `#__nxp_easycart_*` tables using phpMyAdmin or similar tools

## Tables Removed on Uninstall

The following tables are dropped when the component is uninstalled:

```
#__nxp_easycart_products
#__nxp_easycart_variants
#__nxp_easycart_categories
#__nxp_easycart_product_categories
#__nxp_easycart_orders
#__nxp_easycart_order_items
#__nxp_easycart_transactions
#__nxp_easycart_coupons
#__nxp_easycart_tax_rates
#__nxp_easycart_shipping_rules
#__nxp_easycart_settings
#__nxp_easycart_carts
#__nxp_easycart_audit
```

## Package vs Component Uninstall

NXP Easy Cart is distributed as a package (`pkg_nxpeasycart`) containing:

- `com_nxpeasycart` - The main component
- `mod_nxpeasycart_cart` - Cart summary module
- `plg_task_nxpeasycartcleanup` - Scheduled task plugin

When you uninstall the **package**, Joomla should cascade-uninstall all child extensions, including running the component's uninstall SQL.

If for any reason the tables remain after uninstalling the package, you can manually remove them by:

1. Uninstalling `com_nxpeasycart` directly from Extensions → Manage (if still listed)
2. Or running the SQL manually:

```sql
DROP TABLE IF EXISTS `#__nxp_easycart_audit`;
DROP TABLE IF EXISTS `#__nxp_easycart_coupons`;
DROP TABLE IF EXISTS `#__nxp_easycart_shipping_rules`;
DROP TABLE IF EXISTS `#__nxp_easycart_tax_rates`;
DROP TABLE IF EXISTS `#__nxp_easycart_settings`;
DROP TABLE IF EXISTS `#__nxp_easycart_carts`;
DROP TABLE IF EXISTS `#__nxp_easycart_transactions`;
DROP TABLE IF EXISTS `#__nxp_easycart_order_items`;
DROP TABLE IF EXISTS `#__nxp_easycart_orders`;
DROP TABLE IF EXISTS `#__nxp_easycart_variants`;
DROP TABLE IF EXISTS `#__nxp_easycart_product_categories`;
DROP TABLE IF EXISTS `#__nxp_easycart_categories`;
DROP TABLE IF EXISTS `#__nxp_easycart_products`;
```

(Replace `#__` with your actual Joomla table prefix, e.g., `j5_`)

## Reinstallation

If you reinstall NXP Easy Cart after uninstalling, you will start with a clean slate. Previously stored orders, products, and settings will not be recoverable unless you restore from a database backup.

## Legal and Compliance Considerations

Before uninstalling, consider whether you have legal obligations to retain:

- **Financial records** - Many jurisdictions require keeping transaction records for tax purposes (typically 5-7 years)
- **GDPR compliance** - You may need to retain certain records to demonstrate compliance with data subject requests

If in doubt, export and archive your data before uninstalling.
