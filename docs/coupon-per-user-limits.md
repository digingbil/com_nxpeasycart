# Per-User Coupon Usage Limits

This document describes the per-user coupon usage limit feature introduced in version 0.2.1.

## Overview

Store administrators can now limit how many times a single user can use a specific coupon. This prevents coupon abuse where a single customer uses the same promotional code multiple times.

## How It Works

### Setting Up a Per-User Limit

1. Go to **Components > NXP Easy Cart > Coupons**
2. Create a new coupon or edit an existing one
3. Set the **"Max uses per user"** field to your desired limit (e.g., `1` for single use per customer)
4. Save the coupon

### Behavior

| Coupon Configuration | Guest Checkout | Logged-in User |
|---------------------|----------------|----------------|
| No per-user limit set | Allowed | Allowed |
| Per-user limit set | **Blocked** - must log in | Allowed (tracked by user ID) |

When a coupon has a per-user limit:

- **Guest users** will see: *"Please log in to use this coupon."*
- **Logged-in users** can use the coupon up to the specified limit
- Once the limit is reached, users see: *"You have already used this coupon the maximum number of times."*

### Why Login is Required

Per-user limits require user authentication to prevent abuse. Without login:
- A guest could use different email addresses to bypass the limit
- There's no reliable way to identify a guest across sessions

By requiring login, usage is tracked by the user's account ID, making it impossible to bypass.

## Database Schema

### Modified Table: `#__nxp_easycart_coupons`

New column added:

| Column | Type | Description |
|--------|------|-------------|
| `max_uses_per_user` | INT UNSIGNED NULL | Maximum times a single user can use this coupon. NULL = unlimited. |

### New Table: `#__nxp_easycart_coupon_usage`

Tracks individual coupon usage per user/order:

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `coupon_id` | INT UNSIGNED | Reference to coupon |
| `user_id` | INT UNSIGNED NULL | Joomla user ID (for logged-in users) |
| `guest_email` | VARCHAR(255) NULL | Email address (for guest fallback, currently not used due to login requirement) |
| `order_id` | INT UNSIGNED | Reference to order |
| `created` | DATETIME | Timestamp when coupon was used |

## Validation Flow

1. **Cart Stage** (when applying coupon):
   - Check if coupon has `max_uses_per_user` set
   - If yes and user is guest → reject with "Please log in"
   - If yes and user is logged in → check usage count against limit

2. **Checkout Stage** (when submitting order):
   - Re-validate coupon with user info
   - Ensures limit wasn't exceeded between cart and checkout

3. **Post-Order** (after successful payment):
   - Record usage in `#__nxp_easycart_coupon_usage` table
   - Increment global `times_used` counter

## Examples

### Single-Use Welcome Coupon

Create a coupon that each customer can only use once:

- **Code:** `WELCOME10`
- **Type:** Percent
- **Value:** 10
- **Max uses per user:** 1
- **Maximum uses:** (leave empty for unlimited total uses)

### Limited Promotion

Create a coupon with both global and per-user limits:

- **Code:** `FLASH50`
- **Type:** Fixed amount
- **Value:** 50
- **Max uses per user:** 2 (each customer can use twice)
- **Maximum uses:** 100 (total 100 redemptions across all customers)

## Migration

For existing installations, run the migration file:

```
administrator/components/com_nxpeasycart/sql/updates/mysql/0.2.1.sql
```

This adds:
- The `max_uses_per_user` column to the coupons table
- The `coupon_usage` tracking table

## Language Strings

| Key | Default Value |
|-----|---------------|
| `COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER` | Max uses per user |
| `COM_NXPEASYCART_COUPONS_FORM_MAX_USES_PER_USER_HINT` | Limit how many times a single user or email can use this coupon. Leave empty for unlimited. |
| `COM_NXPEASYCART_ERROR_COUPON_LOGIN_REQUIRED` | Please log in to use this coupon. |
| `COM_NXPEASYCART_ERROR_COUPON_USER_LIMIT_REACHED` | You have already used this coupon the maximum number of times. |

## Related Files

- `administrator/components/com_nxpeasycart/src/Service/CouponService.php` - Core validation logic
- `components/com_nxpeasycart/src/Controller/CartController.php` - Cart coupon application
- `components/com_nxpeasycart/src/Controller/PaymentController.php` - Checkout validation & usage recording
- `media/com_nxpeasycart/src/app/components/CouponsPanel.vue` - Admin UI form field
