# Sale Pricing for Variants (v0.2.0)

Sale pricing lets you set time-limited discounted prices on individual product variants. This covers schema changes, admin workflows, storefront display, and cart/checkout behaviour.

## What changed

- **Schema:** variants gain `sale_price_cents` (nullable INT), `sale_start` (nullable DATETIME), and `sale_end` (nullable DATETIME). A composite index on `(sale_start, sale_end)` optimises active-sale queries.
- **PriceHelper:** New centralised helper class (`Administrator\Helper\PriceHelper`) handles sale price resolution, date validation, and discount calculation.
- **Admin:** Product editor includes sale price, start date, and end date fields per variant with datetime-local inputs and UTC conversion.
- **Frontend:** Product pages, category listings, landing pages, cart, and checkout all display sale badges, strikethrough regular prices, and sale prices when applicable.
- **Cart & Checkout:** Line items show sale indicators; summary sections display total sale savings.
- **Coupons:** Coupon validation uses effective (sale) prices, so discounts stack correctly with sale pricing.

## Database fields

| Column | Type | Description |
|--------|------|-------------|
| `sale_price_cents` | INT NULL | Sale price in cents; NULL means no sale |
| `sale_start` | DATETIME NULL | UTC timestamp when sale begins; NULL = immediate |
| `sale_end` | DATETIME NULL | UTC timestamp when sale ends; NULL = no expiry |

## PriceHelper API

The `PriceHelper` class provides these static methods:

### resolve()

```php
$result = PriceHelper::resolve(
    object|array $variant,
    ?DateTimeImmutable $now = null
): array;
```

The `$variant` object/array should contain: `price_cents`, `sale_price_cents`, `sale_start`, `sale_end`.

Returns:
- `effective_price_cents` - The price to charge (sale or regular)
- `regular_price_cents` - Original price
- `sale_price_cents` - Sale price (null if no sale)
- `is_on_sale` - Boolean: true if sale is active and price is lower
- `sale_active` - Boolean: true if within sale period
- `discount_percent` - Integer percentage discount (0-100)

### isSaleActive()

```php
$active = PriceHelper::isSaleActive(?string $start, ?string $end): bool;
```

Checks if current UTC time is within the sale window.

### computePriceRange()

```php
$range = PriceHelper::computePriceRange(array $variants): array;
```

Returns min/max effective prices across variants:
- `min_cents`, `max_cents` - Effective price range
- `min_regular_cents`, `max_regular_cents` - Regular price range
- `has_sale` - Any variant has sale configured
- `any_sale_active` - Any variant currently on sale

## Admin workflow

1. **Product editor:** Each variant row includes:
   - **Sale Price** - Optional discounted price (shown in base currency)
   - **Sale Start** - Optional start datetime (browser-local, converted to UTC)
   - **Sale End** - Optional end datetime (browser-local, converted to UTC)

2. **Validation:** The system warns if sale price >= regular price (no actual discount).

3. **Preview:** Before saving, the UI shows the effective discount percentage when sale price is set.

## Storefront display

### Product page

- Sale badge appears next to price when variant is on sale
- Regular price shown with strikethrough
- Sale price highlighted
- Variant table shows per-variant sale status with discount percentages
- Schema.org structured data includes sale price for SEO

### Category & Landing pages

- Product cards show sale badge when any variant is on sale
- Price display: strikethrough regular price + sale price
- Badge uses distinct styling (background colour, bold text)

### Cart page

- Line items show sale badge and strikethrough regular price per-item
- Summary section shows "You save" line with total sale savings
- Mobile card view matches desktop display

### Checkout page

- Order summary items show sale badges
- Strikethrough regular prices per line item
- "Sale savings" row in totals when applicable

## CSS styling

Sale-related CSS classes use BEM naming under `nxp-ec-` namespace:

```scss
// CSS variables
--nxp-ec-color-sale: #dc2626;
--nxp-ec-color-sale-bg: #fef2f2;

// Common classes
.nxp-ec-*__sale-badge      // Sale badge styling
.nxp-ec-*__regular-price   // Strikethrough styling
.nxp-ec-*__sale-price      // Highlighted sale price
.nxp-ec-*__discount-badge  // Discount percentage badge
```

## Language strings

### Admin (com_nxpeasycart.ini)
- `COM_NXPEASYCART_PRODUCT_SALE_PRICE_LABEL` - "Sale Price"
- `COM_NXPEASYCART_PRODUCT_SALE_START_LABEL` - "Sale Start"
- `COM_NXPEASYCART_PRODUCT_SALE_END_LABEL` - "Sale End"
- `COM_NXPEASYCART_PRODUCT_SALE_PRICE_HINT` - Help text
- `COM_NXPEASYCART_PRODUCT_SALE_START_HINT` - Help text
- `COM_NXPEASYCART_PRODUCT_SALE_END_HINT` - Help text

### Site (com_nxpeasycart.ini)
- `COM_NXPEASYCART_PRODUCT_SALE_BADGE` - "Sale"
- `COM_NXPEASYCART_PRODUCT_DISCOUNT_OFF` - "%d%% off"
- `COM_NXPEASYCART_CATEGORY_SALE_BADGE` - "Sale"
- `COM_NXPEASYCART_CART_SALE_BADGE` - "Sale"
- `COM_NXPEASYCART_CART_SALE_SAVINGS` - "You save"
- `COM_NXPEASYCART_CHECKOUT_SALE_BADGE` - "Sale"
- `COM_NXPEASYCART_CHECKOUT_SALE_SAVINGS` - "Sale savings"

## Coupon integration

Coupons work correctly with sale prices:

1. **Subtotal calculation:** Uses effective (sale) prices from PriceHelper
2. **Minimum order threshold:** Checked against effective prices
3. **Discount stacking:** Coupon discounts apply after sale pricing
4. **Display:** Cart/checkout shows both sale savings and coupon discount separately

## Technical notes

### UTC timezone handling

- Sale dates are stored in UTC in the database
- Admin UI uses `datetime-local` inputs with browser-local display
- JavaScript converts to/from UTC when loading/saving
- PriceHelper compares against current UTC time

### Security

- Prices are always resolved from database, never from client-submitted data
- CartPresentationService and CartController use PriceHelper for authoritative pricing
- Sale price validation prevents negative prices

### Performance

- Composite index on `(sale_start, sale_end)` for efficient active-sale queries
- Category/Landing models use SQL CASE expressions for aggregate price calculations
- Batch variant loading in cart operations
