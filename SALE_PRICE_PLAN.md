# Sale Price Implementation Plan

**Version:** 0.2.0
**Date:** December 15, 2025
**Status:** Planning
**Component:** NXP Easy Cart (com_nxpeasycart)

---

## Executive Summary

This document outlines the comprehensive implementation of **sale pricing** for product variants in NXP Easy Cart. Sale prices enable merchants to run time-limited promotions with automatic activation/expiration, maintain pricing integrity across the entire storefront, and prevent double-discount abuse via the existing coupon system.

**Core Principle:** Sale prices are **optional variant-level overrides** that take precedence over regular prices during defined promotional periods. All pricing logic throughout the component must respect this hierarchy to ensure consistency.

---

## 1. Database Schema Changes

### 1.1 Variant Table Modifications

**File:** `administrator/components/com_nxpeasycart/sql/updates/mysql/0.2.0.sql`

Add three new fields to the `#__nxp_easycart_variants` table:

```sql
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `sale_price_cents` INT NULL DEFAULT NULL AFTER `price_cents`,
  ADD COLUMN `sale_start` DATETIME NULL DEFAULT NULL AFTER `sale_price_cents`,
  ADD COLUMN `sale_end` DATETIME NULL DEFAULT NULL AFTER `sale_start`,
  ADD INDEX `idx_nxp_variants_sale_active` (`sale_start`, `sale_end`);
```

**Field Definitions:**

- **`sale_price_cents`** (INT NULL): Optional promotional price in minor currency units (cents). NULL = no sale price set.
- **`sale_start`** (DATETIME NULL): Sale activation timestamp. NULL = no start restriction (active immediately if sale_price_cents is set).
- **`sale_end`** (DATETIME NULL): Sale expiration timestamp. NULL = no end restriction (never expires).
- **Index `idx_nxp_variants_sale_active`**: Composite index on `(sale_start, sale_end)` for efficient "active sales" queries (WHERE NOW() BETWEEN sale_start AND sale_end).

**Rationale:**

- **Performance:** Storing sale data directly on variants avoids JOIN overhead during product listing/detail queries.
- **Flexibility:** NULL values allow merchants to omit start/end dates for evergreen sales or immediate activation.
- **Consistency:** Follows the same `_cents` naming convention as `price_cents` and respects the single-currency MVP guardrail.

### 1.2 Update Install Schema

**File:** `administrator/components/com_nxpeasycart/sql/install.mysql.utf8.sql`

Insert the new fields into the base `CREATE TABLE` statement for `#__nxp_easycart_variants` (between `price_cents` and `currency`):

```sql
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(64) NOT NULL,
  `ean` VARCHAR(13) NULL DEFAULT NULL,
  `price_cents` INT NOT NULL,
  `sale_price_cents` INT NULL DEFAULT NULL,
  `sale_start` DATETIME NULL DEFAULT NULL,
  `sale_end` DATETIME NULL DEFAULT NULL,
  `currency` CHAR(3) NOT NULL,
  -- ... (rest of existing fields)
  KEY `idx_nxp_variants_sale_active` (`sale_start`, `sale_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Core Logic: Price Resolution Helper

### 2.1 New Helper Class: `PriceHelper`

**File:** `administrator/components/com_nxpeasycart/src/Helper/PriceHelper.php`

Create a centralized helper that encapsulates all sale price logic:

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

defined('_JEXEC') or die;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Price resolution utilities (regular vs sale prices).
 *
 * @since 0.2.0
 */
class PriceHelper
{
    /**
     * Resolve the effective price for a variant considering sale pricing.
     *
     * @param object|array $variant Variant row with price_cents, sale_price_cents, sale_start, sale_end
     * @param DateTimeImmutable|null $now Optional timestamp for testing (defaults to current time)
     *
     * @return array{
     *   effective_price_cents: int,
     *   regular_price_cents: int,
     *   sale_price_cents: ?int,
     *   is_on_sale: bool,
     *   sale_active: bool
     * }
     *
     * @since 0.2.0
     */
    public static function resolve($variant, ?DateTimeImmutable $now = null): array
    {
        $isArray = is_array($variant);
        $regularPrice = (int) ($isArray ? ($variant['price_cents'] ?? 0) : ($variant->price_cents ?? 0));
        $salePrice    = $isArray ? ($variant['sale_price_cents'] ?? null) : ($variant->sale_price_cents ?? null);
        $saleStart    = $isArray ? ($variant['sale_start'] ?? null) : ($variant->sale_start ?? null);
        $saleEnd      = $isArray ? ($variant['sale_end'] ?? null) : ($variant->sale_end ?? null);

        // If no sale price is set, return regular price
        if ($salePrice === null || $salePrice === '') {
            return [
                'effective_price_cents' => $regularPrice,
                'regular_price_cents'   => $regularPrice,
                'sale_price_cents'      => null,
                'is_on_sale'            => false,
                'sale_active'           => false,
            ];
        }

        $salePriceCents = (int) $salePrice;

        // Check if sale is currently active
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $isActive = self::isSaleActive($saleStart, $saleEnd, $now);

        $effectivePrice = $isActive ? $salePriceCents : $regularPrice;

        return [
            'effective_price_cents' => $effectivePrice,
            'regular_price_cents'   => $regularPrice,
            'sale_price_cents'      => $salePriceCents,
            'is_on_sale'            => $salePriceCents > 0,
            'sale_active'           => $isActive,
        ];
    }

