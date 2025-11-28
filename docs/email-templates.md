# Email Templates

NXP Easy Cart includes a transactional email system for order lifecycle notifications. All emails are sent through Joomla's configured mailer (SMTP, sendmail, etc.).

---

## Overview

The component supports three email types:

| Email Type         | Template File            | Trigger Point                         | Purpose                     |
| ------------------ | ------------------------ | ------------------------------------- | --------------------------- |
| Order Confirmation | `order_confirmation.php` | After successful payment capture      | Confirm order receipt       |
| Order Shipped      | `order_shipped.php`      | Admin adds tracking or fulfills order | Notify customer of shipment |
| Order Refunded     | `order_refunded.php`     | Order transitions to 'refunded'       | Confirm refund processing   |

All templates are located in:

```
administrator/components/com_nxpeasycart/templates/email/
```

---

## Email Templates

### 1. Order Confirmation (`order_confirmation.php`)

**Trigger:** Called automatically after successful payment via `PaymentController::checkout()`.

**Data available:**

- `$order` - Full order array (order_no, total_cents, currency, billing, shipping, etc.)
- `$order['items']` - Line items with product details
- `$statusUrl` - Tokenised order status page URL

**Key features:**

- Order number and date
- Line item breakdown with prices
- Billing and shipping addresses
- Order total with currency formatting
- Link to track order status

---

### 2. Order Shipped (`order_shipped.php`)

**Trigger:** Must be called when:

1. Admin adds tracking information to an order
2. Order state transitions from 'paid' to 'fulfilled'

**Usage in code:**

```php
$mailService = $this->container->get(MailService::class);
$mailService->sendOrderShipped($order, [
    'carrier' => 'FedEx',
    'tracking_number' => '1234567890',
    'tracking_url' => 'https://fedex.com/track/1234567890',
]);
```

**Data available:**

- `$order` - Full order array
- `$options['carrier']` - Shipping carrier name (optional)
- `$options['tracking_number']` - Tracking number (optional)
- `$options['tracking_url']` - Direct link to carrier tracking page (optional)
- `$statusUrl` - Tokenised order status page URL

**Key features:**

- Prominent "Your order has shipped!" header
- Tracking information section (when provided)
- Clickable tracking link
- Order items summary
- Shipping address reminder

---

### 3. Order Refunded (`order_refunded.php`)

**Trigger:** Must be called when order transitions to 'refunded' state.

**Usage in code:**

```php
$mailService = $this->container->get(MailService::class);
$mailService->sendOrderRefunded($order, [
    'refund_amount' => 2500, // cents
]);
```

**Data available:**

- `$order` - Full order array
- `$options['refund_amount']` - Refund amount in cents (optional, defaults to order total)
- `$statusUrl` - Tokenised order status page URL

**Key features:**

- Clear refund confirmation message
- Refund amount prominently displayed
- Note about processing time (3-5 business days)
- Support contact information
- Order reference number

---

## MailService API

### `sendOrderConfirmation(array $order): bool`

Sends order confirmation email after checkout.

### `sendOrderShipped(array $order, array $options = []): bool`

Sends shipping notification with optional tracking info.

**Options:**

- `carrier` (string) - Shipping carrier name
- `tracking_number` (string) - Package tracking number
- `tracking_url` (string) - Direct URL to track package

### `sendOrderRefunded(array $order, array $options = []): bool`

Sends refund confirmation email.

**Options:**

- `refund_amount` (int) - Refund amount in cents (defaults to order total)

---

## Automatic Email Triggers

Emails can be sent **automatically** when order states transition, controlled by the "Auto-send order emails" setting in **Settings → General**.

### Auto-Send Setting

| Setting                | Location           | Default | Behavior                                           |
| ---------------------- | ------------------ | ------- | -------------------------------------------------- |
| Auto-send order emails | Settings → General | Off     | When ON, emails sent automatically on state change |

When **enabled**, emails are automatically triggered:

| Transition        | Email Sent            |
| ----------------- | --------------------- |
| Any → `fulfilled` | `sendOrderShipped()`  |
| Any → `refunded`  | `sendOrderRefunded()` |

When **disabled**, no automatic emails are sent - administrators must use the manual send buttons.

### Manual Email Buttons

Regardless of the auto-send setting, administrators can manually send emails from the order details panel:

1. Open an order in the Orders workspace
2. For **fulfilled** orders: Click "Send shipped email" (or "Re-send shipped email" if previously sent)
3. For **refunded** orders: Click "Send refunded email" (or "Re-send refunded email" if previously sent)

The button label changes to "Re-send" when the audit trail shows that email type was already sent for the order.

### Audit Trail

All email sends are logged to the audit trail:

- **Automatic sends**: Logged with action `order.email.sent` and context `{type: 'shipped'|'refunded'}`
- **Manual sends**: Logged with action `order.email.sent` and context `{type: 'shipped'|'refunded', manual: true}`

This allows compliance tracking and prevents accidental duplicate sends (visual indicator only - re-sending is still allowed).

### Manual Sending via Code

You can also send emails manually when needed:

```php
// Send shipped email with tracking info
$mailService = $container->get(MailService::class);
$mailService->sendOrderShipped($order, [
    'carrier' => 'FedEx',
    'tracking_number' => '1234567890',
    'tracking_url' => 'https://fedex.com/track/1234567890',
]);

// Send refund email
$mailService->sendOrderRefunded($order, [
    'amount_cents' => 2500,
]);
```

