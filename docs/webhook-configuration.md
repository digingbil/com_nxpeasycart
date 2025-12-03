# Webhook Configuration Guide

This guide covers how to properly configure payment gateway webhooks for NXP Easy Cart to ensure reliable payment processing.

## Overview

Webhooks are HTTP callbacks that payment gateways (Stripe, PayPal) send to your site when payment events occur. Without properly configured webhooks:

- Orders may remain stuck in "pending" state
- Payment confirmations won't be recorded
- Customers won't receive order confirmation emails

## Stripe Webhooks

### Setup Steps

1. Log in to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Developers → Webhooks**
3. Click **Add endpoint**
4. Configure the endpoint:
   - **Endpoint URL**: `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.stripe`
   - **Description**: NXP Easy Cart payments (optional)
5. Select events to listen to:
   - `checkout.session.completed` **(required)**
   - `checkout.session.expired` (recommended)
   - `payment_intent.payment_failed` (recommended)
6. Click **Add endpoint**
7. Copy the **Signing secret** (starts with `whsec_`)
8. In Joomla admin, go to **Components → NXP Easy Cart → Settings → Payments → Stripe**
9. Paste the signing secret into the **Webhook Secret** field
10. Save

### Stripe Events Explained

| Event | Purpose |
|-------|---------|
| `checkout.session.completed` | Primary event - marks order as paid when customer completes payment |
| `checkout.session.expired` | Cancels order if customer abandons checkout session |
| `payment_intent.payment_failed` | Records failed payment attempts for troubleshooting |

### Testing Stripe Webhooks

1. In Stripe Dashboard, go to your webhook endpoint
2. Click **Send test webhook**
3. Select `checkout.session.completed`
4. Check NXP Easy Cart logs for successful processing

### Stripe Retry Behavior

Stripe automatically retries failed webhooks:

- Up to **3 retries** over 72 hours
- Exponential backoff between retries
- Endpoint disabled after consistent failures (>1 week)

NXP Easy Cart returns appropriate HTTP status codes:

| Response | Meaning | Stripe Action |
|----------|---------|---------------|
| `200 OK` | Webhook processed | Marked as delivered |
| `400 Bad Request` | Invalid signature/payload | Retries with backoff |
| `404 Not Found` | Order not found | Retries (order may be processing) |
| `500 Server Error` | Processing failed | Retries with backoff |

---

## PayPal Webhooks

### Setup Steps

1. Log in to [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/)
2. Select your app (or create one under **Apps & Credentials**)
3. Scroll to **Webhooks** section
4. Click **Add Webhook**
5. Configure:
   - **Webhook URL**: `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal`
6. Subscribe to events:
   - `CHECKOUT.ORDER.APPROVED` **(required)** - triggers payment capture
   - `PAYMENT.CAPTURE.COMPLETED` **(required)** - confirms payment success
   - `PAYMENT.CAPTURE.DENIED` (recommended)
   - `PAYMENT.CAPTURE.REFUNDED` (recommended)
7. Click **Save**
8. Copy the **Webhook ID** (a UUID, NOT the URL)
9. In Joomla admin, go to **Components → NXP Easy Cart → Settings → Payments → PayPal**
10. Paste the Webhook ID into the **Webhook ID** field
11. Save

### PayPal Events Explained

| Event | Purpose |
|-------|---------|
| `CHECKOUT.ORDER.APPROVED` | Customer approved payment - NXP Easy Cart auto-captures |
| `PAYMENT.CAPTURE.COMPLETED` | Payment successfully captured - order marked as paid |
| `PAYMENT.CAPTURE.DENIED` | Payment was denied - order remains pending |
| `PAYMENT.CAPTURE.REFUNDED` | Payment was refunded via PayPal dashboard |

### PayPal Auto-Capture Flow

Unlike Stripe, PayPal requires explicit capture after customer approval:

```
1. Customer clicks "Pay with PayPal" → Redirected to PayPal
2. Customer approves payment → Redirected back to your site
3. PayPal sends CHECKOUT.ORDER.APPROVED webhook
4. NXP Easy Cart automatically calls PayPal's capture API
5. PayPal sends PAYMENT.CAPTURE.COMPLETED webhook
6. Order transitions to "paid" state
```