    /**
     * Check if a sale period is currently active.
     *
     * @since 0.2.0
     */
    public static function isSaleActive(
        ?string $start,
        ?string $end,
        ?DateTimeImmutable $now = null
    ): bool {
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // No start date = active immediately
        // No end date = never expires
        // If start > now OR end < now = inactive

        if ($start !== null && $start !== '') {
            try {
                $startDt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
                if ($now < $startDt) {
                    return false; // Not started yet
                }
            } catch (\Throwable $e) {
                return false; // Invalid date = treat as inactive
            }
        }

        if ($end !== null && $end !== '') {
            try {
                $endDt = new DateTimeImmutable($end, new DateTimeZone('UTC'));
                if ($now > $endDt) {
                    return false; // Already ended
                }
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true; // No restrictions or within valid window
    }

    /**
     * Compute price range (min/max) across an array of variants.
     * Returns the effective sale prices when active.
     *
     * @param array $variants Array of variant rows
     *
     * @return array{min_cents: int, max_cents: int, currency: string, has_sale: bool}
     *
     * @since 0.2.0
     */
    public static function computePriceRange(array $variants, string $defaultCurrency = 'USD'): array
    {
        if (empty($variants)) {
            return [
                'min_cents' => 0,
                'max_cents' => 0,
                'currency'  => $defaultCurrency,
                'has_sale'  => false,
            ];
        }

        $prices = [];
        $hasSale = false;

        foreach ($variants as $variant) {
            $resolved = self::resolve($variant);
            $prices[] = $resolved['effective_price_cents'];

            if ($resolved['sale_active']) {
                $hasSale = true;
            }
        }

        $prices = array_filter($prices, static fn($p) => $p > 0);

        if (empty($prices)) {
            return [
                'min_cents' => 0,
                'max_cents' => 0,
                'currency'  => $defaultCurrency,
                'has_sale'  => $hasSale,
            ];
        }

        return [
            'min_cents' => min($prices),
            'max_cents' => max($prices),
            'currency'  => $defaultCurrency,
            'has_sale'  => $hasSale,
        ];
    }
}
```

**Key Methods:**

1. **`resolve($variant)`**: Returns effective price + metadata (regular_price, sale_price, is_on_sale, sale_active).
2. **`isSaleActive($start, $end)`**: Date range validation logic with NULL-safe handling.
3. **`computePriceRange($variants)`**: Aggregates min/max effective prices across all variants for product cards/listings.

**Testing Timestamps:** The `$now` parameter allows unit tests to inject specific timestamps for testing past/future sales.

---

## 3. Backend: VariantTable Validation

### 3.1 Update `VariantTable::check()`

**File:** `administrator/components/com_nxpeasycart/src/Table/VariantTable.php`

Add validation logic after the existing `price_cents` check:

```php
// Existing price_cents validation
if ($this->price_cents < 0) {
    throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));
}

// NEW: Sale price validation
if ($this->sale_price_cents !== null && $this->sale_price_cents !== '') {
    $salePriceCents = (int) $this->sale_price_cents;

    if ($salePriceCents < 0) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SALE_PRICE_INVALID'));
    }

    // WARNING (not error): Sale price should typically be lower than regular price
    if ($salePriceCents > 0 && $salePriceCents >= $this->price_cents) {
        Factory::getApplication()->enqueueMessage(
            Text::_('COM_NXPEASYCART_WARNING_VARIANT_SALE_PRICE_NOT_LOWER'),
            'warning'
        );
    }
} else {
    $this->sale_price_cents = null;
}

// Normalize sale date fields
$this->sale_start = ($this->sale_start !== null && $this->sale_start !== '') ? $this->sale_start : null;
$this->sale_end   = ($this->sale_end !== null && $this->sale_end !== '') ? $this->sale_end : null;
```

**Validation Rules:**

- Sale price must be >= 0 (if set).
- **Warning** (not error) if sale price >= regular price (merchants may want to run "clearance" events at regular price).
- NULL normalization ensures clean data.

---

## 4. Backend: Admin Product Editor (Vue SPA)

### 4.1 Update `ProductEditor.vue` Variant Fields

**File:** `media/com_nxpeasycart/src/app/components/ProductEditor.vue`

Add three new input fields per variant (after the existing `price` field):

```vue
<!-- Existing price field -->
<div class="nxp-ec-form-field">
    <label class="nxp-ec-form-label" :for="`variant-${index}-price`">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_PRICE", "Price") }}
    </label>
    <input
        :id="`variant-${index}-price`"
        class="nxp-ec-form-input"
        type="number"
        step="0.01"
        min="0"
        v-model="variant.price"
        required
    />
</div>

<!-- NEW: Sale price field -->
<div class="nxp-ec-form-field">
    <label class="nxp-ec-form-label" :for="`variant-${index}-sale-price`">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE", "Sale Price (optional)") }}
    </label>
    <input
        :id="`variant-${index}-sale-price`"
        class="nxp-ec-form-input"
        type="number"
        step="0.01"
        min="0"
        v-model="variant.sale_price"
    />
    <p class="nxp-ec-form-help">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE_HELP", "Promotional price. Leave empty if not on sale.") }}
    </p>
</div>

<!-- NEW: Sale start date -->
<div class="nxp-ec-form-field">
    <label class="nxp-ec-form-label" :for="`variant-${index}-sale-start`">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_START", "Sale Start Date") }}
    </label>
    <input
        :id="`variant-${index}-sale-start`"
        class="nxp-ec-form-input"
        type="datetime-local"
        v-model="variant.sale_start_local"
        :disabled="!variant.sale_price || variant.sale_price === ''"
    />
    <p class="nxp-ec-form-help">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_START_HELP", "Leave empty to activate immediately.") }}
    </p>
