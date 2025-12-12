# Abandoned Cart Recovery - Implementation Plan

## Overview

Abandoned cart recovery is an automated email system that reminds customers who added items to their cart but didn't complete checkout. This feature requires **explicit opt-in consent** to comply with GDPR and other privacy regulations.

**Key Principle:** No tracking without consent. Customers must actively check a box to receive reminders.

---

## Business Value

| Metric | Industry Average |
|--------|------------------|
| Cart abandonment rate | 70-80% |
| Recovery rate with email | 5-15% |
| Revenue recovery potential | 3-10% of lost sales |

For a store with 100 abandoned carts/month at €50 average order value:
- Lost revenue: €5,000/month
- With 10% recovery: €500/month additional revenue
- Annual impact: €6,000+

---

## Phase 1: Database Schema

### 1.1 New Tables

```sql
-- Abandoned cart tracking with consent
CREATE TABLE `#__nxp_easycart_abandoned_carts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` CHAR(36) NOT NULL,                    -- UUID from #__nxp_easycart_carts
    `email` VARCHAR(255) NOT NULL,
    `consent_given` TINYINT(1) NOT NULL DEFAULT 0,  -- Must be 1 to send emails
    `consent_timestamp` DATETIME DEFAULT NULL,      -- When consent was given
    `consent_ip` VARCHAR(45) DEFAULT NULL,          -- IP at consent time (for audit)
    `cart_snapshot` JSON DEFAULT NULL,              -- Cart contents at abandonment
    `cart_total_cents` INT UNSIGNED DEFAULT 0,
    `currency` CHAR(3) DEFAULT 'USD',
    `reminder_count` TINYINT UNSIGNED DEFAULT 0,    -- How many reminders sent
    `last_reminder_at` DATETIME DEFAULT NULL,
    `recovered` TINYINT(1) DEFAULT 0,               -- Did they complete purchase?
    `recovered_order_id` INT UNSIGNED DEFAULT NULL, -- Link to completed order
    `unsubscribed` TINYINT(1) DEFAULT 0,            -- Opted out of reminders
    `unsubscribe_token` CHAR(64) DEFAULT NULL,      -- For one-click unsubscribe
    `created` DATETIME NOT NULL,
    `modified` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_cart_id` (`cart_id`),
    KEY `idx_email` (`email`),
    KEY `idx_consent` (`consent_given`),
    KEY `idx_recovery_candidates` (`consent_given`, `recovered`, `unsubscribed`, `reminder_count`),
    KEY `idx_unsubscribe_token` (`unsubscribe_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email send log for analytics and debugging
CREATE TABLE `#__nxp_easycart_abandoned_cart_emails` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `abandoned_cart_id` INT UNSIGNED NOT NULL,
    `email_type` VARCHAR(50) NOT NULL,              -- 'reminder_1', 'reminder_2', etc.
    `sent_at` DATETIME NOT NULL,
    `opened_at` DATETIME DEFAULT NULL,              -- If tracking pixels enabled
    `clicked_at` DATETIME DEFAULT NULL,             -- If link tracking enabled
    `error` TEXT DEFAULT NULL,                      -- Any send errors
    PRIMARY KEY (`id`),
    KEY `idx_abandoned_cart` (`abandoned_cart_id`),
    KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Settings Additions

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `abandoned_cart_enabled` | boolean | false | Master enable/disable |
| `abandoned_cart_delay_minutes` | int | 60 | Wait time before considering cart abandoned |
| `abandoned_cart_reminder_count` | int | 1 | Max reminders to send (1-3) |
| `abandoned_cart_reminder_intervals` | JSON | [60, 1440, 4320] | Minutes between reminders |
| `abandoned_cart_email_subject` | string | "You left something behind!" | Email subject |
| `abandoned_cart_include_discount` | boolean | false | Include a coupon in reminder |
| `abandoned_cart_discount_code` | string | "" | Coupon code to include |
| `abandoned_cart_expiry_days` | int | 30 | Days to keep abandoned cart records |

---