### PayPal Sandbox Notes

When testing with PayPal Sandbox:

- Sandbox may return `PENDING` capture status even for successful payments
- This is normal sandbox behavior - production works correctly
- NXP Easy Cart handles this gracefully

### PayPal Retry Behavior

PayPal retries webhooks automatically:

- Immediate retry on failure
- Up to **3 days** of retry attempts
- Exponential backoff between retries

---

## Common Troubleshooting

### Orders Stuck in "Pending"

**Symptoms**: Customer completes payment, but order stays "pending"

**Causes & Solutions**:

1. **Webhook URL unreachable**
   - Verify your site is accessible from the internet
   - Check firewall rules allow incoming POST requests
   - Ensure SSL certificate is valid

2. **Webhook secret/ID not configured**
   - Stripe: Check `whsec_` secret is in settings
   - PayPal: Check Webhook ID (UUID) is in settings

3. **Wrong webhook URL**
   - Must include full path with `index.php`
   - Case-sensitive: `com_nxpeasycart` not `com_NxpEasyCart`

4. **Webhook events not selected**
   - Stripe: Must have `checkout.session.completed`
   - PayPal: Must have both `CHECKOUT.ORDER.APPROVED` and `PAYMENT.CAPTURE.COMPLETED`

### "Invalid Signature" Errors

**Stripe**:
- Regenerate webhook secret in Stripe dashboard
- Copy the new `whsec_` value to NXP Easy Cart settings
- Ensure no trailing/leading whitespace

**PayPal**:
- Verify you copied the Webhook ID (not the URL)
- Ensure credentials match environment (Live vs Sandbox)

### Duplicate Transactions

NXP Easy Cart prevents duplicate processing via:

1. **Idempotency keys** - Each webhook event ID is tracked
2. **External ID matching** - Transaction IDs are deduplicated

If you see duplicates, check:
- Multiple webhook endpoints configured for the same events
- Webhook being sent to both production and staging

---

## Monitoring Webhook Health

### Stripe Dashboard

1. Go to **Developers → Webhooks → Your endpoint**
2. Check **Recent webhook attempts**
3. Look for failed deliveries (4xx/5xx responses)

### PayPal Dashboard

1. Go to **App → Webhooks → Your webhook**
2. View **Event history**
3. Check for failed deliveries

### Database Audit Trail

Query recent payment events:

```sql
SELECT
    action,
    entity_id AS order_id,
    payload,
    created
FROM #__nxp_easycart_audit
WHERE action LIKE 'order.payment%'
   OR action LIKE 'webhook%'
ORDER BY created DESC
LIMIT 50;
```

### Find Stuck Orders

```sql
SELECT
    order_no,
    email,
    total_cents,
    payment_method,
    created
FROM #__nxp_easycart_orders
WHERE state = 'pending'
  AND created < DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created DESC;
```

---

## Security Considerations

### HTTPS Required

Both Stripe and PayPal require HTTPS endpoints. HTTP webhooks will:
- Stripe: Be rejected entirely
- PayPal: Work in sandbox, fail in production

### Webhook Signature Verification

NXP Easy Cart **always** verifies webhook signatures:

- **Stripe**: HMAC-SHA256 signature in `Stripe-Signature` header
- **PayPal**: Verification via PayPal's API with Webhook ID

Webhooks with invalid signatures are rejected with `400 Bad Request`.

### Credential Security

- Never expose webhook secrets in client-side code
- Use different credentials for sandbox vs production
- Rotate secrets if compromised

---

## Environment-Specific URLs

| Environment | Stripe Endpoint | PayPal Endpoint |
|-------------|-----------------|-----------------|
| Production | `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.stripe` | `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal` |
| Staging | Use test/sandbox credentials with staging URL | Use sandbox credentials with staging URL |

### Using ngrok for Local Development

For local testing with real webhooks:

```bash
ngrok http 80
```

Then configure webhook URL as:
```
https://abc123.ngrok.io/index.php?option=com_nxpeasycart&task=webhook.stripe
```

Remember to use test/sandbox credentials only!