</div>

<!-- NEW: Sale end date -->
<div class="nxp-ec-form-field">
    <label class="nxp-ec-form-label" :for="`variant-${index}-sale-end`">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_END", "Sale End Date") }}
    </label>
    <input
        :id="`variant-${index}-sale-end`"
        class="nxp-ec-form-input"
        type="datetime-local"
        v-model="variant.sale_end_local"
        :disabled="!variant.sale_price || variant.sale_price === ''"
    />
    <p class="nxp-ec-form-help">
        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_END_HELP", "Leave empty for no expiration.") }}
    </p>
</div>
```

**Data Handling:**

- Use `sale_start_local` / `sale_end_local` for `<input type="datetime-local">` binding (ISO 8601 format without timezone).
- Convert to/from UTC when saving/loading from API.
- Disable date inputs when `sale_price` is empty.

### 4.2 Update `ProductEditor.vue` Methods

Add conversion logic in the `loadProduct()` and `save()` methods:

```javascript
// When loading a variant from API
const loadedVariant = {
    // ... existing fields
    sale_price: variant.sale_price || "",
    sale_start_local: variant.sale_start
        ? convertUTCToLocal(variant.sale_start)
        : "",
    sale_end_local: variant.sale_end ? convertUTCToLocal(variant.sale_end) : "",
};

// When saving
const payload = {
    // ... existing fields
    sale_price: variant.sale_price || null,
    sale_start: variant.sale_start_local
        ? convertLocalToUTC(variant.sale_start_local)
        : null,
    sale_end: variant.sale_end_local
        ? convertLocalToUTC(variant.sale_end_local)
        : null,
};

