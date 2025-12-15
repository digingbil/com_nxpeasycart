# Restrict Coupon Usage Per User - Implementation Plan

## Overview

Currently, the `max_uses` field on coupons is a **global limit** - it tracks total usage across all users. This allows the same user to use a coupon repeatedly until the global limit is exhausted.

This plan adds **per-user usage limits** to prevent abuse and provide more control over coupon distribution.

## Current State

### Coupons Table (`#__nxp_easycart_coupons`)
| Column | Type | Description |
|--------|------|-------------|
| `max_uses` | INT NULL | Global maximum uses (NULL = unlimited) |
| `times_used` | INT | Global counter incremented on order completion |

### Current Flow
1. User applies coupon to cart
2. Validation checks: `times_used < max_uses`
3. Order completes → `incrementUsage()` increments `times_used`
4. **Problem**: Same user can repeat steps 1-3 indefinitely

## Proposed Solution

### New Database Schema

#### 1. Add column to `#__nxp_easycart_coupons`

```sql
ALTER TABLE `#__nxp_easycart_coupons`
  ADD COLUMN `max_uses_per_user` INT UNSIGNED NULL DEFAULT NULL AFTER `max_uses`;
```

| Column | Type | Description |
|--------|------|-------------|
| `max_uses_per_user` | INT UNSIGNED NULL | Per-user limit (NULL = unlimited per user, 1 = single use per user) |

#### 2. Create usage tracking table `#__nxp_easycart_coupon_usage`

```sql
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_coupon_usage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `guest_email` VARCHAR(255) NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_coupon_usage_coupon` (`coupon_id`),
  KEY `idx_nxp_coupon_usage_user` (`coupon_id`, `user_id`),
  KEY `idx_nxp_coupon_usage_email` (`coupon_id`, `guest_email`(100)),
  KEY `idx_nxp_coupon_usage_order` (`order_id`),
  CONSTRAINT `fk_nxp_coupon_usage_coupon` FOREIGN KEY (`coupon_id`)
    REFERENCES `#__nxp_easycart_coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Design decisions:**
- `user_id` NULL for guest checkouts
- `guest_email` for tracking guest usage (prevents same email using coupon repeatedly)
- `order_id` links to the order for audit trail
- Foreign key with CASCADE ensures cleanup when coupon is deleted

## Implementation Steps

### Phase 1: Database Changes

#### 1.1 Update `install.mysql.utf8.sql`
- Add `max_uses_per_user` column to coupons table
- Add `#__nxp_easycart_coupon_usage` table definition

#### 1.2 Create migration `sql/updates/mysql/0.2.1.sql`
```sql
-- Add per-user limit column
ALTER TABLE `#__nxp_easycart_coupons`
  ADD COLUMN `max_uses_per_user` INT UNSIGNED NULL DEFAULT NULL AFTER `max_uses`;

-- Create usage tracking table
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_coupon_usage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `guest_email` VARCHAR(255) NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_coupon_usage_coupon` (`coupon_id`),
  KEY `idx_nxp_coupon_usage_user` (`coupon_id`, `user_id`),
  KEY `idx_nxp_coupon_usage_email` (`coupon_id`, `guest_email`(100)),
  KEY `idx_nxp_coupon_usage_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Phase 2: Backend Service Changes

#### 2.1 Update `CouponService.php`

**Add new methods:**

```php
/**
 * Count how many times a user has used a specific coupon.
 *
 * @param int $couponId
 * @param int|null $userId (null for guests)
 * @param string|null $email (for guest tracking)
 * @return int
 */
public function getUserUsageCount(int $couponId, ?int $userId, ?string $email = null): int

/**
 * Record coupon usage after successful order.
 *
 * @param int $couponId
 * @param int $orderId
 * @param int|null $userId
 * @param string|null $guestEmail
 * @return void
 */
public function recordUsage(int $couponId, int $orderId, ?int $userId, ?string $guestEmail = null): void
```

