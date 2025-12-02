# Security Audit Fixes - Critical Vulnerabilities Resolved

This document details the fixes implemented for critical security vulnerabilities identified during the security audit.

---

## Issue 2: XSS Vulnerability in Checkout Island ✅ FIXED

### Vulnerability Details
- **Location**: `media/com_nxpeasycart/src/site/islands/checkout.js:364`
- **Severity**: HIGH (7.5/10)
- **Type**: Cross-Site Scripting (XSS)

### Problem
The checkout success message used `v-html` to render dynamic content:

```vue
<p v-html="formatOrderCreated(orderNumber)"></p>
```

The `formatOrderCreated()` function injected HTML directly:

```javascript
const formatOrderCreated = (orderNo) => {
    return labels.order_created.replace("%s", `<strong>${orderNo}</strong>`);
};
```

If an attacker could manipulate the `orderNumber` value (e.g., through a compromised database or API response), they could inject malicious HTML/JavaScript:

```javascript
orderNumber = "<script>alert('XSS')</script>"
// Would execute as: "Your order <script>alert('XSS')</script> was created."
```

### Solution
Replaced `v-html` with safe Vue text interpolation:

```vue
<p>
  <span v-for="(part, index) in formatOrderCreatedParts(orderNumber)" :key="index">
    <strong v-if="part.bold">{{ part.text }}</strong>
    <template v-else>{{ part.text }}</template>
  </span>
</p>
```

The new `formatOrderCreatedParts()` function returns an array of safe text parts:

```javascript
const formatOrderCreatedParts = (orderNo) => {
    const template = labels.order_created || "Your order %s was created successfully.";
    const parts = [];
    const marker = "%s";
    const index = template.indexOf(marker);

    if (index === -1) {
        parts.push({ text: template, bold: false });
    } else {
        if (index > 0) {
            parts.push({ text: template.substring(0, index), bold: false });
        }
        parts.push({ text: String(orderNo || ""), bold: true });
        if (index + marker.length < template.length) {
            parts.push({ text: template.substring(index + marker.length), bold: false });
        }
    }
    return parts;
};
```

### Protection Mechanism
- Vue's `{{ }}` interpolation automatically escapes all HTML entities
- `orderNumber` is always converted to a string and rendered as plain text
- Malicious HTML/JavaScript cannot execute

### Testing
Tested with malicious payloads:
- `orderNumber = "<script>alert('XSS')</script>"` → Renders as plain text
- `orderNumber = "<img src=x onerror=alert('XSS')>"` → Renders as plain text
- All HTML entities are properly escaped by Vue

---

## Issue 3: Weak Webhook Security ✅ FIXED

### Part A: Stripe Webhook Security

#### Vulnerability Details
- **Location**: `administrator/components/com_nxpeasycart/src/Payment/StripeGateway.php:80-90`
- **Severity**: CRITICAL (9.0/10)
- **Type**: Authentication Bypass / Webhook Forgery

#### Problem
Signature validation was **optional** in the original code:

```php
$webhookSecret = trim((string) ($this->config['webhook_secret'] ?? ''));

if ($webhookSecret !== '') {
    // Signature validation only runs if webhook_secret is configured
    $signature = $context['Stripe-Signature'] ?? '';
    if (!$this->verifySignature($payload, $signature, $webhookSecret)) {
        throw new RuntimeException('Invalid signature');
    }
}
// If webhook_secret is empty, webhooks are processed without validation!
```

**Attack Scenario**: An attacker could forge Stripe webhook events to:
- Mark unpaid orders as "paid"
- Trigger order fulfillment without payment
- Manipulate inventory and customer data

#### Solution
Made `webhook_secret` **mandatory**:

```php
public function handleWebhook(string $payload, array $context = []): array
{
    $webhookSecret = trim((string) ($this->config['webhook_secret'] ?? ''));

    // SECURITY: Webhook secret is mandatory to prevent webhook forgery
    if ($webhookSecret === '') {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_WEBHOOK_SECRET_MISSING'));
    }

    $signature = $context['Stripe-Signature'] ?? '';

    if (!$this->verifySignature($payload, $signature, $webhookSecret)) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_STRIPE_SIGNATURE_INVALID'));
    }

    // Continue processing...
}
```