// Utility functions
function convertUTCToLocal(utcString) {
    const date = new Date(utcString + "Z"); // Append Z to force UTC parsing
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function convertLocalToUTC(localString) {
    const date = new Date(localString);
    return date.toISOString().slice(0, 19).replace("T", " "); // MySQL DATETIME format
}
```

---

## 5. Backend: API Endpoints

### 5.1 Update `ProductsController` (Store/Update)

**File:** `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php`

In the `hydrateVariant()` method, add sale price fields to the response payload:

```php
private function hydrateVariant(array $variant): array
{
    $priceCents = isset($variant['price_cents']) ? (int) $variant['price_cents'] : 0;
    $salePriceCents = isset($variant['sale_price_cents']) ? (int) $variant['sale_price_cents'] : null;

    // Resolve effective price using PriceHelper
    $resolved = PriceHelper::resolve($variant);

    return [
        'id'                => (int) ($variant['id'] ?? 0),
        'sku'               => (string) ($variant['sku'] ?? ''),
        'ean'               => isset($variant['ean']) ? (string) $variant['ean'] : null,
        'price_cents'       => $priceCents,
        'price'             => isset($variant['price'])
            ? (string) $variant['price']
            : $this->formatPrice($priceCents),
        'sale_price_cents'  => $salePriceCents,
        'sale_price'        => $salePriceCents !== null
            ? $this->formatPrice($salePriceCents)
            : null,
        'sale_start'        => $variant['sale_start'] ?? null,
        'sale_end'          => $variant['sale_end'] ?? null,
        'effective_price_cents' => $resolved['effective_price_cents'],
        'is_on_sale'        => $resolved['is_on_sale'],
        'sale_active'       => $resolved['sale_active'],
        'currency'          => (string) ($variant['currency'] ?? ''),
        'stock'             => (int) ($variant['stock'] ?? 0),
        'options'           => $variant['options'] ?? [],
        'weight'            => $variant['weight'] ?? null,
        'active'            => (int) (bool) ($variant['active'] ?? true),
        'is_digital'        => (int) (bool) ($variant['is_digital'] ?? false),
    ];
}
```

### 5.2 Update `ProductModel::save()`

**File:** `administrator/components/com_nxpeasycart/src/Model/ProductModel.php`

In the variant persistence loop, include sale price fields:

```php
// Inside saveVariants() method
$variantData = [
    'id'                => $variantId,
    'product_id'        => $productId,
    'sku'               => $variant['sku'],
    'ean'               => $variant['ean'] ?? null,
    'price_cents'       => (int) $variant['price_cents'],
    'sale_price_cents'  => isset($variant['sale_price_cents']) && $variant['sale_price_cents'] !== ''
        ? (int) $variant['sale_price_cents']
        : null,
    'sale_start'        => $variant['sale_start'] ?? null,
    'sale_end'          => $variant['sale_end'] ?? null,
    'currency'          => $variant['currency'],
    'stock'             => (int) ($variant['stock'] ?? 0),
    'options'           => isset($variant['options'])
        ? json_encode($variant['options'])
        : null,
    'weight'            => $variant['weight'] ?? null,
    'active'            => (int) (bool) ($variant['active'] ?? true),
    'is_digital'        => (int) (bool) ($variant['is_digital'] ?? false),
];
```

Also update `resolvePriceCents()` to handle `sale_price_cents` if passed during variant creation.

---

## 6. Frontend: Price Display Logic

### 6.1 Update All Product Queries

**Files to Update:**

- `components/com_nxpeasycart/src/Model/ProductModel.php` (detail view)
- `components/com_nxpeasycart/src/Model/CategoryModel.php` (category listings)
- `components/com_nxpeasycart/src/Model/LandingModel.php` (landing page)

**SQL Changes:**

Add `sale_price_cents`, `sale_start`, `sale_end` to all variant SELECT statements:

```php
$query->select([
    // ... existing fields
    $db->quoteName('v.price_cents'),
    $db->quoteName('v.sale_price_cents'),
    $db->quoteName('v.sale_start'),
    $db->quoteName('v.sale_end'),
    // ... rest of fields
])
->from($db->quoteName('#__nxp_easycart_variants', 'v'));
```

### 6.2 Update `ProductModel::getProduct()`

**File:** `components/com_nxpeasycart/src/Model/ProductModel.php`

After loading variants, compute the price range using `PriceHelper`:

```php
$variants = /* ... loaded from DB ... */;
$priceRange = PriceHelper::computePriceRange($variants, $baseCurrency);

$product['price'] = [
    'currency'  => $priceRange['currency'],
    'min_cents' => $priceRange['min_cents'],
    'max_cents' => $priceRange['max_cents'],
    'has_sale'  => $priceRange['has_sale'],
];

// Also resolve each variant's effective price
foreach ($variants as &$variant) {
    $resolved = PriceHelper::resolve($variant);
    $variant['effective_price_cents'] = $resolved['effective_price_cents'];
    $variant['is_on_sale'] = $resolved['is_on_sale'];
    $variant['sale_active'] = $resolved['sale_active'];
}
```

### 6.3 Update Product Template (`product/default.php`)

**File:** `components/com_nxpeasycart/tmpl/product/default.php`

Pass sale price fields to the Vue island payload:

```php
$variantPayload = array_map(
    static function (array $variant): array {
        $resolved = PriceHelper::resolve($variant);
        $currency = ConfigHelper::getBaseCurrency();

        return [
            'id'                    => (int) ($variant['id'] ?? 0),
            'sku'                   => (string) ($variant['sku'] ?? ''),
            'ean'                   => isset($variant['ean']) ? (string) $variant['ean'] : null,
            'price_cents'           => (int) ($variant['price_cents'] ?? 0),
            'sale_price_cents'      => isset($variant['sale_price_cents'])
                ? (int) $variant['sale_price_cents']
                : null,
            'sale_start'            => $variant['sale_start'] ?? null,
            'sale_end'              => $variant['sale_end'] ?? null,
            'effective_price_cents' => $resolved['effective_price_cents'],
            'is_on_sale'            => $resolved['is_on_sale'],
            'sale_active'           => $resolved['sale_active'],
            'currency'              => $currency,
            'price_label'           => MoneyHelper::format($resolved['regular_price_cents'], $currency),
            'sale_price_label'      => $resolved['sale_active']
                ? MoneyHelper::format($resolved['sale_price_cents'], $currency)
                : null,
            'stock'                 => (int) ($variant['stock'] ?? 0),
            'options'               => $variant['options'] ?? [],
            'weight'                => $variant['weight'] ?? null,
        ];
    },
    $variants
);
```

Add struck-through regular price display in the server-rendered fallback:

```php
<?php if ($resolved['sale_active']) : ?>
    <p class="nxp-ec-product__price">
        <span class="nxp-ec-price--sale">
            <?php echo MoneyHelper::format($resolved['sale_price_cents'], $currency); ?>
        </span>
        <span class="nxp-ec-price--regular nxp-ec-price--strikethrough">
            <?php echo MoneyHelper::format($resolved['regular_price_cents'], $currency); ?>
        </span>
    </p>
<?php else : ?>
    <p class="nxp-ec-product__price">
        <?php echo $priceLabel; ?>
    </p>
<?php endif; ?>
```

### 6.4 Update Product Island (`product.js`)

**File:** `media/com_nxpeasycart/src/site/islands/product.js`

Update the reactive price display logic:

```javascript
const currentPrice = computed(() => {
    if (!state.selectedVariantId) {
        return null;
    }

    const variant = state.variants.find(
        (v) => v.id === state.selectedVariantId
    );
    if (!variant) {
        return null;
    }

    return {
        regular: variant.price_label,
        sale: variant.sale_price_label,
        isOnSale: variant.is_on_sale && variant.sale_active,
        effectiveCents: variant.effective_price_cents,
    };
});
```

Update the template to show struck-through prices:

```javascript
// In the Vue template
<div class="nxp-ec-product__price-display">
    <template v-if="currentPrice?.isOnSale">
        <span class="nxp-ec-price--sale">{{ currentPrice.sale }}</span>
        <span class="nxp-ec-price--regular nxp-ec-price--strikethrough">
            {{ currentPrice.regular }}
        </span>
    </template>
    <template v-else>
        <span class="nxp-ec-price--regular">{{ currentPrice?.regular }}</span>
    </template>
</div>
```

### 6.5 Update Category & Landing Islands

**Files:**

- `media/com_nxpeasycart/src/site/islands/category.js`
- `media/com_nxpeasycart/src/site/islands/landing.js`

For product cards, display sale prices in the price range:

```javascript
// In the product card rendering logic
if (item.price?.has_sale) {
    priceLabel = `<span class="nxp-ec-price--sale">${formatMoney(min, currency, locale)}</span>`;

    if (max > min) {
        priceLabel += ` - <span class="nxp-ec-price--sale">${formatMoney(max, currency, locale)}</span>`;
    }
} else {
    // Existing regular price display
    priceLabel = formatMoney(min, currency, locale);
    if (max > min) {
        priceLabel += ` - ${formatMoney(max, currency, locale)}`;
    }
}
```

### 6.6 Update Cart/Checkout Display

**Files:**

- `components/com_nxpeasycart/src/Service/CartPresentationService.php`
- `media/com_nxpeasycart/src/site/islands/cart.js`
- `media/com_nxpeasycart/src/site/islands/checkout.js`

Cart items must display the effective sale price (already stored in cart from checkout):

```php
// In CartPresentationService::hydrate()
foreach ($items as &$item) {
    $variantId = $item['variant_id'] ?? null;
    $variant = /* ... load from DB ... */;

    $resolved = PriceHelper::resolve($variant);

    // Override cart-stored price with current effective price (security + consistency)
    $item['unit_price_cents'] = $resolved['effective_price_cents'];
    $item['is_on_sale'] = $resolved['sale_active'];
    $item['regular_price_cents'] = $resolved['regular_price_cents'];
    $item['sale_price_cents'] = $resolved['sale_price_cents'];
}
```

**Display Logic (cart.js / checkout.js):**

```javascript
<template v-if="item.is_on_sale">
    <span class="nxp-ec-cart-item__price--sale">
        {{ formatMoney(item.unit_price_cents, item.currency, locale) }}
    </span>
    <span class="nxp-ec-cart-item__price--regular nxp-ec-price--strikethrough">
        {{ formatMoney(item.regular_price_cents, item.currency, locale) }}
    </span>
</template>
<template v-else>
    {{ formatMoney(item.unit_price_cents, item.currency, locale) }}
</template>
```

---

## 7. Coupon System Integration: Double-Discount Prevention

### 7.1 Update `CouponService::validate()`

**File:** `administrator/components/com_nxpeasycart/src/Service/CouponService.php`

Add a new validation check to prevent coupons from applying to items already on sale:

```php
public function validate(string $code, int $subtotalCents, array $cartItems = []): array
{
    // ... existing validation (active, dates, usage, min_total) ...

    // NEW: Check if cart contains sale items
    $hasSaleItems = false;
    foreach ($cartItems as $item) {
        if (!empty($item['is_on_sale'])) {
            $hasSaleItems = true;
            break;
        }
    }

    if ($hasSaleItems) {
        return [
            'valid'          => false,
            'coupon'         => null,
            'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_NOT_VALID_WITH_SALE_ITEMS'),
            'discount_cents' => 0,
        ];
    }

    // ... rest of discount calculation ...
}
```

**Alternative Approach (More Flexible):**

Add a new field `#__nxp_easycart_coupons.exclude_sale_items` (TINYINT(1) DEFAULT 1) and make this behavior configurable per coupon. This allows merchants to choose whether specific coupons can stack with sales.

