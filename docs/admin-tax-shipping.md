# Admin Tax & Shipping Architecture (v0.1.10)

This document describes the architectural refactor that extracted Tax Rates and Shipping Methods from the Settings panel into dedicated first-class admin workspaces.

## Motivation

Previously, Tax Rates and Shipping Methods were managed as tabs within the Settings panel. However, these are CRUD lists (create, read, update, delete) with their own data models, not configuration items. Having them inside Settings:

1. Made the Settings panel overly complex
2. Mixed configuration concerns with data management
3. Didn't follow the established pattern of other CRUD entities (Orders, Products, Categories, Coupons)

## Architecture Changes

### New Admin Views

Two new admin views were created following the Joomla MVC pattern:

```
administrator/components/com_nxpeasycart/
├── src/View/
│   ├── Tax/HtmlView.php          # Tax rates view
│   └── Shipping/HtmlView.php     # Shipping methods view
└── tmpl/
    ├── tax/default.php           # Tax template
    └── shipping/default.php      # Shipping template
```

Each view:
- Sets the appropriate page title via `Text::_('COM_NXPEASYCART_MENU_TAX')` / `Text::_('COM_NXPEASYCART_MENU_SHIPPING')`
- Fetches initial data from the respective service (`TaxService` / `ShippingRuleService`)
- Sets `appSection` to route to the correct Vue panel
- Includes the shared `app/default.php` template that mounts the Vue SPA

### Vue SPA Panels

Two new Vue components handle the UI:

```
media/com_nxpeasycart/src/app/components/
├── TaxPanel.vue        # Tax rates management
└── ShippingPanel.vue   # Shipping methods management
```

Both panels follow the established modal dialog pattern from `CouponsPanel.vue`:

1. **Table view**: Displays list of items with edit/delete actions
2. **Modal form**: Opens a slide-in panel for add/edit operations
3. **Composable integration**: Uses `useTaxRates()` and `useShippingRules()` for data management

### App.vue Integration

The main `App.vue` component was updated to:

1. Import and register the new panel components
2. Add computed properties for conditional loading:
   ```javascript
   const shouldLoadTax = computed(() =>
       appSection.value === "settings" || appSection.value === "tax"
   );
   const shouldLoadShipping = computed(() =>
       appSection.value === "settings" || appSection.value === "shipping"
   );
   ```
3. Add switch cases for routing to the new sections
4. Remove tax/shipping state props from `SettingsPanel`

### Settings Panel Cleanup

The `SettingsPanel.vue` was simplified:

- **Removed**: Tax and Shipping tab buttons from navigation
- **Removed**: Tax and Shipping tab content sections (~700 lines)
- **Removed**: All tax/shipping related props, emits, and reactive state
- **Updated**: `validTabs` to `["general", "security", "payments", "visual"]`
- **Updated**: Lead text from "Configure taxes, shipping, and store defaults" to "Configure payments, security, and store defaults"

### Navigation & Menu Items

**Admin navigation** (`tmpl/app/default.php`):
```php
$navItems = ['dashboard', 'orders', 'products', 'categories', 'coupons', 'tax', 'shipping', 'customers', 'logs', 'settings'];
```

**Component manifest** (`nxpeasycart.xml`):
```xml
<submenu>
    <!-- ... existing items ... -->
    <menu link="option=com_nxpeasycart&amp;view=tax">COM_NXPEASYCART_MENU_TAX</menu>
    <menu link="option=com_nxpeasycart&amp;view=shipping">COM_NXPEASYCART_MENU_SHIPPING</menu>
</submenu>
```

**Note**: After installation/reinstallation, the Joomla sidebar menu will display the new items. Existing installations require component reinstall for menu cache to update.

### Language Strings

Added to `administrator/language/en-GB/com_nxpeasycart.ini`:
- `COM_NXPEASYCART_TAX_PANEL_LEAD`
- `COM_NXPEASYCART_SHIPPING_PANEL_LEAD`
- `COM_NXPEASYCART_LOADING_TAX`
- `COM_NXPEASYCART_LOADING_SHIPPING`
- `COM_NXPEASYCART_CLOSE`

Added to `administrator/language/en-GB/com_nxpeasycart.sys.ini`:
- `COM_NXPEASYCART_MENU_TAX`
- `COM_NXPEASYCART_MENU_SHIPPING`

## Modal Dialog Pattern

Both panels use the consistent modal pattern:

```vue
<div v-if="formOpen" class="nxp-ec-modal" role="dialog" aria-modal="true">
    <div class="nxp-ec-modal__backdrop" @click="cancelEdit"></div>
    <div class="nxp-ec-modal__dialog nxp-ec-modal__dialog--panel">
        <aside class="nxp-ec-admin-panel__sidebar">
            <header class="nxp-ec-admin-panel__sidebar-header">
                <h3>{{ isEditing ? 'Edit' : 'Add' }} Item</h3>
                <button @click="cancelEdit">×</button>
            </header>
            <div class="nxp-ec-admin-panel__sidebar-body">
                <!-- Form fields -->
            </div>
            <footer class="nxp-ec-admin-panel__sidebar-footer">
                <button @click="cancelEdit">Cancel</button>
                <button @click="saveItem">Save</button>
            </footer>
        </aside>
    </div>
</div>
```

## File Changes Summary

### New Files
- `administrator/components/com_nxpeasycart/src/View/Tax/HtmlView.php`
- `administrator/components/com_nxpeasycart/src/View/Shipping/HtmlView.php`
- `administrator/components/com_nxpeasycart/tmpl/tax/default.php`
- `administrator/components/com_nxpeasycart/tmpl/shipping/default.php`
- `media/com_nxpeasycart/src/app/components/TaxPanel.vue`
- `media/com_nxpeasycart/src/app/components/ShippingPanel.vue`

### Modified Files
- `media/com_nxpeasycart/src/app/App.vue` - Added panel imports and routing
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue` - Removed tax/shipping code
- `administrator/components/com_nxpeasycart/tmpl/app/default.php` - Added nav items
- `administrator/components/com_nxpeasycart/nxpeasycart.xml` - Added submenu entries
- `administrator/language/en-GB/com_nxpeasycart.ini` - Added translation keys
- `administrator/language/en-GB/com_nxpeasycart.sys.ini` - Added menu translations

## Benefits

1. **Cleaner separation of concerns**: Settings manages config, Tax/Shipping manage data
2. **Consistent UX**: Same patterns as other CRUD panels (modal forms, table lists)
3. **Easier maintenance**: Each panel is self-contained with its own view/template
4. **Better navigation**: Direct menu access instead of navigating through Settings
5. **Reduced complexity**: Settings panel is now ~700 lines lighter