#### Configuration Requirement
Merchants **must** configure the Stripe webhook secret in the admin panel before webhooks will be accepted.

**Setup Steps**:
1. Log into Stripe Dashboard
2. Navigate to Developers → Webhooks
3. Create a new webhook endpoint pointing to: `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.stripe`
4. Copy the "Signing secret" (starts with `whsec_`)
5. Paste into NXP Easy Cart → Settings → Payments → Stripe → Webhook Secret

---

### Part B: PayPal Webhook Security

#### Vulnerability Details
- **Location**: `administrator/components/com_nxpeasycart/src/Payment/PayPalGateway.php:102-129`
- **Severity**: CRITICAL (9.5/10)
- **Type**: Zero Authentication / Webhook Forgery

#### Problem
PayPal webhooks had **NO signature validation at all**:

```php
public function handleWebhook(string $payload, array $context = []): array
{
    $event = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid JSON');
    }

    // No signature validation - any POST request is accepted!
    $resource = $event['resource'] ?? [];
    // Process payment...
}
```

**Attack Scenario**: Anyone could POST a fake webhook to:
- Mark orders as "COMPLETED" without payment
- Trigger refunds and inventory adjustments
- Manipulate order states arbitrarily

#### Solution
Implemented PayPal's webhook verification API:

```php
public function handleWebhook(string $payload, array $context = []): array
{
    // SECURITY: Verify PayPal webhook signature before processing
    $this->verifyWebhookSignature($payload, $context);

    $event = json_decode($payload, true);
    // Continue processing...
}

private function verifyWebhookSignature(string $payload, array $context): void
{
    $webhookId = trim((string) ($this->config['webhook_id'] ?? ''));

    // SECURITY: Webhook ID is mandatory to prevent webhook forgery
    if ($webhookId === '') {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_ID_MISSING'));
    }

    // Extract required headers
    $transmissionId   = $context['PayPal-Transmission-Id']   ?? '';
    $transmissionTime = $context['PayPal-Transmission-Time'] ?? '';
    $transmissionSig  = $context['PayPal-Transmission-Sig']  ?? '';
    $certUrl          = $context['PayPal-Cert-Url']          ?? '';
    $authAlgo         = $context['PayPal-Auth-Algo']         ?? '';

    if (empty($transmissionId) || empty($transmissionTime) || empty($transmissionSig)) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_HEADERS_MISSING'));
    }

    // Call PayPal's verification API
    $verificationBody = [
        'transmission_id'   => $transmissionId,
        'transmission_time' => $transmissionTime,
        'cert_url'          => $certUrl,
        'auth_algo'         => $authAlgo,
        'transmission_sig'  => $transmissionSig,
        'webhook_id'        => $webhookId,
        'webhook_event'     => json_decode($payload, true),
    ];

    $response = $this->http->request('POST', $this->apiBase() . '/v1/notifications/verify-webhook-signature', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ],
        'json' => $verificationBody,
    ]);

    $result = json_decode((string) $response->getBody(), true);
    $verificationStatus = strtoupper((string) ($result['verification_status'] ?? ''));

    if ($verificationStatus !== 'SUCCESS') {
        throw new RuntimeException('PayPal webhook signature verification failed');
    }
}
```

#### Configuration Requirement
Merchants **must** configure the PayPal webhook ID before webhooks will be accepted.

**Setup Steps**:
1. Log into PayPal Developer Dashboard
2. Navigate to Apps & Credentials → Your App → Webhooks
3. Create a webhook endpoint: `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal`
4. Subscribe to events: `PAYMENT.CAPTURE.COMPLETED`, `CHECKOUT.ORDER.APPROVED`
5. Copy the "Webhook ID" from the webhook details
6. Paste into NXP Easy Cart → Settings → Payments → PayPal → Webhook ID

---

## Language Strings Added

### Stripe Errors
```ini
COM_NXPEASYCART_ERROR_STRIPE_WEBHOOK_SECRET_MISSING="Stripe webhook secret is not configured. Webhook signature validation is mandatory for security."
```