### 7.2 Update Cart Controller

**File:** `components/com_nxpeasycart/src/Controller/CartController.php`

In the `applyCoupon()` method, pass cart items to the validation:

```php
// Before validation
$items = $cart['items'] ?? [];
$enrichedItems = [];

foreach ($items as $item) {
    $variantId = $item['variant_id'] ?? null;
    if ($variantId) {
        $variant = $this->loadVariantForCheckout($db, $variantId);
        if ($variant) {
            $resolved = PriceHelper::resolve($variant);
            $enrichedItems[] = array_merge($item, [
                'is_on_sale' => $resolved['sale_active'],
            ]);
        }
    }
}

// Pass to validation
$validation = $coupons->validate($code, $subtotalCents, $enrichedItems);
```

---

## 8. Styling: CSS for Strikethrough Prices

### 8.1 Add CSS Rules

**File:** `media/com_nxpeasycart/css/site.css`

```css
/* Sale price styling */
.nxp-ec-price--sale {
    color: var(--nxp-ec-color-primary, #e74c3c);
    font-weight: 600;
    font-size: 1.125em;
}

.nxp-ec-price--regular.nxp-ec-price--strikethrough {
    text-decoration: line-through;
    color: var(--nxp-ec-color-muted, #888);
    font-size: 0.9em;
    margin-left: 0.5rem;
}

/* Product card sale badge (optional) */
.nxp-ec-product-card--on-sale::after {
    content: "SALE";
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: var(--nxp-ec-color-primary, #e74c3c);
    color: white;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: bold;
    border-radius: 3px;
    text-transform: uppercase;
}
```

---

## 9. Language Strings

### 9.1 Admin Language Constants

**File:** `administrator/language/en-GB/com_nxpeasycart.ini`

```ini
; Sale price fields
COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE="Sale Price (optional)"
COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE_HELP="Promotional price. Leave empty if not on sale."
COM_NXPEASYCART_FIELD_VARIANT_SALE_START="Sale Start Date"
COM_NXPEASYCART_FIELD_VARIANT_SALE_START_HELP="Leave empty to activate immediately."
COM_NXPEASYCART_FIELD_VARIANT_SALE_END="Sale End Date"
COM_NXPEASYCART_FIELD_VARIANT_SALE_END_HELP="Leave empty for no expiration."

; Validation messages
COM_NXPEASYCART_ERROR_VARIANT_SALE_PRICE_INVALID="Sale price cannot be negative."
COM_NXPEASYCART_WARNING_VARIANT_SALE_PRICE_NOT_LOWER="Sale price is equal to or higher than the regular price. This is unusual but allowed."

; Coupon double-discount prevention
COM_NXPEASYCART_ERROR_COUPON_NOT_VALID_WITH_SALE_ITEMS="This coupon cannot be applied to items already on sale."
```

### 9.2 Site Language Constants