## Phase 2: Consent Collection

### 2.1 Checkout Flow Integration

The consent checkbox appears in the checkout form when the customer enters their email:

```html
<!-- Checkout email section -->
<div class="nxp-ec-checkout__email-section">
    <label for="checkout-email">Email address</label>
    <input type="email" id="checkout-email" name="email" required>

    <!-- Consent checkbox - only shown if abandoned cart feature is enabled -->
    <div class="nxp-ec-checkout__consent" v-if="abandonedCartEnabled">
        <label class="nxp-ec-checkbox">
            <input
                type="checkbox"
                id="cart-reminder-consent"
                v-model="cartReminderConsent"
                @change="recordConsent"
            >
            <span class="nxp-ec-checkbox__label">
                {{ translations.CART_REMINDER_CONSENT }}
            </span>
        </label>
        <p class="nxp-ec-checkout__consent-info">
            {{ translations.CART_REMINDER_CONSENT_DESC }}
        </p>
    </div>
</div>
```

**Language Strings:**
```ini
COM_NXPEASYCART_CART_REMINDER_CONSENT="Email me a reminder if I don't complete my purchase"
COM_NXPEASYCART_CART_REMINDER_CONSENT_DESC="We'll send you one friendly reminder with your cart contents. You can unsubscribe anytime."
```

### 2.2 Consent Recording Endpoint

**Endpoint:** `POST /index.php?option=com_nxpeasycart&task=cart.recordConsent`

**Payload:**
```json
{
    "email": "customer@example.com",
    "consent": true
}
```

**Controller Logic:**
```php
public function recordConsent(): void
{
    $this->checkToken();

    $email = $this->input->json->getString('email', '');
    $consent = $this->input->json->getBool('consent', false);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Invalid email address');
    }

    $cartId = $this->cartSession->getCartId();

    $this->abandonedCartService->recordConsent(
        $cartId,
        $email,
        $consent,
        $this->input->server->getString('REMOTE_ADDR', '')
    );

    $this->sendJsonResponse(['success' => true]);
}
```

### 2.3 Consent Data Structure

When consent is given, we record:

```php
[
    'cart_id' => 'uuid-of-current-cart',
    'email' => 'customer@example.com',
    'consent_given' => 1,
    'consent_timestamp' => '2025-01-15 14:32:00',
    'consent_ip' => '192.168.1.100',
    'unsubscribe_token' => bin2hex(random_bytes(32)),
    'created' => '2025-01-15 14:32:00',
    'modified' => '2025-01-15 14:32:00',
]
```

---

## Phase 3: Backend Services

### 3.1 AbandonedCartService

**Location:** `administrator/components/com_nxpeasycart/src/Service/AbandonedCartService.php`

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Service;

class AbandonedCartService
{
    private DatabaseInterface $db;
    private SettingsService $settings;
    private CartPresentationService $cartPresenter;

    /**
     * Record customer consent for cart reminders.
     */
    public function recordConsent(
        string $cartId,
        string $email,
        bool $consent,
        string $ipAddress
    ): void;

    /**
     * Snapshot cart contents when customer leaves checkout.
     * Called via JavaScript beforeunload or on timeout.
     */
    public function snapshotCart(string $cartId): void;

    /**
     * Find carts eligible for reminder emails.
     *
     * Criteria:
     * - consent_given = 1
     * - recovered = 0
     * - unsubscribed = 0
     * - reminder_count < max_reminders
     * - last activity > delay threshold
     * - last_reminder_at respects interval
     */
    public function findRecoveryCandidates(int $limit = 50): array;

    /**
     * Send reminder email for an abandoned cart.
     */
    public function sendReminder(int $abandonedCartId): bool;

    /**
     * Mark cart as recovered when order is placed.
     */
    public function markRecovered(string $email, int $orderId): void;

    /**
     * Process unsubscribe request.
     */
    public function unsubscribe(string $token): bool;

    /**
     * Clean up old abandoned cart records.
     */
    public function cleanup(int $olderThanDays = 30): int;

