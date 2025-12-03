# Manual Transaction Recording

This document describes the manual transaction recording feature for offline payment methods (Cash on Delivery and Bank Transfer).

## Overview

Online payment gateways (Stripe, PayPal) automatically record transactions via webhooks. However, offline payment methods require manual confirmation when payment is received:

- **Cash on Delivery (COD)**: Payment collected when order is delivered
- **Bank Transfer**: Payment received via bank transfer after order placement

The manual transaction recording feature allows administrators to record these payments, automatically transitioning orders from `pending` to `paid` state.

## User Interface

### When It Appears

The "Record Payment" section appears in the order detail drawer when:

1. Order state is `pending`
2. Payment method is `cod` or `bank_transfer`

Once a payment is recorded, the section disappears (order is no longer `pending`).

### Form Fields

| Field | Required | Description |
|-------|----------|-------------|
| **Amount** | No | Payment amount received. Defaults to order total if left empty. |
| **Reference** | No | Receipt number, bank reference, or other identifier for tracking. |
| **Note** | No | Internal note for audit purposes (not shown to customer). |

### Workflow

1. Open an order with `pending` state and `cod`/`bank_transfer` payment method
2. The "Record Payment" section appears below the email controls
3. Optionally adjust amount, add reference, or note
4. Click "Record Payment"
5. Order transitions to `paid` state
6. Transaction appears in the "Payments" section
7. Timeline shows the payment event

## API Endpoint

### Record Transaction

```
POST /administrator/index.php?option=com_nxpeasycart&task=api.orders.recordTransaction
```

**Headers:**
- `Content-Type: application/json`
- `X-CSRF-Token: <token>` (or form token)

**Request Body:**
```json
{
    "id": 123,
    "amount_cents": 5000,
    "reference": "RECEIPT-001",
    "note": "Cash collected by driver John"
}
```

**Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | Order ID |
| `amount_cents` | integer | No | Amount in cents. Defaults to order total. |
| `reference` | string | No | External reference (receipt #, bank ref). Max 128 chars. |
| `note` | string | No | Internal note. |

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "order": {
            "id": 123,
            "order_no": "EC-ABC123",
            "state": "paid",
            "transactions": [...],
            "timeline": [...]
        }
    }
}
```

**Error Responses:**

| HTTP Code | Condition |
|-----------|-----------|
| 400 | Invalid order ID |
| 400 | Payment method not `cod` or `bank_transfer` |
| 404 | Order not found |
| 403 | Missing permissions or CSRF token |
| 500 | Transaction recording failed |

## Backend Flow

### OrdersController::recordTransaction()

Located at: `administrator/components/com_nxpeasycart/src/Controller/Api/OrdersController.php`

1. Validates order exists
2. Checks payment method is `cod` or `bank_transfer`
3. Builds transaction payload:
   ```php
   $transaction = [
       'gateway'      => $paymentMethod,  // 'cod' or 'bank_transfer'
       'external_id'  => $reference,       // Optional reference
       'status'       => 'paid',
       'amount_cents' => $amountCents,
       'currency'     => $order['currency'],
       'payload'      => [
           'note'        => $note,
           'recorded_by' => $actorId,
           'manual'      => true,
       ],
   ];
   ```
4. Calls `OrderService::recordTransaction()`
5. Records audit entry with `order.payment.manual` action

### OrderService::recordTransaction()

This existing method handles both automatic (webhook) and manual transactions:

1. Validates order exists
2. Checks for duplicate transactions (idempotency)
3. Inserts transaction record into `#__nxp_easycart_transactions`
4. If status is `paid`, automatically transitions order to `paid` state
5. Records `order.payment.recorded` audit entry

## Database Schema

### Transactions Table

```sql
CREATE TABLE `#__nxp_easycart_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `gateway` VARCHAR(64) NOT NULL,        -- 'cod', 'bank_transfer', 'stripe', 'paypal'
    `ext_id` VARCHAR(128) NULL,            -- Reference (nullable for manual)
    `status` VARCHAR(32) NOT NULL,         -- 'paid', 'pending', 'failed'
    `amount_cents` INT NOT NULL,
    `payload` JSON NULL,                   -- { note, recorded_by, manual: true }
    `event_idempotency_key` VARCHAR(128) NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);
```

### Audit Trail

Manual payments create two audit entries:

1. `order.payment.recorded` - Standard payment recording (via OrderService)
2. `order.payment.manual` - Manual payment specific (via OrdersController)

The `order.payment.manual` entry includes:
```json
{
    "gateway": "cod",
    "amount_cents": 5000,
    "reference": "RECEIPT-001"
}
```

## Security Considerations

### Access Control

- Requires `core.edit` permission on `com_nxpeasycart`
- CSRF token validation (form token or X-CSRF-Token header)
- Only authenticated admin users can record transactions

### Payment Method Validation

The endpoint strictly validates that the order's payment method is one of:
- `cod`
- `bank_transfer`

Attempts to record manual transactions for Stripe/PayPal orders are rejected with HTTP 400.

### Amount Validation

- Amount must be a positive integer (cents)
- If not provided, defaults to order total
- No upper bound validation (allows recording partial or adjusted amounts)

## Translation Keys

The following language strings are used:

```ini
COM_NXPEASYCART_ORDERS_RECORD_PAYMENT="Record Payment"
COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_HELP="Manually record payment receipt for this order. This will mark the order as paid."
COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT="Amount"
COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT_HELP="Leave empty to use order total"
COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE="Reference (optional)"
COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE_PLACEHOLDER="Receipt number, bank reference..."
COM_NXPEASYCART_ORDERS_PAYMENT_NOTE="Note (optional)"
COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_SUBMIT="Record Payment"
COM_NXPEASYCART_ERROR_MANUAL_PAYMENT_NOT_ALLOWED="Manual payment recording is only available for Cash on Delivery or Bank Transfer orders."
COM_NXPEASYCART_ERROR_RECORD_PAYMENT_FAILED="Failed to record payment: %s"
```

## Testing

### Manual Testing Steps

1. Create a test order with COD or Bank Transfer payment method
2. Verify order is in `pending` state
3. Open order in admin panel
4. Verify "Record Payment" section appears
5. Test with default amount (leave empty)
6. Test with custom amount
7. Test with reference and note
8. Verify order transitions to `paid`
9. Verify transaction appears in Payments list
10. Verify timeline shows payment event

### Edge Cases

- Order already in `paid` state: Section should not appear
- Non-offline payment method: Section should not appear
- Empty amount: Should use order total
- Very large amount: Should be accepted (no upper limit)
- Special characters in reference/note: Should be properly escaped

## Future Enhancements

Potential improvements for future versions:

1. **Partial payments**: Track multiple payments against a single order
2. **Payment date**: Allow recording historical payment date (not just current timestamp)
3. **Receipt upload**: Attach scanned receipt or proof of payment
4. **Refund recording**: Manual refund recording for offline methods
5. **Email notification**: Option to send payment confirmation email to customer