**File:** `language/en-GB/com_nxpeasycart.ini`

```ini
; Price display
COM_NXPEASYCART_PRODUCT_PRICE_SALE="Sale"
COM_NXPEASYCART_PRODUCT_PRICE_WAS="Was"
COM_NXPEASYCART_CART_ITEM_ON_SALE="On sale"
```

---

## 10. Testing Strategy

### 10.1 Unit Tests (PHPUnit)

**File:** `tests/Unit/Helper/PriceHelperTest.php`

```php
<?php
namespace Joomla\Component\Nxpeasycart\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Joomla\Component\Nxpeasycart\Administrator\Helper\PriceHelper;
use DateTimeImmutable;

class PriceHelperTest extends TestCase
{
    public function testResolveNoSalePrice()
    {
        $variant = [
            'price_cents' => 1000,
            'sale_price_cents' => null,
        ];

        $result = PriceHelper::resolve($variant);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertFalse($result['is_on_sale']);
        $this->assertFalse($result['sale_active']);
    }

    public function testResolveSaleActiveDuringPeriod()
    {
        $now = new DateTimeImmutable('2025-01-15 12:00:00', new \DateTimeZone('UTC'));

        $variant = [
            'price_cents' => 1000,
            'sale_price_cents' => 750,
            'sale_start' => '2025-01-01 00:00:00',
            'sale_end' => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(750, $result['effective_price_cents']);
        $this->assertTrue($result['is_on_sale']);
        $this->assertTrue($result['sale_active']);
    }

    public function testResolveSaleNotYetStarted()
    {
        $now = new DateTimeImmutable('2024-12-15 12:00:00', new \DateTimeZone('UTC'));

        $variant = [
            'price_cents' => 1000,
            'sale_price_cents' => 750,
            'sale_start' => '2025-01-01 00:00:00',
            'sale_end' => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertTrue($result['is_on_sale']);
        $this->assertFalse($result['sale_active']); // Sale exists but not active yet
    }

    public function testResolveSaleExpired()
    {
        $now = new DateTimeImmutable('2025-02-15 12:00:00', new \DateTimeZone('UTC'));

        $variant = [
            'price_cents' => 1000,
            'sale_price_cents' => 750,
            'sale_start' => '2025-01-01 00:00:00',
            'sale_end' => '2025-01-31 23:59:59',
        ];

        $result = PriceHelper::resolve($variant, $now);

        $this->assertEquals(1000, $result['effective_price_cents']);
        $this->assertTrue($result['is_on_sale']);
        $this->assertFalse($result['sale_active']); // Sale exists but expired
    }
}
```

### 10.2 Manual Testing Checklist

**Admin Panel:**

- [ ] Create a new product with a variant that has a sale price set
- [ ] Set sale start date to tomorrow, verify price shows as regular in frontend
- [ ] Set sale start date to yesterday, verify sale price shows in frontend
- [ ] Set sale end date to yesterday, verify price reverts to regular
- [ ] Edit an existing variant to add sale price, verify it saves and displays correctly
- [ ] Try to set sale price higher than regular price, verify warning message appears (not error)
- [ ] Try to set negative sale price, verify error message appears
- [ ] Duplicate a variant with sale pricing, verify sale fields are copied

**Frontend Product Pages:**

- [ ] View product with active sale, verify struck-through regular price appears next to sale price
- [ ] View product with future sale, verify only regular price shows
- [ ] View product with expired sale, verify only regular price shows
- [ ] View product with multiple variants (some on sale, some not), verify correct prices per variant
- [ ] View product detail page, select variant with sale, verify price updates correctly
- [ ] View product without any sale, verify regular price display unchanged

**Category & Landing Pages:**

- [ ] View category with mixed sale/regular products, verify price badges display correctly
- [ ] View landing page, verify sale prices appear in product cards
- [ ] Check that price ranges account for sale prices (e.g., "$10 - $25" uses effective prices)

**Cart & Checkout:**

- [ ] Add item on sale to cart, verify sale price is used in line item
- [ ] Add item on sale to cart, verify struck-through regular price appears
- [ ] Add multiple items (some on sale, some not), verify subtotal calculation uses effective prices
- [ ] Add item to cart, let sale expire (manually update DB), refresh cart, verify price reverts to regular
- [ ] Add item to cart with active sale, apply coupon, verify error message about sale items

**Coupon Double-Discount:**

- [ ] Add regular-priced item to cart, apply coupon, verify discount applies
- [ ] Add sale-priced item to cart, apply coupon, verify error message appears
- [ ] Add mixed cart (sale + regular), apply coupon, verify error message appears
- [ ] Create a coupon with `exclude_sale_items = 0` (if implemented), verify it can stack with sales

**Locale & Currency:**

- [ ] Change display locale in settings, verify sale prices format correctly (e.g., Macedonian "1.390,00 ден.")
- [ ] Verify sale price respects base currency (single-currency MVP)

---

## 11. Migration Path for Existing Data

**Impact:** Existing variants in the database will have `NULL` values for the new sale price fields after running the migration. This is **safe** and requires **no data backfill**.

**Default Behavior:**

- `sale_price_cents = NULL` → No sale (falls back to regular price)
- `sale_start = NULL` → No start restriction (active immediately if sale price is set)
- `sale_end = NULL` → No end restriction (never expires)

**Post-Migration Actions:**

1. Merchants must manually edit products to add sale prices (via admin SPA).
2. No automatic migration of existing discounts (coupons remain separate).

---

## 12. Performance Considerations

