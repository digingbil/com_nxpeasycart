# Order State Machine & Security (v0.1.9)

This document covers the order state machine guards, webhook amount validation, review flag system, and stale order cleanup introduced in v0.1.9.

## Order State Machine

Orders follow a strict state machine with validated transitions. Invalid transitions are rejected with user-friendly error messages.

### Valid States

| State | Description |
|-------|-------------|
| `cart` | Initial state, order is being built |
| `pending` | Order submitted, awaiting payment |
| `paid` | Payment confirmed via webhook |
| `fulfilled` | Order shipped/completed |
| `refunded` | Payment refunded |
| `canceled` | Order canceled (terminal) |

### Transition Rules

```
cart      → pending, canceled
pending   → paid, canceled
paid      → fulfilled, refunded, canceled
fulfilled → refunded
refunded  → (terminal - no transitions allowed)
canceled  → (terminal - no transitions allowed)
```

### Implementation

The state machine is enforced in `OrderService.php`:

```php
private const VALID_TRANSITIONS = [
    'cart'      => ['pending', 'canceled'],
    'pending'   => ['paid', 'canceled'],
    'paid'      => ['fulfilled', 'refunded', 'canceled'],
    'fulfilled' => ['refunded'],
    'refunded'  => [],  // terminal state
    'canceled'  => [],  // terminal state
];

public static function isValidTransition(string $from, string $to): bool
{
    $allowed = self::VALID_TRANSITIONS[$from] ?? [];
    return in_array($to, $allowed, true);
}
```

### Error Handling

Invalid transitions return HTTP 400 with a descriptive message:

```json
{
  "success": false,
  "message": "Invalid state transition: paid to pending is not allowed.",
  "data": {"error": true}
}
```

The admin UI displays this message in an error alert above the orders table.

## Webhook Amount Variance Detection

### Purpose

Protects against price manipulation attacks where an attacker might:
- Modify cart data to show lower prices
- Complete payment for a smaller amount
- Expect the order to be fulfilled at the manipulated price

### Implementation

After processing a payment webhook, `PaymentGatewayManager::checkAmountVariance()` compares:
- **Expected amount**: Order total from database (`total_cents`)
- **Received amount**: Payment amount from webhook

```php
private const AMOUNT_VARIANCE_TOLERANCE_CENTS = 1;

private function checkAmountVariance(array $order, int $webhookAmountCents, string $gateway): void
{
    $orderTotalCents = (int) ($order['total_cents'] ?? 0);
    $variance = abs($webhookAmountCents - $orderTotalCents);

    if ($variance > self::AMOUNT_VARIANCE_TOLERANCE_CENTS) {
        $this->orders->flagForReview(
            (int) $order['id'],
            'payment_amount_mismatch',
            [
                'expected_cents' => $orderTotalCents,
                'received_cents' => $webhookAmountCents,
                'variance_cents' => $variance,
                'gateway' => $gateway,
            ]
        );
    }
}
```

### Tolerance

A 1-cent tolerance accommodates rounding differences between systems. Variances exceeding this trigger a review flag.

## Order Review Flag System

### Database Schema

```sql
ALTER TABLE `#__nxp_easycart_orders`
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `review_reason` VARCHAR(255) NULL,
  ADD INDEX `idx_nxp_orders_needs_review` (`needs_review`);
