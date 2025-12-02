# PayPal Webhook Flow

This document describes the PayPal payment integration, webhook handling, and the auto-capture mechanism implemented in NXP Easy Cart.

## Overview

NXP Easy Cart uses PayPal's **Orders v2 API** with `intent: CAPTURE`. The flow is:

1. Customer initiates checkout → system creates PayPal order
2. Customer approves payment on PayPal → redirected back to order confirmation page
3. PayPal sends `CHECKOUT.ORDER.APPROVED` webhook → system auto-captures payment
4. System records transaction and transitions order to "paid"

## Configuration

### Required Settings

In the admin panel under **Settings → Payments → PayPal**:

| Field | Description |
|-------|-------------|
| `client_id` | PayPal REST API Client ID |
| `client_secret` | PayPal REST API Client Secret |
| `webhook_id` | **Required** - The webhook ID from PayPal Developer Dashboard |
| `sandbox` | Enable for testing with sandbox credentials |

### Webhook Setup

1. Go to [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/applications)
2. Select your application
3. Navigate to **Webhooks**
4. Add webhook URL: `https://yoursite.com/index.php?option=com_nxpeasycart&task=webhook.paypal`
5. Subscribe to these events:
   - `CHECKOUT.ORDER.APPROVED`
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED` (optional, for failure handling)
6. Copy the **Webhook ID** and enter it in NXP Easy Cart settings

## Payment Flow Details

### Step 1: Checkout Initiation

When customer clicks "Pay with PayPal", `PaymentController::checkout()`:

1. Creates order in database with `state: pending` and `payment_method: paypal`
2. Calls `PayPalGateway::createHostedCheckout()` to create PayPal order
3. Redirects customer to PayPal approval URL

```php
$body = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => $order['order_no'],
        'custom_id' => $order['id'],  // Used to link back to our order
        'amount' => [
            'currency_code' => 'USD',
            'value' => '299.99',
        ],
    ]],
    'application_context' => [
        'return_url' => '/shop/order?ref=TOKEN&status=success',
        'cancel_url' => '/shop/checkout?status=cancelled',
    ],
];
```

### Step 2: Customer Approval

Customer approves payment on PayPal and is redirected to:
```
/shop/order?ref=PUBLIC_TOKEN&status=success&token=PAYPAL_ORDER_ID&PayerID=PAYER_ID
```

At this point, the order is still `pending` - payment has been approved but not captured.

### Step 3: Webhook Processing

PayPal sends `CHECKOUT.ORDER.APPROVED` webhook to your endpoint. The handler:

1. **Verifies signature** using PayPal's verification API (mandatory)
2. **Extracts order ID** from `custom_id` field
3. **Auto-captures payment** by calling PayPal's capture endpoint
4. **Records transaction** with status `paid`
5. **Transitions order** from `pending` to `paid`

```php
// In PayPalGateway::buildTransactionPayload()
if ($eventType === 'CHECKOUT.ORDER.APPROVED' && !empty($resource['id'])) {
    // Auto-capture the order
    $capture = $this->captureOrder($resource['id'], $accessToken);
    // ... extract capture details and set status to 'paid'
}
```

### Step 4: Order Status Update

`OrderService::recordTransaction()` receives the transaction with `status: paid`:

```php
$shouldMarkPaid = strtolower($transaction['status']) === 'paid';