### PayPal Errors
```ini
COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_ID_MISSING="PayPal webhook ID is not configured. Webhook signature validation is mandatory for security."
COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_HEADERS_MISSING="PayPal webhook signature headers are missing."
COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_VERIFICATION_FAILED="PayPal webhook signature verification request failed."
COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_VERIFICATION_INVALID="PayPal webhook verification response is invalid."
COM_NXPEASYCART_ERROR_PAYPAL_WEBHOOK_SIGNATURE_INVALID="PayPal webhook signature verification failed."
```

---

## Testing Recommendations

### XSS Testing
1. Simulate checkout completion with malicious order numbers
2. Inject `<script>`, `<img>`, `<iframe>` tags in order number field
3. Verify all HTML is rendered as plain text in browser

### Webhook Security Testing

#### Stripe
1. Send webhook without `webhook_secret` configured → Should reject with 400
2. Send webhook with invalid signature → Should reject with 400
3. Send legitimate webhook from Stripe → Should process successfully

#### PayPal
1. Send webhook without `webhook_id` configured → Should reject with 400
2. Send webhook with missing headers → Should reject with 400
3. Send webhook with invalid signature → Should reject after API verification
4. Send legitimate webhook from PayPal → Should process successfully

---

## Deployment Checklist

Before deploying to production:

- [x] XSS fix implemented and tested
- [x] Stripe webhook signature enforcement implemented
- [x] PayPal webhook signature verification implemented
- [x] Site bundle rebuilt with fixes
- [ ] Configure Stripe webhook secret in production
- [ ] Configure PayPal webhook ID in production
- [ ] Test webhook endpoints with real gateway data
- [ ] Monitor webhook logs for rejected requests

---

## Impact Assessment

### Before Fixes
- **XSS Risk**: Any stored XSS in order numbers would execute in customer browsers
- **Webhook Forgery**: Attackers could mark orders as paid without payment
- **Financial Loss**: Potential for inventory theft, unauthorized refunds
- **Data Breach**: Customer order data could be manipulated

### After Fixes
- **XSS Protected**: All dynamic content safely rendered as text
- **Webhook Security**: Cryptographic signature validation enforced
- **Attack Surface**: Reduced to near-zero for these vectors
- **Compliance**: Meets PCI-DSS requirements for webhook security

---

## References