**Update `validate()` method signature:**

```php
public function validate(
    string $code,
    int $subtotalCents,
    bool $hasSaleItems = false,
    ?int $userId = null,
    ?string $guestEmail = null
): array
```

**Add per-user validation logic:**

```php
// Check per-user usage limit
if ($coupon['max_uses_per_user'] !== null) {
    $userUsageCount = $this->getUserUsageCount(
        $coupon['id'],
        $userId,
        $guestEmail
    );

    if ($userUsageCount >= $coupon['max_uses_per_user']) {
        return [
            'valid'          => false,
            'coupon'         => $coupon,
            'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_USER_LIMIT_REACHED'),
            'discount_cents' => 0,
        ];
    }
}
```

**Update `mapRow()`:**

```php
'max_uses_per_user' => $row->max_uses_per_user !== null ? (int) $row->max_uses_per_user : null,
```

**Update `normalisePayload()`:**

```php
$maxUsesPerUser = $data['max_uses_per_user'] !== null && $data['max_uses_per_user'] !== ''
    ? (int) $data['max_uses_per_user']
    : null;

// Add to return array
'max_uses_per_user' => $maxUsesPerUser,
```

#### 2.2 Update `CartController.php`

**Update `applyCoupon()` method:**

```php
// Get current user info
$user = Factory::getApplication()->getIdentity();
$userId = $user && !$user->guest ? (int) $user->id : null;

// For guests, we need email from cart/checkout data
$guestEmail = null;
if ($userId === null && !empty($payload['customer']['email'])) {
    $guestEmail = strtolower(trim($payload['customer']['email']));
}

// Pass to validation
$validation = $couponService->validate(
    $code,
    $subtotalCents,
    $hasSaleItems,
    $userId,
    $guestEmail
);
```

#### 2.3 Update Order Completion

**In `CheckoutService.php` or `PaymentController.php`:**

After successful order creation, record coupon usage:

```php
if (!empty($orderData['coupon']['id'])) {
    $couponService->recordUsage(
        (int) $orderData['coupon']['id'],
        $orderId,
        $userId,
        $guestEmail
    );

    // Also increment global counter (existing behavior)
    $couponService->incrementUsage((int) $orderData['coupon']['id']);
}
```

### Phase 3: Admin UI Changes

#### 3.1 Update `CouponsPanel.vue`

**Add form field after max_uses:**

```vue
<div class="nxp-ec-form-field">
    <label class="nxp-ec-form-label" for="coupon-max-uses-per-user">
        {{ __("COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER", "Max uses per user") }}
    </label>
    <input
        id="coupon-max-uses-per-user"
        class="nxp-ec-form-input"
        type="number"
        min="0"
        step="1"
        v-model.number="draft.max_uses_per_user"
        :placeholder="__('COM_NXPEASYCART_COUPONS_FORM_UNLIMITED', 'Unlimited')"
    />
    <small class="nxp-ec-form-hint">
        {{ __("COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER_HINT", "Leave empty for unlimited uses per user.") }}
    </small>
</div>
```

**Update draft reactive object:**

```javascript
const draft = reactive({
    // ... existing fields
    max_uses_per_user: null,
});
```

**Update `startCreate()`, `startEdit()`, `emitSave()`** to include `max_uses_per_user`.

### Phase 4: Language Strings

#### 4.1 Site language file (`language/en-GB/com_nxpeasycart.ini`)

```ini
COM_NXPEASYCART_ERROR_COUPON_USER_LIMIT_REACHED="You have already used this coupon the maximum number of times allowed."
```

#### 4.2 Admin language file (`administrator/language/en-GB/com_nxpeasycart.ini`)

```ini
COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER="Max uses per user"
COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER_HINT="Limit how many times a single user can use this coupon. Leave empty for unlimited."
COM_NXPEASYCART_COUPONS_FORM_UNLIMITED="Unlimited"
```