    /**
     * Get recovery statistics for dashboard.
     */
    public function getStats(string $period = '30d'): array;
}
```

### 3.2 Recovery Candidate Query

```sql
SELECT ac.*, c.data as current_cart_data
FROM #__nxp_easycart_abandoned_carts ac
LEFT JOIN #__nxp_easycart_carts c ON c.id = ac.cart_id
WHERE ac.consent_given = 1
  AND ac.recovered = 0
  AND ac.unsubscribed = 0
  AND ac.reminder_count < :max_reminders
  AND ac.modified < DATE_SUB(NOW(), INTERVAL :delay_minutes MINUTE)
  AND (
      ac.last_reminder_at IS NULL
      OR ac.last_reminder_at < DATE_SUB(NOW(), INTERVAL :interval_minutes MINUTE)
  )
  AND (
      c.data IS NOT NULL
      AND JSON_LENGTH(JSON_EXTRACT(c.data, '$.items')) > 0
  )
ORDER BY ac.modified ASC
LIMIT :limit
```

### 3.3 Cart Snapshot Logic

When to snapshot the cart:

1. **On email blur** (checkout page) - Customer entered email, snapshot current cart
2. **On page unload** (checkout page) - Customer leaving without completing
3. **On consent change** - When toggling the reminder checkbox
4. **Periodic sync** - Every 30 seconds while on checkout page

```javascript
// checkout.js
const snapshotCart = async () => {
    if (!cartReminderConsent.value || !email.value) return;

    await api.post('cart.snapshot', {
        email: email.value,
        cart: cart.value
    });
};

// Debounced snapshot on changes
watch([cart, email], debounce(snapshotCart, 5000));

// Snapshot on page leave
onBeforeUnmount(() => {
    if (cartReminderConsent.value && email.value) {
        navigator.sendBeacon(
            buildUrl('cart.snapshot'),
            JSON.stringify({ email: email.value, cart: cart.value })
        );
    }
});
```

---

## Phase 4: Scheduled Task

### 4.1 Joomla 5 Task Plugin

**Location:** `plugins/task/nxpeasycartabandoned/nxpeasycartabandoned.php`

```php
<?php
namespace Joomla\Plugin\Task\Nxpeasycartabandoned\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;

class Nxpeasycartabandoned extends CMSPlugin
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'nxpeasycart.abandoned_cart_reminders' => [
            'langConstPrefix' => 'PLG_TASK_NXPEASYCARTABANDONED',
            'method' => 'processAbandonedCarts',
            'form' => 'abandoned_cart_form',
        ],
    ];

    private function processAbandonedCarts(ExecuteTaskEvent $event): int
    {
        $service = $this->getAbandonedCartService();

        if (!$service->isEnabled()) {
            return Status::OK;
        }

        $candidates = $service->findRecoveryCandidates(50);
        $sent = 0;
        $failed = 0;

        foreach ($candidates as $cart) {
            try {
                if ($service->sendReminder($cart['id'])) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logError($cart['id'], $e->getMessage());
            }
        }

        // Also run cleanup
        $cleaned = $service->cleanup();

        $this->snapshot['sent'] = $sent;
        $this->snapshot['failed'] = $failed;
        $this->snapshot['cleaned'] = $cleaned;

        return Status::OK;
    }
}
```

### 4.2 Recommended Schedule

| Task | Frequency | Purpose |
|------|-----------|---------|
| Process reminders | Every 15 minutes | Send pending reminder emails |
| Cleanup old records | Daily at 3 AM | Remove expired abandoned cart data |

---

## Phase 5: Email Templates

### 5.1 Reminder Email Template

**Location:** `administrator/components/com_nxpeasycart/templates/email/abandoned_cart_reminder.php`

```php
<?php
/**
 * Abandoned Cart Reminder Email Template
 *
 * Available variables:
 * - $cart: array with items, totals
 * - $customer: array with email, name (if available)
 * - $store: array with name, url, logo
 * - $recoveryUrl: string - URL to restore cart
 * - $unsubscribeUrl: string - One-click unsubscribe
 * - $discount: array|null - Coupon details if enabled
 * - $reminderNumber: int - Which reminder this is (1, 2, 3)
 */