### Manual Sending via API

The admin UI uses an API endpoint to send emails manually:

```
POST /administrator/index.php?option=com_nxpeasycart&task=api.orders.sendemail
```

**Request body (JSON):**

```json
{
    "order_id": 123,
    "type": "shipped"
}
```

**Parameters:**

| Parameter  | Type    | Required | Description                         |
| ---------- | ------- | -------- | ----------------------------------- |
| `order_id` | integer | Yes      | The order ID                        |
| `type`     | string  | Yes      | Email type: `shipped` or `refunded` |

**Response (success):**

```json
{
    "success": true
}
```

**Response (error):**

```json
{
    "error": "Order not found"
}
```

**Error conditions:**

- Missing `order_id` or `type` parameter
- Order not found
- Invalid email type (not `shipped` or `refunded`)
- Order not in correct state (must be `fulfilled` for shipped, `refunded` for refunded)
- Mail service failure

### Plugin Event Integration

You can also hook into state transitions via plugin events:

```php
// plugins/system/myeasycart/myeasycart.php
public function onNxpEasycartAfterOrderStateChange($event)
{
    $order = $event->getArgument('order');
    $toState = $event->getArgument('toState');

    // Custom notifications, CRM sync, etc.
}
```

---

## Language Strings

Email templates use the following language keys (in `administrator/language/en-GB/com_nxpeasycart.ini`):

```ini
; Order Shipped Email
COM_NXPEASYCART_EMAIL_ORDER_SHIPPED_SUBJECT="Your order %s has shipped!"
COM_NXPEASYCART_EMAIL_ORDER_SHIPPED_HEADING="Good news! Your order is on its way"
COM_NXPEASYCART_EMAIL_ORDER_SHIPPED_INTRO="Your order #%s has been shipped and is on its way to you."
COM_NXPEASYCART_EMAIL_TRACKING_INFO="Tracking Information"
COM_NXPEASYCART_EMAIL_CARRIER="Carrier"
COM_NXPEASYCART_EMAIL_TRACKING_NUMBER="Tracking Number"
COM_NXPEASYCART_EMAIL_TRACK_PACKAGE="Track Your Package"

; Order Refunded Email
COM_NXPEASYCART_EMAIL_ORDER_REFUNDED_SUBJECT="Refund processed for order %s"
COM_NXPEASYCART_EMAIL_ORDER_REFUNDED_HEADING="Your refund has been processed"
COM_NXPEASYCART_EMAIL_ORDER_REFUNDED_INTRO="We have processed a refund for your order #%s."
COM_NXPEASYCART_EMAIL_REFUND_AMOUNT="Refund Amount"
COM_NXPEASYCART_EMAIL_REFUND_NOTE="Please note: Refunds typically take 3-5 business days to appear in your account."
```

---

## Template Customisation

### Structure

Each template receives:

1. `$order` array with all order data
2. `$options` array with email-specific options
3. Access to Joomla's `Text` class for translations
4. Access to `NumberFormatter` for currency formatting

### Styling

Templates use inline CSS for maximum email client compatibility. Key classes:

- `.email-container` - Main wrapper (max-width: 600px)
- `.email-header` - Header section with branding
- `.email-body` - Main content area
- `.order-items` - Line items table
- `.tracking-section` - Tracking info box (shipped emails)
- `.refund-amount` - Prominent refund display

### Best Practices

1. **Always use inline styles** - External CSS is stripped by many email clients
2. **Test in multiple clients** - Gmail, Outlook, Apple Mail have different rendering
3. **Keep images small** - Some clients block images by default
4. **Include plain text fallback** - For clients that don't render HTML
5. **Use tables for layout** - Most reliable cross-client structure

---

## Configuration

### Joomla Mail Settings

Ensure Joomla's mail settings are configured in **System → Global Configuration → Server**:

- Mail From: Your store's email address
- From Name: Your store name
- Mailer: SMTP (recommended for production)
- SMTP Host/Port/Auth: Per your email provider

### Store Settings

The store name used in emails is pulled from NXP Easy Cart's settings (Settings → General → Store Name).

---

## Testing

### Manual Testing

1. Create a test order
2. Complete payment (use sandbox gateway)
3. Verify confirmation email received
4. Transition order to 'fulfilled' with tracking
5. Verify shipped email received
6. Transition order to 'refunded'
7. Verify refunded email received

### Unit Tests

See `tests/Unit/Administrator/Service/MailServiceTest.php` for email service tests.

---

## Troubleshooting

### Emails not sending

1. Check Joomla mail configuration (System → Global Configuration → Server)
2. Verify SMTP credentials if using SMTP
3. Check server error logs for mail errors
4. Test with Joomla's "Send Test Email" feature

### Emails in spam

1. Configure SPF/DKIM/DMARC for your domain
2. Use a reputable SMTP provider (SendGrid, Mailgun, etc.)
3. Avoid spam trigger words in subject lines

### Template not loading

1. Verify template file exists in `templates/email/`
2. Check file permissions (readable by web server)
3. Verify no PHP syntax errors in template

---

**Last Updated**: 2025-01-20