### 12.1 Database Indexing

- **Composite index on `(sale_start, sale_end)`** enables efficient queries for "active sales" reports.
- **No additional JOINs required** for price resolution (all data in `variants` table).

### 12.2 Query Optimization

- `PriceHelper::resolve()` operates on in-memory variant objects (no additional DB queries).
- Price range calculation in category/landing views is done in PHP after variants are loaded (avoids complex SQL aggregations).

### 12.3 Caching

- Consider adding cache layer for "active sales" product IDs (e.g., Joomla PSR-16 cache with 5-minute TTL).
- Admin SPA can cache sale metadata per product to avoid repeated API calls.

---

## 13. Future Enhancements (Out of Scope for v0.2.0)

### 13.1 Bulk Sale Management

- Admin panel for applying sale prices to entire categories or product ranges.
- CSV import/export for sale pricing schedules.

### 13.2 Sale Price History

- Audit trail tracking when sale prices are created/modified/expired.
- Price change notifications for subscribed customers.

### 13.3 Advanced Sale Types

- "Buy X, Get Y% Off" (quantity-based discounts).
- "BOGO" (Buy One, Get One) promotions.
- Conditional sales (e.g., "10% off for logged-in users").

### 13.4 Sale Badge Customization

- Allow merchants to upload custom sale badge images.
- Configurable badge text ("SALE", "CLEARANCE", "50% OFF", etc.).

### 13.5 Scheduled Sales Reports

- Daily email digest of active/expiring sales.
- Revenue comparison reports (sale vs. regular price revenue).

---

## 14. Security Considerations

### 14.1 Price Integrity

- **Database is the single source of truth:** Cart-stored prices are **always** overridden by `PriceHelper::resolve()` during checkout to prevent price tampering (consistent with existing security fixes for regular prices).
- Sale dates are stored in **UTC** to prevent timezone manipulation.

### 14.2 ACL Enforcement

- Sale price editing requires `core.edit` permission on products (same as regular prices).
- No separate permission needed (simplifies onboarding).

### 14.3 Input Validation

- All date inputs sanitized and validated via Joomla's input filters.
- Sale price must be >= 0 (enforced by `VariantTable::check()`).

---

## 15. Documentation Updates

### 15.1 New Documentation File

**File:** `docs/sale-pricing.md`

Create a comprehensive guide covering:

- How to set up a sale (admin workflow)
- Date/time handling (UTC vs. local time)
- Price display logic (effective price hierarchy)
- Coupon interaction rules
- Troubleshooting common issues (e.g., "Why isn't my sale showing?")

### 15.2 Update Existing Docs

- **`README.md`:** Add "Sale Pricing" to feature list.
- **`docs/architecture.md`:** Document `PriceHelper` and sale price resolution flow.
- **`CHANGELOG.md`:** Add v0.2.0 entry with sale pricing feature.

---

## 16. Implementation Phases

### Phase 1: Database & Core Logic (Week 1)

- [ ] Create migration SQL (`0.2.0.sql`)
- [ ] Update install schema
- [ ] Implement `PriceHelper` class
- [ ] Write unit tests for `PriceHelper`
- [ ] Update `VariantTable::check()` validation

### Phase 2: Backend Admin (Week 2)

- [ ] Update `ProductEditor.vue` with sale price fields
- [ ] Add datetime-local input handling (UTC conversion)
- [ ] Update `ProductsController` API hydration
- [ ] Update `ProductModel::save()` persistence
- [ ] Add language strings (admin)

### Phase 3: Frontend Display (Week 3)

- [ ] Update all product queries (ProductModel, CategoryModel, LandingModel)
- [ ] Update `product/default.php` template
- [ ] Update `product.js` island
- [ ] Update `category.js` and `landing.js` islands
- [ ] Add CSS for strikethrough prices
- [ ] Add language strings (site)

### Phase 4: Cart & Checkout (Week 4)

- [ ] Update `CartPresentationService` to use `PriceHelper`
- [ ] Update `PaymentController::checkout()` to use effective prices
- [ ] Update `cart.js` island display
- [ ] Update `checkout.js` island display
- [ ] Update order confirmation emails

### Phase 5: Coupon Integration (Week 5)

- [ ] Update `CouponService::validate()` with sale item check
- [ ] Update `CartController::applyCoupon()` to pass cart item metadata
- [ ] Add error message for double-discount attempts
- [ ] Test coupon + sale interactions

### Phase 6: Testing & Polish (Week 6)

- [ ] Run full manual testing checklist
- [ ] Write integration tests for cart + coupon scenarios
- [ ] Update documentation (`docs/sale-pricing.md`)
- [ ] Update `README.md` and `CHANGELOG.md`
- [ ] Code review and security audit

---

## 17. Risk Register

| Risk                        | Impact | Likelihood | Mitigation                                                                                                             |
| --------------------------- | ------ | ---------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Timezone Confusion**      | High   | Medium     | Store all dates in UTC; document datetime-local conversion. Admin UI shows clear timezone hints.                       |
| **Price Tampering**         | High   | Low        | Use existing price security model: always recalculate from DB at checkout via `PriceHelper::resolve()`.                |
| **Coupon Stacking Abuse**   | Medium | Medium     | Validate cart items in `CouponService::validate()` and reject coupons if sale items present (configurable per coupon). |
| **Performance Degradation** | Medium | Low        | Use composite index on `(sale_start, sale_end)`. Price resolution is in-memory (no extra queries).                     |
| **Migration Breaks**        | Medium | Low        | Use `ALTER TABLE` (non-destructive). Existing variants default to NULL = no sale. Test on staging first.               |
| **UI Complexity**           | Low    | Medium     | Use collapsible "Sale Pricing" section in product editor. Disable date inputs when sale price is empty.                |
| **Currency Mismatch**       | Low    | Low        | Sale prices inherit same currency as regular prices (enforced by single-currency MVP).                                 |

