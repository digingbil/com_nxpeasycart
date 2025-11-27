# 🚨 CRITICAL SECURITY VULNERABILITY: Price Tampering Attack Vector

**Date**: 2025-11-27
**Severity**: CRITICAL (9.5/10)
**Status**: 🔴 VULNERABLE
**Attack Vector**: Client-side price manipulation

---

## Executive Summary

The checkout system **TRUSTS CLIENT-SUBMITTED PRICES** instead of recalculating them from the database. An attacker can manipulate cart data to purchase products at arbitrary prices (including $0.00).

---

## Vulnerability Analysis

### Attack Flow

1. **Customer adds product to cart** (legitimate)
2. **Server stores price in cart** from database ✅ (CartController.php:521)
3. **Attacker intercepts and modifies cart data** ⚠️
4. **Checkout uses tampered cart prices** ❌ (PaymentController.php:306-352)
5. **Order created with attacker's prices** ❌
6. **Payment gateway charged wrong amount** ❌

---

## Code Analysis

### ✅ SECURE: CartController.php (Lines 518-561)

**When adding items to cart**, prices ARE fetched from the database:

```php
private function upsertCartItem(array $items, object $product, object $variant, int $qty): array
{
    $baseCurrency = strtoupper((string) ($variant->currency ?? 'USD'));
    $unitPrice    = (int) ($variant->price_cents ?? 0);  // ✅ FROM DATABASE

    //...

    $items[] = [
        'product_id'       => (int) $product->id,
        'variant_id'       => (int) $variant->id,
        'title'            => $variant->sku ?? $product->title,
        'qty'              => $qty,
        'unit_price_cents' => $unitPrice,  // ✅ SERVER-SIDE PRICE
        'currency'         => $baseCurrency,
        'options'          => $options,
    ];

    return $items;
}
```

**Attack Surface**: Cart data is stored in the database (`#__nxp_easycart_carts.data`) as JSON. An attacker could:
- Directly modify the database (SQL injection elsewhere)
- Exploit a session hijacking vulnerability
- Manipulate serialized cart data

---

### ❌ VULNERABLE: PaymentController.php (Lines 306-352)

**During checkout**, prices are taken from the cart WITHOUT re-validation:

```php
private function buildOrderPayload(array $cart, array $payload): array
{
    $items = [];

    foreach ($cart['items'] as $item) {
        $items[] = [
            'sku'              => $item['sku']   ?? '',
            'title'            => $item['title'] ?? '',
            'qty'              => (int) ($item['qty'] ?? 1),
            'unit_price_cents' => (int) ($item['unit_price_cents'] ?? 0),  // ❌ TRUSTS CART
            'total_cents'      => (int) ($item['total_cents'] ?? 0),       // ❌ TRUSTS CART
            'currency'         => $item['currency']   ?? ($cart['summary']['currency'] ?? 'USD'),
            'product_id'       => $item['product_id'] ?? null,
            'variant_id'       => $item['variant_id'] ?? null,
            'tax_rate'         => '0.00',
        ];
    }

    return [
        'email'          => $payload['email']    ?? '',
        'billing'        => $payload['billing']  ?? [],
        'shipping'       => $payload['shipping'] ?? null,
        'items'          => $items,
        'currency'       => $currency,
        'state'          => 'pending',
        'subtotal_cents' => (int) ($cart['summary']['subtotal_cents'] ?? 0),  // ❌ TRUSTS CART
        'shipping_cents' => (int) ($payload['shipping_cents'] ?? 0),
        'tax_cents'      => (int) ($payload['tax_cents'] ?? 0),
        'discount_cents' => $discountCents,
        'total_cents'    => (int) ($cart['summary']['total_cents'] ?? 0),      // ❌ TRUSTS CART
        'coupon'         => $couponData,
    ];
}
```

**The checkout flow**:
1. Reads cart from session: `$cart = $presenter->hydrate($cartSession->current());` (line 110)
2. Builds order payload: `$orderPayload = $this->buildOrderPayload($cart, $payload);` (line 120)
3. Creates order: `$order = $orders->create($orderPayload);` (line 162)
4. Sends to payment gateway: `$checkout = $manager->createHostedCheckout($gateway, [...], $preferences);` (line 253)

**NO PRICE RECALCULATION HAPPENS!**

