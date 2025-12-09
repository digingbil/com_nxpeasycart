# Stripe Credentials Setup Guide

This guide walks you through obtaining the required Stripe API credentials for NXP Easy Cart: **Publishable Key**, **Secret Key**, and **Webhook Secret**.

---

## Overview

NXP Easy Cart requires three credentials from Stripe:

| Credential | Purpose | Example Format |
|------------|---------|----------------|
| **Publishable Key** | Client-side payment form initialization | `pk_test_...` or `pk_live_...` |
| **Secret Key** | Server-side API calls (charges, refunds) | `sk_test_...` or `sk_live_...` |
| **Webhook Secret** | Verify webhook signatures for security | `whsec_...` |

---

## Prerequisites

- A Stripe account (free to create)
- Email verification completed
- Business information added (for live mode)

---

## Step 1: Create a Stripe Account

If you don't have a Stripe account:

1. Go to [https://dashboard.stripe.com/register](https://dashboard.stripe.com/register)
2. Enter your email address and create a password
3. Verify your email address by clicking the link Stripe sends you
4. Complete the initial account setup

---

## Step 2: Access the Stripe Dashboard

1. Log in to [https://dashboard.stripe.com](https://dashboard.stripe.com)
2. You'll see the main dashboard with your account overview

### Test Mode vs Live Mode

Stripe provides two environments:

- **Test Mode**: For development and testing (no real charges)
- **Live Mode**: For production (real money transactions)

You can toggle between modes using the switch in the top-right corner of the dashboard:

```
┌─────────────────────────────────────┐
│  [Toggle] Test mode  ←  Look here   │
└─────────────────────────────────────┘
```

**Recommendation**: Start with Test Mode while setting up your store, then switch to Live Mode when ready to accept real payments.

---

## Step 3: Get Your API Keys (Publishable Key & Secret Key)

### Navigate to API Keys

1. In the Stripe Dashboard, click **Developers** in the left sidebar
2. Click **API keys**

Or go directly to: [https://dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)

### Copy Your Keys

You'll see two keys:

```
┌────────────────────────────────────────────────────────────┐
│ Standard keys                                              │
├────────────────────────────────────────────────────────────┤
│ Publishable key                                            │
│ pk_test_51ABC123xyz...                        [Reveal]     │
│                                                            │
│ Secret key                                                 │
│ sk_test_51ABC123xyz...                        [Reveal]     │
└────────────────────────────────────────────────────────────┘
```

1. **Publishable Key**: Click to copy (visible by default)
2. **Secret Key**: Click **Reveal test key** (or **Reveal live key**), then copy

### Key Format Reference

| Environment | Publishable Key | Secret Key |
|-------------|-----------------|------------|
| Test | `pk_test_...` | `sk_test_...` |
| Live | `pk_live_...` | `sk_live_...` |

---

## Step 4: Create a Webhook Endpoint

Webhooks allow Stripe to notify your site when payment events occur (successful payments, refunds, etc.).

### Navigate to Webhooks

1. In the Stripe Dashboard, click **Developers** in the left sidebar
2. Click **Webhooks**

Or go directly to: [https://dashboard.stripe.com/webhooks](https://dashboard.stripe.com/webhooks)

### Add Endpoint

1. Click **Add endpoint**
2. Enter your webhook URL:
   ```
   https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.stripe
   ```
   Replace `yoursite.com` with your actual domain.

3. (Optional) Add a description: `NXP Easy Cart payments`

### Select Events to Listen To

Click **Select events** and choose:

| Event | Required? | Description |
|-------|-----------|-------------|
| `checkout.session.completed` | **Required** | Marks order as paid when customer completes payment |
| `checkout.session.expired` | Recommended | Cancels order if customer abandons checkout |
| `payment_intent.payment_failed` | Recommended | Records failed payment attempts |

**Minimum required**: `checkout.session.completed`

### Save the Endpoint

1. Click **Add endpoint**
2. You'll be taken to the endpoint details page

---

## Step 5: Get Your Webhook Secret

After creating the endpoint:

1. On the endpoint details page, find **Signing secret**
2. Click **Reveal** to show the secret
3. Copy the value (starts with `whsec_`)

```
┌────────────────────────────────────────────────────────────┐
│ Signing secret                                             │
│ whsec_abc123xyz...                            [Reveal]     │
└────────────────────────────────────────────────────────────┘
```

---

## Step 6: Enter Credentials in NXP Easy Cart

1. In your Joomla admin panel, go to **Components → NXP Easy Cart**
2. Click **Settings** in the left menu
3. Click the **Payments** tab
4. Expand the **Stripe** section
5. Enter your credentials:

| Field | Value |
|-------|-------|
| **Publishable Key** | `pk_test_...` (your publishable key) |
| **Secret Key** | `sk_test_...` (your secret key) |
| **Webhook Secret** | `whsec_...` (your webhook signing secret) |
| **Sandbox Mode** | Enable for testing, disable for live payments |

6. Click **Save**

---

## Testing Your Setup

### Test Mode Checkout

1. Create a test product in NXP Easy Cart
2. Go to your storefront and add it to cart
3. Proceed to checkout and select Stripe
4. Use Stripe's test card numbers:

| Card Number | Scenario |
|-------------|----------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 0002` | Card declined |
| `4000 0000 0000 3220` | 3D Secure authentication required |

Use any future expiry date (e.g., `12/34`) and any 3-digit CVC.

### Verify Webhook Delivery

1. After a test payment, go to **Developers → Webhooks → Your endpoint**
2. Check **Recent webhook attempts**
3. Successful deliveries show **200** status
4. Failed deliveries show error codes (4xx/5xx)

### Check NXP Easy Cart Logs

1. Go to **Components → NXP Easy Cart → Logs**
2. Look for `webhook.stripe` events
3. Verify orders transition to "Paid" status

---

## Going Live

When ready to accept real payments:

### 1. Complete Stripe Account Setup

1. Go to **Settings → Business settings** in Stripe Dashboard
2. Complete all required fields:
   - Business information
   - Bank account for payouts
   - Tax information (if required)
   - Identity verification

### 2. Get Live Credentials

1. Toggle to **Live mode** (top-right switch)
2. Go to **Developers → API keys**
3. Copy your live keys (`pk_live_...` and `sk_live_...`)
4. Go to **Developers → Webhooks**
5. Create a new webhook endpoint for your production URL
6. Copy the live webhook secret (`whsec_...`)

### 3. Update NXP Easy Cart

1. Replace test credentials with live credentials
2. **Disable Sandbox Mode** in Settings → Payments → Stripe
3. Save settings

### 4. Test Live Mode

Make a small real purchase to verify everything works:
- Payment processes correctly
- Order status updates to "Paid"
- Customer receives confirmation email
- Payout appears in Stripe Dashboard

---

## Troubleshooting

### "Invalid API Key" Error

**Cause**: Wrong key or environment mismatch

**Solution**:
- Verify you copied the complete key (no missing characters)
- Ensure test keys are used with Sandbox Mode enabled
- Ensure live keys are used with Sandbox Mode disabled

### Webhook Signature Verification Failed

**Cause**: Wrong webhook secret or endpoint URL mismatch

**Solution**:
1. Re-copy the webhook secret from Stripe Dashboard
2. Verify the endpoint URL matches exactly (including `https://`)
3. Check for trailing slashes or extra characters

### Orders Stuck in "Pending"

**Cause**: Webhook not reaching your site

**Solution**:
1. Verify your site is accessible from the internet
2. Check SSL certificate is valid
3. Ensure firewall allows incoming POST requests
4. Verify webhook events are selected (especially `checkout.session.completed`)

### "Webhook Secret Not Configured" Error

**Cause**: Missing webhook secret in settings

**Solution**:
Webhook secret is **mandatory** for security. You must:
1. Create a webhook endpoint in Stripe Dashboard
2. Copy the signing secret
3. Paste it into NXP Easy Cart settings

---

## Security Best Practices

1. **Never expose your Secret Key** in client-side code or public repositories
2. **Use separate keys** for test and live environments
3. **Rotate keys** if you suspect they've been compromised
4. **Restrict API key permissions** in Stripe Dashboard if needed
5. **Monitor webhook logs** for suspicious activity

---

## Quick Reference

### Stripe Dashboard URLs

| Page | URL |
|------|-----|
| Dashboard | [https://dashboard.stripe.com](https://dashboard.stripe.com) |
| API Keys | [https://dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys) |
| Webhooks | [https://dashboard.stripe.com/webhooks](https://dashboard.stripe.com/webhooks) |
| Test Mode | [https://dashboard.stripe.com/test](https://dashboard.stripe.com/test) |

### NXP Easy Cart Webhook URL

```
https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.stripe
```

### Required Webhook Events

- `checkout.session.completed` (required)
- `checkout.session.expired` (recommended)
- `payment_intent.payment_failed` (recommended)

---

## Related Documentation

- [webhook-configuration.md](webhook-configuration.md) - Detailed webhook setup and troubleshooting
- [security-audit-fixes.md](security-audit-fixes.md) - Security requirements for webhook validation
- [paypal-credentials-setup.md](paypal-credentials-setup.md) - PayPal credential setup guide

---

**Last Updated**: 2025-12-09