## Validation Logic Summary

When a coupon is applied, validate in this order:

1. **Coupon exists** - code is valid
2. **Coupon is active** - `active = 1`
3. **Date range** - within `start` and `end` dates
4. **Global usage limit** - `times_used < max_uses` (if set)
5. **Per-user usage limit** - user's count < `max_uses_per_user` (if set) ← NEW
6. **Sale items restriction** - `allow_sale_items` check (if cart has sale items)
7. **Minimum order total** - subtotal >= `min_total_cents`

## Edge Cases & Considerations

### Guest Checkout
- Track by email address (normalized to lowercase)
- Guest could use different email addresses to bypass limit
- This is acceptable - same limitation exists in most e-commerce platforms
- Optional enhancement: Could also track by IP address (not recommended due to shared IPs)

### User Registers After Guest Purchase
- Usage tracked separately for `user_id` vs `guest_email`
- If user registers with same email, they could potentially use coupon again
- Optional enhancement: Merge guest usage records when user registers

### Email Not Yet Entered
- During cart phase, guest email may not be known
- Coupon validation at cart stage only checks logged-in user
- Full validation (including guest email) happens at checkout
- Consider: Show warning if coupon may be invalid at checkout

### Coupon Deletion
- Foreign key CASCADE deletes usage records automatically
- No orphaned records

### Performance
- Index on `(coupon_id, user_id)` for fast logged-in user lookups
- Index on `(coupon_id, guest_email)` for guest lookups
- Usage count query is simple COUNT with WHERE clause

## Testing Checklist

### Unit Tests
- [ ] `CouponService::getUserUsageCount()` returns correct count
- [ ] `CouponService::recordUsage()` creates record correctly
- [ ] `CouponService::validate()` respects per-user limit for logged-in users
- [ ] `CouponService::validate()` respects per-user limit for guests
- [ ] `CouponService::validate()` allows unlimited when `max_uses_per_user` is NULL

### Integration Tests
- [ ] Logged-in user can use coupon up to limit
- [ ] Logged-in user blocked after reaching limit
- [ ] Guest can use coupon up to limit (same email)
- [ ] Guest blocked after reaching limit
- [ ] Different users can each use coupon up to their individual limits
- [ ] Global `max_uses` still works alongside per-user limit
- [ ] Usage recorded correctly after order completion

### Admin UI Tests
- [ ] New field displays in coupon form
- [ ] Field saves correctly (including NULL for unlimited)
- [ ] Field loads correctly when editing existing coupon
- [ ] Validation prevents negative values

## File Changes Summary

| File | Action |
|------|--------|
| `administrator/components/com_nxpeasycart/sql/install.mysql.utf8.sql` | Add column + new table |
| `administrator/components/com_nxpeasycart/sql/updates/mysql/0.2.1.sql` | Create migration |
| `administrator/components/com_nxpeasycart/src/Service/CouponService.php` | Add methods, update validate() |
| `components/com_nxpeasycart/src/Controller/CartController.php` | Pass user info to validate() |
| `components/com_nxpeasycart/src/Controller/PaymentController.php` | Record usage on order completion |
| `media/com_nxpeasycart/src/app/components/CouponsPanel.vue` | Add form field |
| `language/en-GB/com_nxpeasycart.ini` | Add error message |
| `administrator/language/en-GB/com_nxpeasycart.ini` | Add form labels |

## Default Behavior

- `max_uses_per_user = NULL` → Unlimited uses per user (backward compatible)
- `max_uses_per_user = 1` → Single use per user (most common for promotional coupons)
- `max_uses_per_user = N` → N uses per user

## Rollback Plan

If issues arise:
1. Column `max_uses_per_user` with NULL default has no impact on existing logic
2. New table can be dropped without affecting core functionality
3. Validation checks can be disabled by removing the per-user check block