- [Stripe Webhook Security](https://stripe.com/docs/webhooks/signatures)
- [PayPal Webhook Verification](https://developer.paypal.com/api/rest/webhooks/)
- [OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)

---

---

## Issue 4: Price Tampering Vulnerability ✅ FIXED

### Vulnerability Details
- **Location**: `components/com_nxpeasycart/src/Controller/PaymentController.php:306-352`
- **Severity**: CRITICAL (9.5/10)
- **Type**: Price Manipulation / Data Integrity Failure

### Problem
The checkout system trusted prices stored in the cart database instead of recalculating them from the authoritative source (variants table). An attacker could manipulate cart data to purchase products at arbitrary prices.

**Attack Scenario**: Attacker modifies cart JSON in database:
```sql
UPDATE #__nxp_easycart_carts
SET data = JSON_SET(data, '$.items[0].unit_price_cents', 1)
WHERE session_id = 'victim-session';
```
Result: $999.99 product sold for $0.01

### Solution
Implemented complete price recalculation from database at checkout:

**PaymentController.php (Lines 306-385)**:
```php
// SECURITY: Recalculate ALL prices from database - never trust cart prices
foreach ($cart['items'] as $item) {
    $variantId = (int) ($item['variant_id'] ?? 0);

    // Fetch CURRENT price from database
    $variant = $this->loadVariantForCheckout($db, $variantId);

    // Use database price, NOT cart price
    $unitPriceCents = (int) ($variant->price_cents ?? 0);
    $totalCents     = $unitPriceCents * $qty;
}

// Recalculate subtotal from database prices
$subtotalCents = array_reduce($items, static fn($sum, $item) => $sum + ($item['total_cents'] ?? 0), 0);
```

**CartPresentationService.php (Lines 97-101)**:
```php
// SECURITY: Always use database price, never trust cart-stored prices
$priceCents = ($variant['price_cents'] ?? 0);  // FROM DATABASE ONLY
```

### Protection Mechanism
- Cart becomes a "shopping list" (variant IDs + quantities only)
- Database is the ONLY authoritative price source
- ALL prices recalculated from database at checkout and cart display
- Validates variant exists and is active before allowing purchase

### Testing
Attack simulation performed:
- Tampered cart price from 23400 cents to 1 cent in database
- Checkout used database price (23400 cents), ignoring tampered value
- Inactive variants blocked from checkout

**Complete details**: See `docs/security-price-tampering-fix.md`

---

---

## Issue 5: Coupon Discount Tampering Vulnerability ✅ FIXED

### Vulnerability Details
- **Location**: `components/com_nxpeasycart/src/Controller/CartController.php:680-686`
- **Severity**: HIGH (8.0/10)
- **Type**: Discount Calculation Manipulation / Revenue Loss

### Problem
The coupon discount calculation used cart-stored prices instead of database prices. An attacker could tamper with cart prices to reduce the discount amount, causing revenue loss.

**Attack Scenario**:
```sql
-- Product costs $100, 50% coupon available
UPDATE #__nxp_easycart_carts
SET data = JSON_SET(data, '$.items[0].unit_price_cents', 1000)  -- Change to $10
WHERE session_id = 'victim';

-- Apply 50% coupon: 50% of $10 = $5 discount
-- Checkout: $100 (real price) - $5 (wrong discount) = $95 paid
-- Expected: $100 - $50 = $50 paid
-- Merchant loses: $45
```

### Solution
Implemented complete discount recalculation from database prices:

**CartController.php applyCoupon() (Lines 680-709)**:
```php
// SECURITY: Calculate cart subtotal from DATABASE PRICES
$subtotalCents = 0;
foreach ($items as $item) {
    $variantId = (int) ($item['variant_id'] ?? 0);

    // Fetch current price from database
    $variant = $this->loadVariantForCoupon($db, $variantId);

    $unitPrice = (int) ($variant->price_cents ?? 0);  // FROM DATABASE
    $subtotalCents += $unitPrice * $qty;
}

// Discount calculated from REAL database subtotal
$validation = $couponService->validate($code, $subtotalCents);
```

**PaymentController.php buildOrderPayload() (Lines 359-376)**:
```php
// SECURITY: Recalculate coupon discount from database subtotal
$discountCents = $this->calculateCouponDiscount(
    $couponData,
    $subtotalCents  // ALREADY RECALCULATED FROM DATABASE
);
```

### Protection Mechanism
- Coupon discount ALWAYS calculated from database prices
- Checkout recalculates discount from database subtotal
- Cart-stored discount values completely ignored
- Supports both percentage and fixed-amount coupons
- Prevents both revenue loss and customer overcharges

### Testing
Attack simulation performed:
- Tampered cart price from $100 to $10
- Applied 50% coupon
- Discount calculated as $50 (from database), not $5 (from cart)
- Revenue protected

**Complete details**: See `docs/security-coupon-discount-fix.md`

### Bug Fix: Coupon Type Mismatch (2025-11-27)
**Issue**: After implementing the security fix, coupons weren't applying discounts at checkout. Investigation revealed a type mismatch:
- Database stores coupon type as `"percent"`
- `calculateCouponDiscount()` only checked for `"percentage"`
- Result: All percentage coupons returned 0 discount

**Fix**: Updated `PaymentController::calculateCouponDiscount()` line 1064 to accept both `"percent"` and `"percentage"`:
```php
switch ($type) {
    case 'percent':      // ← Added to match database value
    case 'percentage':
        return (int) round(($subtotalCents * $value) / 100);
```

**Verification**: Tested with POPUST (10% off) coupon:
- Subtotal: $345.00
- Discount: $34.50 ✓
- Total: $310.50 ✓
- Order, email, and admin all show correct discount ✓

---

**Status**: ✅ All critical vulnerabilities resolved
**Date**: 2025-11-27
**Reviewed**: Security audit issues #2, #3, #4, and #5
**Bug Fix**: Coupon type mismatch resolved 2025-11-27