if ($shouldMarkPaid && $order['state'] !== 'paid') {
    $this->transitionState($orderId, 'paid');
}
```

## PayPal Sandbox Quirks

### PENDING Capture Status

PayPal sandbox frequently returns `PENDING` capture status with `status_details.reason: OTHER` even for successful payments. This is a known sandbox behavior.

**How we handle it:**

The webhook handler detects successful captures by checking if `external_id` changed from the PayPal order ID to a capture ID:

```php
if ($paypalStatus === 'COMPLETED') {
    $status = 'paid';
} elseif ($eventType === 'CHECKOUT.ORDER.APPROVED'
    && $externalId !== null
    && $externalId !== $resource['id']) {
    // Capture succeeded (external_id is now capture ID, not order ID)
    $status = 'paid';
} else {
    $status = strtolower($resource['status'] ?? 'pending');
}
```

### Webhook Timing

Webhooks typically arrive 10-60 seconds after customer approval. During this window:

- Customer sees order page with "Pending" status
- A prominent notice instructs them to refresh in ~1 minute
- Once webhook processes, refresh shows "Paid" status

## Database Schema

### Orders Table

```sql
ALTER TABLE `#__nxp_easycart_orders`
  ADD COLUMN `payment_method` VARCHAR(32) NULL AFTER `state`;
```

Values: `stripe`, `paypal`, `cod`, `bank_transfer`, or `NULL` for legacy orders.

### Transactions Table

Each successful payment creates a transaction record:

```sql
INSERT INTO `#__nxp_easycart_transactions` (
    order_id,
    gateway,
    ext_id,           -- PayPal capture ID (e.g., '25D44510H4711422H')
    status,           -- 'paid'
    amount_cents,
    payload,          -- Full PayPal response JSON
    event_idempotency_key  -- Webhook event ID for deduplication
) VALUES (...);
```

## Idempotency

Webhook handlers are idempotent:

1. **By external_id**: If a transaction with the same `gateway` + `ext_id` exists, skip
2. **By idempotency key**: If a transaction with the same `event_idempotency_key` exists, skip

This prevents duplicate charges if PayPal retries webhooks.

## User Experience

### Order Confirmation Page

When customer lands on the order page after PayPal approval:

1. If order is already `paid` → show success state
2. If order is `pending` AND `payment_method` is `paypal`:
   - Show prominent amber notice: "Your PayPal payment is being processed. Please refresh this page in about a minute to see the updated status."
3. Customer refreshes after webhook processes → sees "Paid" status

### CSS for Notice

```scss
.nxp-ec-order-confirmation__notice--info {
    background: #fef3c7;
    border: 2px solid #f59e0b;
    color: #92400e;
    font-weight: 600;
    padding: 1rem 1.25rem;

    &::before {
        content: "⚠";
        font-size: 1.25rem;
    }
}
```

## Troubleshooting

### Webhook Signature Verification Failed

**Symptoms:** Logs show `PayPal webhook signature verification failed`

**Causes:**
- Wrong `webhook_id` in settings
- Webhook URL mismatch between PayPal dashboard and actual endpoint
- Clock skew between servers

**Solution:**
1. Verify `webhook_id` matches PayPal dashboard exactly
2. Ensure webhook URL in PayPal matches your site URL
3. Check server time is synchronized

### Order Stays Pending

**Symptoms:** Customer completed payment but order stays in "Pending" status

**Check:**
1. Review logs for webhook errors
2. Query transactions table for the order
3. Verify PayPal shows webhook delivery as "Success"

**Common causes:**
- Webhook not configured in PayPal
- Webhook URL unreachable (firewall, SSL issues)
- Signature verification failing

### Duplicate Transactions

**Symptoms:** Multiple transactions for same order

**This shouldn't happen** due to idempotency checks, but if it does:
1. Check `event_idempotency_key` values - they should be unique
2. Verify `ext_id` (capture ID) is being extracted correctly

## Migration

### Upgrading to v0.1.8

Run the migration SQL:

```sql
ALTER TABLE `#__nxp_easycart_orders`
  ADD COLUMN `payment_method` VARCHAR(32) NULL AFTER `state`,
  ADD INDEX `idx_nxp_orders_payment_method` (`payment_method`);
```

Or reinstall the component to run all migrations automatically.

**Note:** Existing orders will have `payment_method = NULL`. The pending notice only shows for orders where `payment_method = 'paypal'`, so legacy orders won't display it.