use Joomla\CMS\Language\Text;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;

$formatMoney = fn(int $cents) => MoneyHelper::format($cents, $cart['currency'] ?? 'USD');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_TITLE'); ?></title>
    <style>
        /* Email-safe CSS */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            border-radius: 8px;
            padding: 32px;
            margin-bottom: 16px;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1 {
            color: #111827;
            font-size: 24px;
            margin: 0 0 8px;
        }
        .header p {
            color: #6b7280;
            margin: 0;
        }
        .cart-items {
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 0;
            margin: 16px 0;
        }
        .cart-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
        }
        .cart-item img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 16px;
        }
        .cart-item-details {
            flex: 1;
        }
        .cart-item-title {
            font-weight: 600;
            color: #111827;
        }
        .cart-item-meta {
            font-size: 14px;
            color: #6b7280;
        }
        .cart-item-price {
            font-weight: 600;
            color: #111827;
        }
        .totals {
            text-align: right;
            padding: 16px 0;
        }
        .totals-row {
            display: flex;
            justify-content: flex-end;
            padding: 4px 0;
        }
        .totals-label {
            color: #6b7280;
            margin-right: 16px;
        }
        .totals-value {
            font-weight: 600;
            min-width: 80px;
        }
        .totals-total {
            font-size: 18px;
            border-top: 2px solid #e5e7eb;
            padding-top: 8px;
            margin-top: 8px;
        }
        .cta-button {
            display: block;
            width: 100%;
            padding: 16px 32px;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            border-radius: 8px;
            margin: 24px 0;
        }
        .cta-button:hover {
            background-color: #1d4ed8;
        }
        .discount-banner {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            margin: 16px 0;
        }
        .discount-code {
            font-size: 24px;
            font-weight: bold;
            color: #b45309;
            letter-spacing: 2px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            padding: 16px;
        }
        .footer a {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <?php if (!empty($store['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($store['logo']); ?>" alt="<?php echo htmlspecialchars($store['name']); ?>" style="max-height: 48px; margin-bottom: 16px;">
                <?php endif; ?>

                <h1><?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_HEADING'); ?></h1>
                <p><?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_SUBHEADING'); ?></p>
            </div>

            <?php if (!empty($discount)): ?>
            <div class="discount-banner">
                <p><?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_DISCOUNT_INTRO'); ?></p>
                <div class="discount-code"><?php echo htmlspecialchars($discount['code']); ?></div>
                <p><?php echo Text::sprintf('COM_NXPEASYCART_EMAIL_ABANDONED_CART_DISCOUNT_VALUE', $discount['label']); ?></p>
            </div>
            <?php endif; ?>

            <div class="cart-items">
                <?php foreach ($cart['items'] as $item): ?>
                <div class="cart-item">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="">
                    <?php endif; ?>
                    <div class="cart-item-details">
                        <div class="cart-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="cart-item-meta">
                            <?php echo Text::sprintf('COM_NXPEASYCART_EMAIL_ABANDONED_CART_QTY', (int)$item['qty']); ?>
                            <?php if (!empty($item['sku'])): ?>
                                &middot; <?php echo htmlspecialchars($item['sku']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="cart-item-price">
                        <?php echo $formatMoney((int)$item['total_cents']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="totals">
                <div class="totals-row">
                    <span class="totals-label"><?php echo Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'); ?></span>
                    <span class="totals-value"><?php echo $formatMoney((int)$cart['subtotal_cents']); ?></span>
                </div>
                <div class="totals-row totals-total">
                    <span class="totals-label"><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
                    <span class="totals-value"><?php echo $formatMoney((int)$cart['total_cents']); ?></span>
                </div>
            </div>

            <a href="<?php echo htmlspecialchars($recoveryUrl); ?>" class="cta-button">
                <?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_CTA'); ?>
            </a>

            <p style="text-align: center; color: #6b7280; font-size: 14px;">
                <?php echo Text::_('COM_NXPEASYCART_EMAIL_ABANDONED_CART_EXPIRY_NOTE'); ?>
            </p>
        </div>

        <div class="footer">
            <p>
                <?php echo Text::sprintf('COM_NXPEASYCART_EMAIL_ABANDONED_CART_FOOTER', htmlspecialchars($store['name'])); ?>
            </p>
            <p>
                <a href="<?php echo htmlspecialchars($unsubscribeUrl); ?>">
                    <?php echo Text::_('COM_NXPEASYCART_EMAIL_UNSUBSCRIBE'); ?>
                </a>
            </p>
        </div>
    </div>
</body>
</html>
```

### 5.2 Language Strings

```ini
; Abandoned Cart Emails
COM_NXPEASYCART_EMAIL_ABANDONED_CART_TITLE="Complete Your Purchase"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_HEADING="You left something behind!"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_SUBHEADING="Your cart is waiting for you"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_QTY="Qty: %d"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_CTA="Complete My Order"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_EXPIRY_NOTE="Your cart items are reserved but may sell out."
COM_NXPEASYCART_EMAIL_ABANDONED_CART_FOOTER="You're receiving this email because you opted in to cart reminders at %s."
COM_NXPEASYCART_EMAIL_ABANDONED_CART_DISCOUNT_INTRO="Here's a special offer just for you:"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_DISCOUNT_VALUE="Use code for %s off your order"
COM_NXPEASYCART_EMAIL_UNSUBSCRIBE="Unsubscribe from cart reminders"

; Subject lines (can be customized in settings)
COM_NXPEASYCART_EMAIL_ABANDONED_CART_SUBJECT_1="You left something behind!"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_SUBJECT_2="Your cart misses you"
COM_NXPEASYCART_EMAIL_ABANDONED_CART_SUBJECT_3="Last chance: Complete your order"
```

---

## Phase 6: Cart Recovery Flow

### 6.1 Recovery URL Structure

```
https://example.com/shop/cart/recover/{token}
```

The token is a secure, one-time-use identifier that:
1. Identifies the abandoned cart record
2. Restores the cart contents to the user's session
3. Redirects to checkout with items pre-loaded
4. Marks the recovery attempt (for analytics)

### 6.2 Recovery Controller

**Location:** `components/com_nxpeasycart/src/Controller/CartController.php`

```php
/**
 * Recover an abandoned cart from email link.
 *
 * Route: /shop/cart/recover/{token}
 */
public function recover(): void
{
    $token = $this->input->getString('token', '');

    if (strlen($token) !== 64) {
        $this->setRedirect(Route::_('index.php?option=com_nxpeasycart&view=cart'));
        return;
    }

    $result = $this->abandonedCartService->recoverCart($token);

    if (!$result['success']) {
        $this->app->enqueueMessage(
            Text::_('COM_NXPEASYCART_CART_RECOVERY_EXPIRED'),
            'warning'
        );
        $this->setRedirect(Route::_('index.php?option=com_nxpeasycart&view=cart'));
        return;
    }

    // Cart restored to session, redirect to checkout
    $this->app->enqueueMessage(
        Text::_('COM_NXPEASYCART_CART_RECOVERY_SUCCESS'),
        'success'
    );

    $this->setRedirect(Route::_('index.php?option=com_nxpeasycart&view=checkout'));
}
```

### 6.3 Recovery Service Logic

```php
public function recoverCart(string $token): array
{
    $abandoned = $this->findByUnsubscribeToken($token);

    if (!$abandoned || $abandoned['recovered']) {
        return ['success' => false, 'reason' => 'invalid_or_used'];
    }

    // Check if cart snapshot is still valid
    $snapshot = json_decode($abandoned['cart_snapshot'], true);

    if (empty($snapshot['items'])) {
        return ['success' => false, 'reason' => 'empty_cart'];
    }

    // Validate items are still available
    $validItems = $this->validateCartItems($snapshot['items']);

    if (empty($validItems)) {
        return ['success' => false, 'reason' => 'items_unavailable'];
    }

    // Restore to current session
    $this->cartSession->restoreFromSnapshot($validItems);

    // Track recovery attempt
    $this->recordRecoveryAttempt($abandoned['id']);

    return [
        'success' => true,
        'items_restored' => count($validItems),
        'items_unavailable' => count($snapshot['items']) - count($validItems),
    ];
}
```

---

## Phase 7: Unsubscribe Flow

### 7.1 One-Click Unsubscribe

**URL:** `https://example.com/shop/cart/unsubscribe/{token}`

```php
/**
 * Process unsubscribe request from email.
 */
public function unsubscribe(): void
{
    $token = $this->input->getString('token', '');

    $result = $this->abandonedCartService->unsubscribe($token);

    if ($result) {
        $message = Text::_('COM_NXPEASYCART_UNSUBSCRIBE_SUCCESS');
        $type = 'success';
    } else {
        $message = Text::_('COM_NXPEASYCART_UNSUBSCRIBE_INVALID');
        $type = 'warning';
    }

    // Show simple confirmation page
    $this->app->enqueueMessage($message, $type);
    $this->setRedirect(Route::_('index.php?option=com_nxpeasycart&view=unsubscribed'));
}
```

### 7.2 List-Unsubscribe Header

Include in all abandoned cart emails for one-click unsubscribe in email clients:

```php
$headers = [
    'List-Unsubscribe' => '<' . $unsubscribeUrl . '>',
    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
];
```

---

## Phase 8: Admin UI

### 8.1 Dashboard Widget

Add to admin dashboard:

```
┌─────────────────────────────────────────────────────────┐
│  Abandoned Cart Recovery                    Last 30 days │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Abandoned Carts     Reminders Sent     Recovered       │
│       127                 98               12           │
│                                        (12.2%)          │
│                                                         │
│  Potential Revenue    Recovered Revenue                 │
│     €6,350.00            €842.50                        │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 8.2 Settings Panel Tab

Add "Cart Recovery" tab to Settings panel:

```
┌─────────────────────────────────────────────────────────┐
│ [General] [Security] [Payments] [Cart Recovery] [Visual]│
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ☑ Enable abandoned cart recovery                       │
│                                                         │
│  Timing                                                 │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Consider cart abandoned after: [60] minutes       │ │
│  │ Maximum reminders to send: [1 ▾]                  │ │
│  │ Time between reminders: [24] hours                │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  Email Content                                          │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Subject line: [You left something behind!      ]  │ │
│  │                                                   │ │
│  │ ☐ Include discount code in reminder              │ │
│  │   Coupon code: [COMEBACK10  ]                    │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  Data Retention                                         │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Delete abandoned cart data after: [30] days      │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│                              [Save Settings]            │
└─────────────────────────────────────────────────────────┘
```

### 8.3 Abandoned Carts List View (Optional)

Dedicated panel to view/manage abandoned carts:

| Email | Cart Value | Consented | Reminders | Status | Actions |
|-------|------------|-----------|-----------|--------|---------|
| john@... | €125.00 | Yes | 1/1 | Pending | [View] [Send Reminder] |
| jane@... | €89.50 | Yes | 0/1 | New | [View] [Send Reminder] |
| bob@... | €234.00 | Yes | 1/1 | Recovered | [View Order] |

---

## Phase 9: Analytics & Reporting

### 9.1 Metrics to Track

| Metric | Description |
|--------|-------------|
| `abandoned_carts_total` | Total carts abandoned (with consent) |
| `reminders_sent` | Total reminder emails sent |
| `reminders_opened` | Emails opened (if tracking enabled) |
| `reminders_clicked` | Recovery links clicked |
| `carts_recovered` | Completed purchases after reminder |
| `revenue_recovered` | Sum of recovered order values |
| `recovery_rate` | recovered / abandoned * 100 |
| `unsubscribe_rate` | unsubscribed / reminders_sent * 100 |

### 9.2 Analytics Endpoint

```php
public function getStats(string $period = '30d'): array
{
    $days = (int) str_replace('d', '', $period);
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    return [
        'period' => $period,
        'abandoned_carts' => $this->countAbandoned($since),
        'reminders_sent' => $this->countRemindersSent($since),
        'carts_recovered' => $this->countRecovered($since),
        'recovery_rate' => $this->calculateRecoveryRate($since),
        'revenue_abandoned' => $this->sumAbandonedValue($since),
        'revenue_recovered' => $this->sumRecoveredValue($since),
        'unsubscribes' => $this->countUnsubscribes($since),
    ];
}
```

---

## Phase 10: GDPR Compliance Checklist

### 10.1 Requirements Met

| Requirement | Implementation |
|-------------|----------------|
| **Lawful basis** | Explicit consent via checkbox |
| **Purpose limitation** | Only used for cart reminders |
| **Data minimization** | Only email + cart snapshot stored |
| **Consent record** | Timestamp + IP stored |
| **Right to withdraw** | One-click unsubscribe |
| **Right to erasure** | Automatic cleanup after expiry |
| **Right to access** | Covered by existing GDPR export |
| **Transparency** | Clear explanation at consent point |

### 10.2 Privacy Policy Addition

Add to store's privacy policy:

> **Cart Reminders**
>
> If you opt in to cart reminders at checkout, we will store your email address and cart contents temporarily. If you don't complete your purchase, we may send you up to [X] reminder email(s) within [Y] days. This data is automatically deleted after [Z] days. You can unsubscribe from reminders at any time using the link in the email.

---

## Implementation Order

| Phase | Scope | Effort | Dependencies |
|-------|-------|--------|--------------|
| **Phase 1** | Database schema | ~1 hour | None |
| **Phase 2** | Consent collection | ~2 hours | Phase 1 |
| **Phase 3** | Backend services | ~3-4 hours | Phase 1, 2 |
| **Phase 4** | Scheduled task | ~2 hours | Phase 3 |
| **Phase 5** | Email templates | ~2 hours | Phase 3 |
| **Phase 6** | Cart recovery flow | ~2 hours | Phase 3, 5 |
| **Phase 7** | Unsubscribe flow | ~1 hour | Phase 3 |
| **Phase 8** | Admin UI | ~2-3 hours | Phase 3 |
| **Phase 9** | Analytics | ~1-2 hours | Phase 3 |
| **Phase 10** | GDPR audit | ~1 hour | All |

**Total estimate: ~17-20 hours**

---

## Testing Checklist

### Functional Tests
- [ ] Consent checkbox appears only when feature enabled
- [ ] Consent is recorded with timestamp and IP
- [ ] Cart snapshot captures current items
- [ ] Scheduled task finds eligible carts correctly
- [ ] Reminder emails send with correct content
- [ ] Recovery URL restores cart to session
- [ ] Unsubscribe link works and prevents future emails
- [ ] Recovered carts are marked and linked to orders
- [ ] Cleanup removes old records

### Edge Cases
- [ ] Customer with multiple abandoned carts (different sessions)
- [ ] Cart items no longer available at recovery time
- [ ] Customer completes purchase before reminder sent
- [ ] Invalid/expired recovery tokens
- [ ] Email delivery failures
- [ ] Concurrent recovery attempts

### GDPR Compliance
- [ ] No emails sent without consent
- [ ] Consent record includes timestamp
- [ ] Unsubscribe is immediate and permanent
- [ ] Data deleted after retention period
- [ ] GDPR export includes abandoned cart data

---

## Future Enhancements

1. **A/B Testing** - Test different subject lines, send times, discount offers
2. **SMS Reminders** - Alternative channel (requires separate consent)
3. **Exit-Intent Popup** - Capture consent before customer leaves site
4. **Personalized Recommendations** - Include related products in email
5. **Multi-language** - Send emails in customer's preferred language
6. **Segmentation** - Different strategies for high-value vs. low-value carts
