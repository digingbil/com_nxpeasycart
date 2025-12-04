# Currency Localization

NXP Easy Cart supports locale-aware currency formatting, ensuring prices display according to regional conventions (decimal separators, thousands grouping, currency symbol placement).

## Overview

| Locale | Currency | Example (139000 cents) |
|--------|----------|------------------------|
| `mk-MK` | MKD | 1.390,00 ден. |
| `en-US` | USD | $1,390.00 |
| `de-DE` | EUR | 1.390,00 € |
| `en-GB` | GBP | £1,390.00 |
| `fr-FR` | EUR | 1 390,00 € |

## Locale Resolution Order

When formatting prices, the system resolves the display locale in this order:

1. **Store override** — If `display_locale` is set in Settings → General
2. **Joomla site language** — Derived from `Factory::getApplication()->getLanguage()`
3. **Fallback** — `en_US`

This means:
- By default, prices format according to the Joomla site's configured language
- Administrators can override this globally for the entire store
- The fallback ensures formatting never fails

## Configuration

### Setting the Display Locale Override

1. Navigate to **Admin → EasyCart → Settings → General**
2. Find the **"Price display locale"** field
3. Enter a locale code (e.g., `mk-MK`, `de-DE`, `en-US`) or leave empty for auto-detection
4. Click **Save settings**

### Accepted Locale Formats

Both formats are accepted and normalized internally:

| Joomla-style | ICU-style | Result |
|--------------|-----------|--------|
| `mk-MK` | `mk_MK` | Macedonian formatting |
| `de-DE` | `de_DE` | German formatting |
| `en-US` | `en_US` | US English formatting |
| `en-GB` | `en_GB` | British English formatting |

## Technical Implementation

### MoneyHelper (PHP)

All PHP price formatting flows through `MoneyHelper::format()`:

```php
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;

// Auto-resolve locale (checks override → Joomla language → fallback)
$formatted = MoneyHelper::format(139000, 'MKD');
// Result depends on resolved locale

// Explicit locale (bypasses resolution)
$formatted = MoneyHelper::format(139000, 'MKD', 'mk_MK');
// Always returns: "1.390,00 ден."
```

### Locale Resolution

```php
// Get the resolved locale for the current request
$locale = MoneyHelper::resolveLocale();
// Returns e.g., "mk_MK" based on resolution order
```

### ConfigHelper (Settings)

```php
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;

// Get the store override (empty string if not set)
$override = ConfigHelper::getDisplayLocale();

// Set the override (pass empty string to clear)
ConfigHelper::setDisplayLocale('mk-MK');
```

### JavaScript (formatMoney.js)

The storefront islands use `formatMoney()` from `media/com_nxpeasycart/src/site/utils/formatMoney.js`:

```javascript
import { formatMoney } from './utils/formatMoney.js';

// Uses browser's Intl.NumberFormat
const formatted = formatMoney(139000, 'MKD', 'mk-MK');
// Returns: "1.390,00 ден."
```

The locale is passed to islands via `data-nxp-locale` attributes, which PHP sets using `MoneyHelper::resolveLocale()`.

## Files Modified

### Core Formatting

| File | Purpose |
|------|---------|
| `administrator/.../Helper/MoneyHelper.php` | Central `format()` and `resolveLocale()` methods |
| `administrator/.../Helper/ConfigHelper.php` | `getDisplayLocale()` / `setDisplayLocale()` |
| `media/.../src/site/utils/formatMoney.js` | JavaScript formatting utility |

### Settings UI

| File | Purpose |
|------|---------|
| `administrator/.../Controller/Api/SettingsController.php` | Exposes `display_locale` in API |
| `media/.../src/app/components/SettingsPanel.vue` | Admin UI field |
| `media/.../src/app/composables/useSettings.js` | Settings state management |

### Templates Using MoneyHelper

| File | Context |
|------|---------|
| `components/.../tmpl/product/default.php` | Product detail page |
| `components/.../src/Model/CategoryModel.php` | Category listings |
| `components/.../src/Model/ProductModel.php` | Product data |
| `components/.../src/Model/LandingModel.php` | Landing page |
| `modules/mod_nxpeasycart_cart/tmpl/default.php` | Cart summary module |
| `administrator/.../templates/email/*.php` | Order emails |
| `administrator/.../templates/invoice/invoice.php` | PDF invoices |

## Language Strings

```ini
COM_NXPEASYCART_SETTINGS_GENERAL_DISPLAY_LOCALE="Price display locale"
COM_NXPEASYCART_SETTINGS_GENERAL_DISPLAY_LOCALE_HELP="Override price formatting locale. Leave empty to use Joomla's site language. Examples: mk-MK, de-DE, en-US"
COM_NXPEASYCART_ERROR_SETTINGS_DISPLAY_LOCALE_INVALID="Invalid display locale format. Use format like mk-MK or en-US."
```

## Requirements

### PHP intl Extension

The `NumberFormatter` class from PHP's `intl` extension is required for proper locale formatting. Without it, the system falls back to basic formatting (`USD 1390.00`).

Check if installed:

```bash
php -m | grep intl
```

Install on Ubuntu/Debian:

```bash
sudo apt install php-intl
sudo systemctl restart apache2
```

### ICU Data

The `intl` extension uses ICU (International Components for Unicode) data for locale information. Ensure your system has up-to-date ICU libraries for accurate currency symbols and formatting rules.

## Testing

### Manual Testing

1. Set Joomla site language to Macedonian (`mk-MK`)
2. Leave "Price display locale" empty
3. View a product page — prices should show as `1.390,00 ден.`

4. Change "Price display locale" to `en-US`
5. Refresh product page — prices should show as `MKD 1,390.00`

### Verify Locale Resolution

```php
// In a Joomla context
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;

echo MoneyHelper::resolveLocale(); // Shows resolved locale
echo MoneyHelper::format(139000, 'MKD'); // Shows formatted price
```

## Common Scenarios

### Scenario 1: Macedonian Store

- **Joomla language**: `mk-MK`
- **Display locale override**: (empty)
- **Result**: `1.390,00 ден.`

### Scenario 2: International Store with Fixed Format

- **Joomla language**: `en-GB` (multilingual site)
- **Display locale override**: `de-DE`
- **Result**: `1.390,00 €` (German formatting for all visitors)

### Scenario 3: US Store

- **Joomla language**: `en-US`
- **Display locale override**: (empty)
- **Result**: `$1,390.00`

## Troubleshooting

### Prices Show as "MKD 1390.00"

This indicates `NumberFormatter` is falling back to basic formatting. Check:

1. PHP `intl` extension is installed and enabled
2. The locale code is valid (e.g., `mk_MK` not `macedonian`)

### Override Not Taking Effect

1. Clear Joomla cache
2. Verify the setting saved correctly in the database
3. Check `MoneyHelper::resolveLocale()` returns expected value

### Different Format in Emails vs Storefront

Emails rendered in CLI/cron context may not have access to Joomla's language. The system falls back to `en_US` or the store override. Set an explicit override if consistent formatting is required across all contexts.

---

**Last Updated**: 2025-12-04
**Component Version**: 0.1.13