---

## Proof of Concept Attack

### Scenario 1: Direct Database Manipulation

```sql
-- Attacker finds their cart ID (e.g., via session cookie)
UPDATE #__nxp_easycart_carts
SET data = JSON_SET(
    data,
    '$.items[0].unit_price_cents', 1,  -- Change from $999.99 to $0.01
    '$.items[0].total_cents', 1
)
WHERE id = 'attacker-cart-id';
```

Result: $999.99 product purchased for $0.01

### Scenario 2: Session Hijacking + Cart Manipulation

1. Attacker hijacks user session (e.g., via XSS, session fixation)
2. Modifies cart data in session storage
3. Proceeds to checkout with tampered prices

### Scenario 3: Race Condition

1. Add expensive product to cart (price stored from DB)
2. Admin changes product price in database
3. Complete checkout with old (higher/lower) price from cart
4. Price discrepancy between cart and current database price

---

## Impact Assessment

### Financial Loss

- **Direct theft**: Products sold at $0.00 or arbitrary low prices
- **Inventory loss**: Physical goods shipped without proper payment
- **Gateway fees**: Payment processing fees on tampered amounts
- **Refund costs**: Legitimate price disputes

### Business Impact

- **Revenue loss**: Potentially unlimited if exploited at scale
- **Accounting chaos**: Order totals don't match actual variant prices
- **Audit failures**: Payment gateway amounts mismatch order records
- **Legal liability**: Breach of payment card industry (PCI) requirements

### Compliance Violations

- **PCI DSS Requirement 6.5.3**: Insecure cryptographic storage
- **PCI DSS Requirement 6.5.4**: Insecure communications
- **OWASP A08:2021**: Software and Data Integrity Failures

---

## Exploitation Difficulty

**Difficulty**: Medium

**Requirements**:
- Access to modify cart data (database access OR session hijacking)
- Knowledge of cart structure (easily obtained by inspecting API responses)
- Basic SQL/JSON manipulation skills

**Mitigations that DON'T exist**:
- ❌ No price integrity checks before checkout
- ❌ No comparison against current database prices
- ❌ No cryptographic signatures on cart data
- ❌ No audit log of cart price changes

---

## Recommended Fix

### IMMEDIATE (Emergency Patch)

Add price recalculation in `PaymentController::buildOrderPayload()`:

```php
private function buildOrderPayload(array $cart, array $payload): array
{
    $items = [];
    $db    = Factory::getContainer()->get(DatabaseInterface::class);

    foreach ($cart['items'] as $item) {
        $variantId = (int) ($item['variant_id'] ?? 0);

        if ($variantId <= 0) {
            throw new RuntimeException('Invalid variant ID in cart');
        }

        // ✅ FETCH CURRENT PRICE FROM DATABASE
        $variant = $this->loadVariantForCheckout($db, $variantId);

        if (!$variant) {
            throw new RuntimeException('Variant not found: ' . $variantId);
        }

        // ✅ USE DATABASE PRICE, NOT CART PRICE
        $unitPriceCents = (int) ($variant->price_cents ?? 0);
        $qty            = (int) ($item['qty'] ?? 1);
        $totalCents     = $unitPriceCents * $qty;

        $items[] = [
            'sku'              => $variant->sku ?? '',
            'title'            => $variant->sku ?? $item['title'] ?? '',
            'qty'              => $qty,
            'unit_price_cents' => $unitPriceCents,  // ✅ FROM DATABASE
            'total_cents'      => $totalCents,       // ✅ RECALCULATED
            'currency'         => $variant->currency ?? 'USD',
            'product_id'       => $item['product_id'] ?? null,
            'variant_id'       => $variantId,
            'tax_rate'         => '0.00',
        ];
    }

    // ✅ RECALCULATE SUBTOTAL FROM DATABASE PRICES
    $subtotalCents = array_reduce($items, fn($sum, $item) => $sum + $item['total_cents'], 0);

    return [
        'email'          => $payload['email']    ?? '',
        'billing'        => $payload['billing']  ?? [],
        'shipping'       => $payload['shipping'] ?? null,
        'items'          => $items,
        'currency'       => $cart['summary']['currency'] ?? 'USD',
        'state'          => 'pending',
        'subtotal_cents' => $subtotalCents,  // ✅ RECALCULATED
        'shipping_cents' => (int) ($payload['shipping_cents'] ?? 0),
        'tax_cents'      => (int) ($payload['tax_cents'] ?? 0),
        'discount_cents' => $discountCents,
        'total_cents'    => $subtotalCents + /* add shipping + tax - discount */,  // ✅ RECALCULATED
        'coupon'         => $couponData,
    ];
}

private function loadVariantForCheckout(DatabaseInterface $db, int $variantId): ?object
{
    $query = $db->getQuery(true)
        ->select(['id', 'sku', 'price_cents', 'currency', 'stock', 'active'])
        ->from('#__nxp_easycart_variants')
        ->where('id = :id')
        ->bind(':id', $variantId, ParameterType::INTEGER);

    $db->setQuery($query);
    return $db->loadObject();
}
```