---

## 18. Acceptance Criteria

**Feature is complete when:**

1. ✅ Database migration runs without errors on fresh and existing installs.
2. ✅ Admin product editor allows setting sale price + dates per variant.
3. ✅ Frontend product pages display struck-through regular prices when sale is active.
4. ✅ Category and landing pages show correct effective prices (sale if active, else regular).
5. ✅ Cart and checkout use sale prices when applicable.
6. ✅ Coupons cannot be applied to carts containing sale items (error message shown).
7. ✅ Sale prices automatically activate/expire based on `sale_start` / `sale_end` dates.
8. ✅ All unit tests pass (PHPUnit).
9. ✅ Manual testing checklist completed with 100% pass rate.
10. ✅ Documentation updated (`docs/sale-pricing.md`, `README.md`, `CHANGELOG.md`).

---

## 19. Rollout Plan

### Stage 1: Internal Testing (1 week)

- Deploy to development environment (`/var/www/html/j5.loc`).
- Run automated tests (PHPUnit).
- Manual QA using testing checklist.

### Stage 2: Beta Release (1 week)

- Deploy to staging site (`j5-staging.nexusplugins.com`).
- Invite 5-10 beta testers (existing customers).
- Collect feedback via feedback form.
- Fix critical bugs.

### Stage 3: Production Release (v0.2.0)

- Merge to `main` branch.
- Tag release `v0.2.0`.
- Build installable ZIP package.
- Update extension update server (`updates.xml`).
- Publish release notes on website.
- Announce via newsletter and social media.

---

## 20. Success Metrics

**Post-release tracking (30 days):**

- **Adoption Rate:** % of stores that enable sale pricing on at least one product.
- **Conversion Impact:** Compare conversion rates for sale vs. non-sale products.
- **Support Tickets:** Track sale-pricing-related support requests (target: <5% of total tickets).
- **Bug Reports:** Track sale-pricing-related bug reports (target: 0 critical, <3 minor).
- **Performance:** Monitor query performance on product listings (target: <50ms increase).

---

## 21. Appendix: SQL Examples

### A. Find All Active Sales

```sql
SELECT
    p.title AS product_title,
    v.sku,
    v.price_cents / 100 AS regular_price,
    v.sale_price_cents / 100 AS sale_price,
    v.sale_start,
    v.sale_end
FROM #__nxp_easycart_variants v
INNER JOIN #__nxp_easycart_products p ON p.id = v.product_id
WHERE v.sale_price_cents IS NOT NULL
  AND (v.sale_start IS NULL OR v.sale_start <= NOW())
  AND (v.sale_end IS NULL OR v.sale_end >= NOW())
  AND v.active = 1
  AND p.active = 1;
```

### B. Find Expiring Sales (Next 7 Days)

```sql
SELECT
    p.title AS product_title,
    v.sku,
    v.sale_end,
    DATEDIFF(v.sale_end, NOW()) AS days_remaining
FROM #__nxp_easycart_variants v
INNER JOIN #__nxp_easycart_products p ON p.id = v.product_id
WHERE v.sale_price_cents IS NOT NULL
  AND v.sale_end IS NOT NULL
  AND v.sale_end BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
  AND v.active = 1
  AND p.active = 1
ORDER BY v.sale_end ASC;
```

### C. Revenue Report: Sale vs. Regular

```sql
SELECT
    DATE(o.created) AS order_date,
    SUM(CASE WHEN oi.unit_price_cents < v.price_cents THEN oi.total_cents ELSE 0 END) / 100 AS sale_revenue,
    SUM(CASE WHEN oi.unit_price_cents >= v.price_cents THEN oi.total_cents ELSE 0 END) / 100 AS regular_revenue
FROM #__nxp_easycart_order_items oi
INNER JOIN #__nxp_easycart_orders o ON o.id = oi.order_id
LEFT JOIN #__nxp_easycart_variants v ON v.id = oi.variant_id
WHERE o.state = 'paid'
  AND o.created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(o.created)
ORDER BY order_date DESC;
```

---

## 22. Summary

This plan provides a **production-ready, secure, and extensible** implementation of sale pricing for NXP Easy Cart. By following the component's existing architecture patterns (single-currency MVP, price tampering prevention, cache-first admin, locale-aware formatting), the feature integrates seamlessly with minimal technical debt.

**Key Deliverables:**

1. Database schema with indexed sale price fields
2. Centralized `PriceHelper` for effective price resolution
3. Admin SPA sale price editor with datetime-local inputs
4. Frontend price display with struck-through regular prices
5. Coupon system integration preventing double-discounts
6. Comprehensive unit and manual testing coverage
7. Full documentation and migration guide

**Timeline:** 6 weeks (phased rollout)
**Risk Level:** Low (leverages existing patterns, non-breaking changes)
**Backward Compatibility:** 100% (NULL defaults for new fields)

---

**Next Steps:**

1. Review this plan with stakeholders.
2. Create GitHub issues for each phase.
3. Begin Phase 1 implementation (database + core logic).
4. Schedule weekly progress reviews.

**Questions or Feedback?** Contact the development team at dev@nexusplugins.com.
