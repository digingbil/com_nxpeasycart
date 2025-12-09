# PayPal Credentials Setup Guide

This guide walks you through obtaining the required PayPal REST API credentials for NXP Easy Cart: **Client ID**, **Client Secret**, and **Webhook ID**.

---

## Overview

NXP Easy Cart requires three credentials from PayPal:

| Credential | Purpose | Example Format |
|------------|---------|----------------|
| **Client ID** | Identifies your application to PayPal | `AaBbCc123...` (long alphanumeric string) |
| **Client Secret** | Authenticates API requests | `EeFfGg456...` (long alphanumeric string) |
| **Webhook ID** | Verifies webhook signatures for security | `WH-ABC123-XYZ789` (UUID format) |

---

## Prerequisites

- A PayPal Business account (free to create)
- Email verification completed
- Access to PayPal Developer Dashboard

---

## Step 1: Create a PayPal Business Account

If you don't have a PayPal Business account:

1. Go to [https://www.paypal.com/business](https://www.paypal.com/business)
2. Click **Sign Up** or **Get Started**
3. Choose **Business Account**
4. Follow the registration process:
   - Enter your email address
   - Create a password
   - Provide business information
   - Verify your email address

**Note**: Personal PayPal accounts cannot access the Developer Dashboard. You need a Business account.

---

## Step 2: Access the PayPal Developer Dashboard

1. Go to [https://developer.paypal.com](https://developer.paypal.com)
2. Click **Log in to Dashboard** (top-right)
3. Log in with your PayPal Business account credentials

You'll see the Developer Dashboard with your applications and tools.

### Sandbox vs Live Environment

PayPal provides two environments:

- **Sandbox**: For development and testing (no real transactions)
- **Live**: For production (real money transactions)

You'll create credentials for each environment separately.

---

## Step 3: Create a REST API Application

### Navigate to Apps & Credentials

1. In the Developer Dashboard, click **Apps & Credentials** in the top menu
2. You'll see tabs for **Sandbox** and **Live**

Or go directly to: [https://developer.paypal.com/dashboard/applications/sandbox](https://developer.paypal.com/dashboard/applications/sandbox)

### Create a New App (Sandbox First)

1. Ensure you're on the **Sandbox** tab
2. Click **Create App**
3. Fill in the form:
   - **App Name**: `NXP Easy Cart` (or your store name)
   - **App Type**: Select **Merchant**
   - **Sandbox Business Account**: Select your default sandbox business account

4. Click **Create App**

```
┌────────────────────────────────────────────────────────────┐
│ Create New App                                             │
├────────────────────────────────────────────────────────────┤
│ App Name: [NXP Easy Cart                    ]              │
│                                                            │
│ App Type: ○ Platform  ● Merchant                           │
│                                                            │
│ Sandbox Business Account:                                  │
│ [sb-merchant@business.example.com           ▼]             │
│                                                            │
│                               [Create App]                 │
└────────────────────────────────────────────────────────────┘
```

---

## Step 4: Get Your Client ID and Client Secret

After creating the app, you'll be taken to the app details page.

### Locate Your Credentials

Scroll down to the **API Credentials** section:

```
┌────────────────────────────────────────────────────────────┐
│ Sandbox API Credentials                                    │
├────────────────────────────────────────────────────────────┤
│ Client ID                                                  │
│ AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPp...          [Copy]       │
│                                                            │
│ Secret                                                     │
│ ************************************          [Show]       │
└────────────────────────────────────────────────────────────┘
```

### Copy Your Credentials

1. **Client ID**: Click **Copy** to copy the full ID
2. **Client Secret**: Click **Show** to reveal, then copy

**Important**: Keep your Client Secret confidential. Never share it or commit it to public repositories.

---

## Step 5: Create a Webhook

Webhooks allow PayPal to notify your site when payment events occur.

### Navigate to Webhooks

On the app details page:

1. Scroll down to the **Webhooks** section
2. Click **Add Webhook**

### Configure the Webhook

1. **Webhook URL**: Enter your site's webhook endpoint:
   ```
   https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal
   ```
   Replace `yoursite.com` with your actual domain.

2. **Event Types**: Click to select events, then choose:

| Event | Required? | Description |
|-------|-----------|-------------|
| `CHECKOUT.ORDER.APPROVED` | **Required** | Customer approved payment - triggers auto-capture |
| `PAYMENT.CAPTURE.COMPLETED` | **Required** | Payment successfully captured - order marked as paid |
| `PAYMENT.CAPTURE.DENIED` | Recommended | Payment was denied |
| `PAYMENT.CAPTURE.REFUNDED` | Recommended | Payment was refunded |

**Minimum required**: `CHECKOUT.ORDER.APPROVED` and `PAYMENT.CAPTURE.COMPLETED`

3. Click **Save**

```
┌────────────────────────────────────────────────────────────┐
│ Add Webhook                                                │
├────────────────────────────────────────────────────────────┤
│ Webhook URL:                                               │
│ [https://yoursite.com/index.php?option=com_nxpeasycart&ta] │
│                                                            │
│ Events:                                                    │
│ ☑ CHECKOUT.ORDER.APPROVED                                  │
│ ☑ PAYMENT.CAPTURE.COMPLETED                                │
│ ☑ PAYMENT.CAPTURE.DENIED                                   │
│ ☑ PAYMENT.CAPTURE.REFUNDED                                 │
│                                                            │
│                               [Save]                       │
└────────────────────────────────────────────────────────────┘
```

---

## Step 6: Get Your Webhook ID

After saving the webhook:

1. The webhook appears in the **Webhooks** list
2. Click on the webhook URL to view details
3. Copy the **Webhook ID**

The Webhook ID is a UUID that looks like:
```
WH-ABC12345XY678901Z-ABCDEFGH12345678
```

```
┌────────────────────────────────────────────────────────────┐
│ Webhook Details                                            │
├────────────────────────────────────────────────────────────┤
│ Webhook ID: WH-ABC12345XY678901Z-ABCDEFGH12345678  [Copy]  │
│                                                            │
│ URL: https://yoursite.com/index.php?option=com_nxpeasycart │
│      &task=webhook.paypal                                  │
│                                                            │
│ Events:                                                    │
│ • CHECKOUT.ORDER.APPROVED                                  │
│ • PAYMENT.CAPTURE.COMPLETED                                │
│ • PAYMENT.CAPTURE.DENIED                                   │
│ • PAYMENT.CAPTURE.REFUNDED                                 │
└────────────────────────────────────────────────────────────┘
```

**Important**: Copy the Webhook ID, NOT the webhook URL. The ID is required for signature verification.

---

## Step 7: Enter Credentials in NXP Easy Cart

1. In your Joomla admin panel, go to **Components → NXP Easy Cart**
2. Click **Settings** in the left menu
3. Click the **Payments** tab
4. Expand the **PayPal** section
5. Enter your credentials:

| Field | Value |
|-------|-------|
| **Client ID** | Your PayPal app Client ID |
| **Client Secret** | Your PayPal app Client Secret |
| **Webhook ID** | The Webhook ID from your webhook (NOT the URL) |
| **Sandbox Mode** | Enable for testing, disable for live payments |

6. Click **Save**

---

## Step 8: Create Sandbox Test Accounts (For Testing)

PayPal Sandbox requires test buyer and seller accounts.

### Access Sandbox Accounts

1. In Developer Dashboard, go to **Testing Tools → Sandbox Accounts**
2. Or visit: [https://developer.paypal.com/dashboard/accounts](https://developer.paypal.com/dashboard/accounts)

### Default Accounts

PayPal creates default test accounts:
- **Business** account (receives payments)
- **Personal** account (makes test purchases)

### View Account Credentials

1. Click the **...** (three dots) next to a Personal account
2. Click **View/Edit Account**
3. Note the **Email ID** and **System Generated Password**

You'll use the Personal account credentials to log in during test checkouts.

---

## Testing Your Setup

### Test Mode Checkout

1. Create a test product in NXP Easy Cart
2. Go to your storefront and add it to cart
3. Proceed to checkout and select PayPal
4. You'll be redirected to PayPal Sandbox login
5. Log in with your **Sandbox Personal account** credentials
6. Approve the payment
7. You'll be redirected back to your site

### Understanding the Payment Flow

```
1. Customer clicks "Pay with PayPal"
   ↓
2. Redirected to PayPal for approval
   ↓
3. Customer approves payment
   ↓
4. Redirected back to your site (order shows "Pending")
   ↓
5. PayPal sends CHECKOUT.ORDER.APPROVED webhook
   ↓
6. NXP Easy Cart auto-captures payment
   ↓
7. PayPal sends PAYMENT.CAPTURE.COMPLETED webhook
   ↓
8. Order transitions to "Paid"
```

### PayPal Sandbox Quirk

The Sandbox may show a brief "Pending" status even after successful payment. This is normal - the webhook typically processes within 10-60 seconds. Customers can refresh the order page to see the updated status.

### Verify Webhook Delivery

1. In Developer Dashboard, go to your app's webhook section
2. Click **Webhook Events** or **Event history**
3. Verify events show **Success** status

### Check NXP Easy Cart Logs

1. Go to **Components → NXP Easy Cart → Logs**
2. Look for `webhook.paypal` events
3. Verify orders transition to "Paid" status

---

## Going Live

When ready to accept real payments:

### 1. Create Live App Credentials

1. In Developer Dashboard, go to **Apps & Credentials**
2. Switch to the **Live** tab
3. Click **Create App**
4. Complete the same process as for Sandbox
5. Copy your **Live Client ID** and **Client Secret**

### 2. Create Live Webhook

1. On your Live app page, scroll to **Webhooks**
2. Click **Add Webhook**
3. Enter your production webhook URL
4. Select the same events as Sandbox
5. Copy the **Live Webhook ID**

### 3. Update NXP Easy Cart

1. Replace Sandbox credentials with Live credentials:
   - Live Client ID
   - Live Client Secret
   - Live Webhook ID
2. **Disable Sandbox Mode** in Settings → Payments → PayPal
3. Save settings

### 4. Test Live Mode

Make a small real purchase to verify:
- Payment processes correctly
- Order status updates to "Paid"
- Customer receives confirmation email
- Payment appears in PayPal Business account

---

## Troubleshooting

### "Invalid Client ID or Secret" Error

**Cause**: Credentials don't match the environment

**Solution**:
- Sandbox Mode enabled → Use Sandbox credentials
- Sandbox Mode disabled → Use Live credentials
- Verify credentials are copied completely (no extra spaces)

### "Webhook ID Not Configured" Error

**Cause**: Missing Webhook ID in settings

**Solution**:
Webhook ID is **mandatory** for security. You must:
1. Create a webhook in Developer Dashboard
2. Copy the Webhook ID (NOT the URL)
3. Paste it into NXP Easy Cart settings

### Webhook Signature Verification Failed

**Cause**: Wrong Webhook ID or URL mismatch

**Solution**:
1. Verify the Webhook ID matches exactly
2. Ensure webhook URL in PayPal matches your site URL
3. Check for environment mismatch (Sandbox ID with Live mode)

### Orders Stuck in "Pending"

**Cause**: Webhook not reaching your site or not being processed

**Solution**:
1. Verify your site is accessible from the internet (HTTPS required)
2. Check SSL certificate is valid
3. Verify both required events are subscribed:
   - `CHECKOUT.ORDER.APPROVED`
   - `PAYMENT.CAPTURE.COMPLETED`
4. Check PayPal webhook event history for delivery status
5. Review NXP Easy Cart logs for errors

### "PENDING" Status in Sandbox

**This is expected behavior**. PayPal Sandbox often returns `PENDING` capture status even for successful payments. NXP Easy Cart handles this gracefully:
- The webhook processes successfully
- Order transitions to "Paid" once capture completes
- Customer can refresh the page to see updated status

---

## Security Best Practices

1. **Never expose your Client Secret** in client-side code or public repositories
2. **Use separate apps** for Sandbox and Live environments
3. **Rotate credentials** if you suspect they've been compromised
4. **Monitor webhook events** for suspicious activity
5. **Verify Webhook ID is configured** - webhooks without verification are rejected

---

## Quick Reference

### PayPal Developer Dashboard URLs

| Page | URL |
|------|-----|
| Developer Portal | [https://developer.paypal.com](https://developer.paypal.com) |
| Dashboard | [https://developer.paypal.com/dashboard](https://developer.paypal.com/dashboard) |
| Sandbox Apps | [https://developer.paypal.com/dashboard/applications/sandbox](https://developer.paypal.com/dashboard/applications/sandbox) |
| Live Apps | [https://developer.paypal.com/dashboard/applications/live](https://developer.paypal.com/dashboard/applications/live) |
| Sandbox Accounts | [https://developer.paypal.com/dashboard/accounts](https://developer.paypal.com/dashboard/accounts) |

### NXP Easy Cart Webhook URL

```
https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal
```

### Required Webhook Events

- `CHECKOUT.ORDER.APPROVED` (required - triggers payment capture)
- `PAYMENT.CAPTURE.COMPLETED` (required - marks order as paid)
- `PAYMENT.CAPTURE.DENIED` (recommended)
- `PAYMENT.CAPTURE.REFUNDED` (recommended)

### PayPal Payment Flow

```
Customer approves → CHECKOUT.ORDER.APPROVED → Auto-capture → PAYMENT.CAPTURE.COMPLETED → Order paid
```

---

## Summary: Credentials Checklist

Before your PayPal integration works, verify you have:

- [ ] **PayPal Business Account** created and verified
- [ ] **REST API App** created in Developer Dashboard
- [ ] **Client ID** copied from app credentials
- [ ] **Client Secret** copied from app credentials
- [ ] **Webhook** created with correct URL
- [ ] **Webhook ID** copied (NOT the webhook URL)
- [ ] **Events subscribed**: `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`
- [ ] All credentials entered in NXP Easy Cart → Settings → Payments → PayPal
- [ ] **Sandbox Mode** toggle matches your credential environment

---

## Related Documentation

- [webhook-configuration.md](webhook-configuration.md) - Detailed webhook setup and troubleshooting
- [paypal-webhook-flow.md](paypal-webhook-flow.md) - Technical details of PayPal payment flow
- [security-audit-fixes.md](security-audit-fixes.md) - Security requirements for webhook validation
- [stripe-credentials-setup.md](stripe-credentials-setup.md) - Stripe credential setup guide

---

**Last Updated**: 2025-12-09
