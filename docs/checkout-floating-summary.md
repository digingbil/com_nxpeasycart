# Checkout Floating Summary Bar (v0.1.10)

This document describes the floating order summary feature that keeps pricing visible during checkout form completion.

## Problem

On the checkout page, the order summary (Subtotal, Shipping, Tax, Total) appears at the top of the page. When users scroll down to fill in their contact information, billing address, shipping address, and payment method selection, they lose sight of the order totals. This can cause:

1. Uncertainty about the final amount they're committing to
2. Need to scroll back up repeatedly to verify totals
3. Reduced confidence during the checkout process

## Solution

A floating summary bar that:
- Appears at the bottom of the viewport when the main totals scroll out of view
- Shows the same pricing breakdown (Subtotal, Shipping, Tax, Total)
- Disappears when the user scrolls back up and the main totals become visible
- Works on both desktop and mobile devices

## Implementation

### HTML Template (checkout.js)

A new floating summary element was added at the top of the checkout template:

```vue
<!-- Floating summary bar (visible when main totals scroll out of view) -->
<div
  class="nxp-ec-checkout__floating-summary"
  :class="{ 'is-visible': showFloatingSummary && !success }"
  aria-hidden="!showFloatingSummary"
>
  <div class="nxp-ec-checkout__floating-summary-inner">
    <div class="nxp-ec-checkout__floating-row">
      <span>{{ labels.subtotal }}</span>
      <strong>{{ formatMoney(subtotal) }}</strong>
    </div>
    <div class="nxp-ec-checkout__floating-row" v-if="selectedShippingCost > 0">
      <span>{{ labels.shipping }}</span>
      <strong>{{ formatMoney(selectedShippingCost) }}</strong>
    </div>
    <div class="nxp-ec-checkout__floating-row" v-if="showTax">
      <span>{{ taxLabel }}</span>
      <strong>{{ formatMoney(taxAmount) }}</strong>
    </div>
    <div class="nxp-ec-checkout__floating-row nxp-ec-checkout__floating-row--total">
      <span>{{ labels.total }}</span>
      <strong>{{ formatMoney(total) }}</strong>
    </div>
  </div>
</div>
```

### Vue Reactive State

The main totals element gets a template ref:

```vue
<div ref="totalsRef" class="nxp-ec-checkout__totals">
```

Two new reactive values track visibility:

```javascript
const totalsRef = ref(null);
const showFloatingSummary = ref(false);
```

### IntersectionObserver

On mount, an IntersectionObserver watches the main totals section:

```javascript
onMounted(() => {
    refreshCart();

    // Setup IntersectionObserver to show/hide floating summary
    if (totalsRef.value && "IntersectionObserver" in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                // Show floating summary when totals section is NOT visible
                showFloatingSummary.value = !entries[0].isIntersecting;
            },
            {
                root: null,
                rootMargin: "-80px 0px 0px 0px", // Account for potential fixed headers
                threshold: 0,
            }
        );
        observer.observe(totalsRef.value);
    }
});
```

The `rootMargin: "-80px 0px 0px 0px"` accounts for fixed headers that might obscure the top of the viewport.

### CSS Styles (site.scss)

```scss
// Floating summary bar - appears when main totals scroll out of view
&__floating-summary {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: var(--nxp-ec-color-surface);
    border-top: 1px solid var(--nxp-ec-color-border);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    padding: 0.75rem 1rem;
    transform: translateY(100%);
    opacity: 0;
    visibility: hidden;
    transition: transform 0.3s ease, opacity 0.3s ease, visibility 0.3s;

    &.is-visible {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
}

&__floating-summary-inner {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    max-width: 800px;
    margin: 0 auto;
    flex-wrap: wrap;
}

&__floating-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;

    span {
        color: var(--nxp-ec-color-muted);
    }

    strong {
        color: var(--nxp-ec-color-text);
        font-weight: 600;
    }

    &--total {
        font-size: 1rem;
        padding-left: 1rem;
        border-left: 2px solid var(--nxp-ec-color-primary);

        strong {
            color: var(--nxp-ec-color-primary);
            font-weight: 700;
        }
    }
}
```