```

### Flagging Orders

```php
public function flagForReview(int $orderId, string $reason, array $metadata = []): void
{
    $reviewReason = $reason;
    if (!empty($metadata)) {
        $reviewReason .= ':' . json_encode($metadata);
    }

    $db = $this->db;
    $query = $db->getQuery(true)
        ->update($db->quoteName('#__nxp_easycart_orders'))
        ->set($db->quoteName('needs_review') . ' = 1')
        ->set($db->quoteName('review_reason') . ' = :reason')
        ->where($db->quoteName('id') . ' = :id')
        ->bind(':reason', $reviewReason)
        ->bind(':id', $orderId, ParameterType::INTEGER);

    $db->setQuery($query)->execute();
}
```

### Review Reasons

| Reason | Description |
|--------|-------------|
| `payment_amount_mismatch` | Webhook amount differs from order total |

### Admin UI

- **List view**: Orders with `needs_review=true` display a "Review" badge
- **Detail view**: Warning alert shows the review reason with parsed metadata
- **Clearing**: Call `clearReviewFlag($orderId)` after manual review

## Stale Order Cleanup Task

### Purpose

Automatically cancels abandoned orders that remain in `pending` state beyond a configurable threshold, releasing reserved stock.

### Installation

The task plugin is located at `plugins/task/nxpeasycartcleanup/`. Install via:
1. Symlink to Joomla plugins directory
2. Use **System → Discover** to detect and install
3. Enable and configure schedule in **System → Scheduled Tasks**

### Configuration

Settings are managed in **Components → NXP Easy Cart → Settings → General**:

| Setting | Description | Default |
|---------|-------------|---------|
| Enable stale order cleanup | Master toggle | Off |
| Hours threshold | Orders older than this are canceled | 48 |

Valid range: 1-720 hours (1 hour to 30 days)

### Task Logic

```php
protected function cleanupStaleOrders(): int
{
    $enabled = ConfigHelper::isStaleOrderCleanupEnabled();
    if (!$enabled) {
        return TaskStatus::OK;
    }

    $hours = ConfigHelper::getStaleOrderHours();
    $canceled = $this->orderService->cancelStaleOrders($hours);

    return TaskStatus::OK;
}
```

### Stock Release

When canceling stale orders, reserved stock is automatically released:

```php
private function releaseStockForOrder(array $order): void
{
    $items = $order['items'] ?? [];
    foreach ($items as $item) {
        $variantId = (int) ($item['variant_id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($variantId > 0 && $qty > 0) {
            // Increment stock_qty for the variant
        }
    }
}
```

### Scheduling Options

- **Lazy scheduler**: Triggers on page visits (requires `plg_system_schedulerunner` enabled)
- **CLI cron**: `php cli/joomla.php scheduler:run --id=<task_id>`
- **Webcron**: External HTTP trigger with authentication key

**Note**: Joomla's lazy scheduler may be blocked if other tasks have stale locks. Monitor the `j5_scheduler_tasks` table for `locked` values from previous failed runs.

## Files Modified (v0.1.9)

### Backend

| File | Changes |
|------|---------|
| `OrderService.php` | State machine, `flagForReview()`, `cancelStaleOrders()`, `releaseStockForOrder()` |
| `PaymentGatewayManager.php` | `checkAmountVariance()` method |
| `ConfigHelper.php` | Stale order settings getters/setters |
| `OrdersController.php` | Graceful error handling for invalid transitions |
| `AbstractJsonController.php` | Error message extraction for JSON responses |
| `sql/updates/mysql/0.1.9.sql` | Migration for review columns |

### Frontend

| File | Changes |
|------|---------|
| `OrdersPanel.vue` | Review badge, transition error display, v-if chain fix |
| `CategoryPanel.vue` | v-if chain fix for error display |
| `ProductPanel.vue` | v-if chain fix for error display |
| `CouponsPanel.vue` | v-if chain fix for error display |
| `CustomersPanel.vue` | v-if chain fix for error display |
| `LogsPanel.vue` | v-if chain fix for error display |
| `SettingsPanel.vue` | Stale order cleanup settings UI |
| `useSettings.js` | Normalize new settings fields |
| `App.vue` | Try-catch for transition errors |

### New Plugin

```
plugins/task/nxpeasycartcleanup/
├── nxpeasycartcleanup.xml
├── services/provider.php
├── src/Extension/Nxpeasycartcleanup.php
└── language/en-GB/
    ├── plg_task_nxpeasycartcleanup.ini
    └── plg_task_nxpeasycartcleanup.sys.ini
```

## Testing

### State Machine

```bash
# Attempt invalid transition (should return 400)
curl -X POST "https://example.com/administrator/index.php?option=com_nxpeasycart&task=api.orders.transition&id=123" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -d '{"state": "pending"}'
# Order is currently "paid" - transition to "pending" should fail
```

### Stale Order Cleanup

```bash
# Run task manually via CLI
php cli/joomla.php scheduler:run --id=<task_id>

# Check logs
tail -f administrator/logs/joomla_scheduler.php
```

### Review Flags

1. Create a test order
2. Manually set `needs_review=1` and `review_reason='test:{"note":"manual test"}'`
3. Verify badge appears in admin orders list
4. Verify warning alert appears in order details