### SECONDARY (Cart Integrity)

Add cryptographic signatures to cart data to detect tampering:

```php
// When saving cart
$cartData = [...];
$cartData['_signature'] = hash_hmac('sha256', json_encode($cartData), SECRET_KEY);

// When loading cart
$storedSignature = $cartData['_signature'] ?? '';
unset($cartData['_signature']);
$computedSignature = hash_hmac('sha256', json_encode($cartData), SECRET_KEY);

if (!hash_equals($storedSignature, $computedSignature)) {
    throw new RuntimeException('Cart data has been tampered with');
}
```

### LONG-TERM (Architecture)

1. **Never store prices in cart** - only store `product_id`, `variant_id`, `qty`
2. **Always recalculate prices** when displaying cart or processing checkout
3. **Log price changes** in audit table for forensics
4. **Add price-change alerts** when prices differ between cart and database

---

## Testing Procedure

### Test 1: Direct Price Manipulation

```sql
-- Setup: Add product to cart normally
-- Execute: Change price in cart table
UPDATE #__nxp_easycart_carts
SET data = JSON_SET(data, '$.items[0].unit_price_cents', 1)
WHERE session_id = 'test-session';

-- Test: Complete checkout
-- Expected (CURRENT): Order created with $0.01 price ❌
-- Expected (AFTER FIX): Order created with database price ✅
```

### Test 2: Cart-to-Checkout Price Validation

```php
// Add product at $99.99
$this->addToCart($productId, $variantId, 1);

// Change price in database to $49.99
$this->updateVariantPrice($variantId, 4999);

// Complete checkout
$order = $this->completeCheckout();

// Assertion (AFTER FIX)
$this->assertEquals(4999, $order['items'][0]['unit_price_cents']);
```

---

## References

- [OWASP: Insecure Direct Object Reference](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/05-Authorization_Testing/04-Testing_for_Insecure_Direct_Object_References)
- [CWE-471: Modification of Assumed-Immutable Data](https://cwe.mitre.org/data/definitions/471.html)
- [PCI DSS v4.0 Requirements](https://www.pcisecuritystandards.org/document_library/)

---

**Status**: 🔴 REQUIRES IMMEDIATE ATTENTION
**Priority**: P0 - CRITICAL
**ETA for Fix**: 2-4 hours

---

## Appendix: Full Attack Scenarios

### Scenario A: "Free Shopping Spree"

1. Customer adds 10x $1,000 laptops to cart ($10,000 total)
2. Uses SQL injection (or compromised admin account) to modify cart:
   ```sql
   UPDATE #__nxp_easycart_carts
   SET data = JSON_SET(
       data,
       '$.items[*].unit_price_cents', 1,
       '$.items[*].total_cents', 1
   );
   ```
3. Completes checkout paying $0.10 total (10 items × $0.01)
4. Order fulfilled, inventory depleted
5. **Loss**: $9,999.90 + shipping costs + payment fees

### Scenario B: "Negative Pricing"

1. Customer exploits integer overflow or JSON injection
2. Sets `unit_price_cents` to negative value: `-1000`
3. Checkout processes "refund" instead of payment
4. **Potential system crash or accounting chaos**

### Scenario C: "Currency Arbitrage"

1. Add product priced in USD ($100.00 = 10,000 cents)
2. Modify cart currency to EUR but keep price in cents
3. Payment gateway interprets as €100.00 (actual value €110 at exchange rate)
4. **Loss**: Currency conversion exploitation

---

**END OF REPORT**