### Mobile Responsive (< 600px)

```scss
@media (max-width: 600px) {
    .nxp-ec-checkout__floating-summary-inner {
        gap: 0.75rem 1.25rem;
        justify-content: space-between;
    }

    .nxp-ec-checkout__floating-row {
        font-size: 0.8rem;

        &--total {
            font-size: 0.9rem;
            flex-basis: 100%;
            justify-content: space-between;
            padding-left: 0;
            border-left: none;
            padding-top: 0.5rem;
            border-top: 1px solid var(--nxp-ec-color-border);
        }
    }
}
```

On mobile, the Total row takes full width and is separated by a top border for emphasis.

## Behavior

1. **Page load**: Floating bar is hidden (translated below viewport)
2. **User scrolls down**: When main totals section leaves viewport, floating bar slides up
3. **User scrolls up**: When main totals section re-enters viewport, floating bar slides down
4. **Checkout success**: Floating bar hidden when `success` state is true
5. **Fallback**: If IntersectionObserver isn't supported, bar stays hidden (graceful degradation)

## Visual Design

- **Background**: Uses `--nxp-ec-color-surface` (matches theme)
- **Shadow**: Subtle upward shadow for depth perception
- **Total highlight**: Primary color with left border accent
- **Animation**: 0.3s ease transitions for smooth show/hide
- **Layout**: Centered content with max-width constraint

## Accessibility

- `aria-hidden` attribute reflects visibility state
- Uses semantic `<span>` and `<strong>` for labels and values
- High contrast between labels (muted) and values (text/primary)
- Touch-friendly sizing on mobile

## Browser Support

- **IntersectionObserver**: Supported in all modern browsers (Chrome 51+, Firefox 55+, Safari 12.1+, Edge 15+)
- **Fallback**: Browsers without IntersectionObserver will simply not show the floating bar
- **CSS transforms**: Universal support in modern browsers

## File Changes

### Modified Files
- `media/com_nxpeasycart/src/site/islands/checkout.js` - Added floating summary template, refs, and IntersectionObserver
- `media/com_nxpeasycart/src/site.scss` - Added floating summary styles

## Related: Order Confirmation Page

The order confirmation page (`tmpl/order/default.php`) was also updated to show the complete pricing breakdown:

```php
<div class="nxp-ec-order-confirmation__totals">
    <div>
        <span><?php echo Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'); ?></span>
        <strong>...</strong>
    </div>
    <?php if (!empty($order['shipping_cents'])) : ?>
        <div>
            <span><?php echo Text::_('COM_NXPEASYCART_CART_SHIPPING'); ?></span>
            <strong>...</strong>
        </div>
    <?php endif; ?>
    <?php if (!empty($order['discount_cents'])) : ?>
        <div>
            <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_DISCOUNT'); ?></span>
            <strong>-...</strong>
        </div>
    <?php endif; ?>
    <?php if (!empty($order['tax_cents'])) : ?>
        <div>
            <span><?php echo Text::_('COM_NXPEASYCART_CART_TAX'); ?></span>
            <strong>...</strong>
        </div>
    <?php endif; ?>
    <div>
        <span><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
        <strong>...</strong>
    </div>
</div>
```

## Related: Tax Rate Matching Fix

A bug was fixed where tax rates weren't being applied correctly during checkout. The issue was that the backend was using `billing['country']` (display name like "Macedonia") instead of `billing['country_code']` (2-letter ISO code like "MK") to match tax rates.

Fixed in `PaymentController::calculateTaxAmount()`:

```php
// Before (broken)
$country = strtoupper(trim((string) ($billing['country'] ?? '')));

// After (fixed)
$country = strtoupper(trim((string) ($billing['country_code'] ?? $billing['country'] ?? '')));
$region  = strtolower(trim((string) ($billing['region_code'] ?? $billing['region'] ?? '')));
```
