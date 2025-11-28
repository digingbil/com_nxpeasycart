# GDPR Compliance

NXP Easy Cart includes built-in GDPR (General Data Protection Regulation) compliance features for data export and anonymisation, supporting Article 17 (Right to Erasure) and Article 20 (Data Portability).

---

## Overview

The GDPR service provides two core operations:

| Operation | GDPR Article | Purpose                                           | API Endpoint                    |
| --------- | ------------ | ------------------------------------------------- | ------------------------------- |
| Export    | Article 20   | Data portability - provide customer their data    | `GET ?task=api.gdpr.export`     |
| Anonymise | Article 17   | Right to erasure - remove identifying information | `POST ?task=api.gdpr.anonymise` |

---

## Architecture

### GdprService

Located at: `administrator/components/com_nxpeasycart/src/Service/GdprService.php`

```php
class GdprService
{
    public function exportByEmail(string $email): array;
    public function anonymiseByEmail(string $email): int;
}
```

### GdprController

Located at: `administrator/components/com_nxpeasycart/src/Controller/GdprController.php`

Provides admin API endpoints with proper ACL enforcement.

---

## Data Export (Article 20)

### What's Exported

The export returns all data associated with an email address:

```json
{
    "email": "customer@example.com",
    "orders": [
        {
            "id": 123,
            "order_no": "EC-00000001",
            "state": "fulfilled",
            "subtotal_cents": 4500,
            "tax_cents": 500,
            "shipping_cents": 0,
            "discount_cents": 0,
            "total_cents": 5000,
            "currency": "EUR",
            "billing": { ... },
            "shipping": { ... },
            "created": "2025-01-15 10:30:00",
            "items": [
                {
                    "sku": "PROD-001",
                    "title": "Product Name",
                    "qty": 2,
                    "unit_price_cents": 2250
                }
            ],
            "transactions": [
                {
                    "gateway": "stripe",
                    "reference": "pi_xxx",
                    "status": "paid",
                    "amount_cents": 5000,
                    "created": "2025-01-15 10:32:00"
                }
            ]
        }
    ]
}
```

### API Endpoint

```
GET /administrator/index.php?option=com_nxpeasycart&task=api.gdpr.export&email=customer@example.com
```

**Required permission:** `core.manage`

**Response:** JSON containing all customer data

### Usage Example

```php
$gdpr = $container->get(GdprService::class);
$data = $gdpr->exportByEmail('customer@example.com');

// Return as downloadable JSON
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="gdpr-export.json"');
echo json_encode($data, JSON_PRETTY_PRINT);
```

---

## Data Anonymisation (Article 17)

### What's Anonymised

Anonymisation replaces all PII (Personally Identifiable Information) while preserving order data for accounting:

| Field                | Action  | Result                              |
| -------------------- | ------- | ----------------------------------- |
| `email`              | Hashed  | `gdpr+a1b2c3d4e5f6@example.invalid` |
| `billing`            | Cleared | `NULL`                              |
| `shipping`           | Cleared | `NULL`                              |
| `carrier`            | Cleared | `NULL`                              |
| `tracking_number`    | Cleared | `NULL`                              |
| `tracking_url`       | Cleared | `NULL`                              |
| `fulfillment_events` | Cleared | `NULL`                              |

### What's Preserved (for accounting)

- Order ID and order number
- Order state
- Financial data (subtotal, tax, shipping, discount, total)
- Currency
- Timestamps
- Line items (SKU, quantity, prices)
- Transaction records (gateway, status, amounts)

### API Endpoint

```
POST /administrator/index.php?option=com_nxpeasycart&task=api.gdpr.anonymise
Content-Type: application/json
X-CSRF-Token: <token>

{"email": "customer@example.com"}
```

**Required permission:** `core.admin` (Super User)
**Required:** Valid CSRF token

**Response:**

```json
{
    "success": true,
    "message": "Anonymised 3 orders for customer",
    "count": 3
}
```

### Usage Example

```php
$gdpr = $container->get(GdprService::class);
$count = $gdpr->anonymiseByEmail('customer@example.com');

// Returns number of orders anonymised
```

---

## Implementation Details

### Anonymised Email Format

The anonymised email follows this pattern:

```
gdpr+<12-char-hash>@example.invalid
```

Example: `gdpr+a1b2c3d4e5f6@example.invalid`

- Hash is generated from `sha1(original_email + microtime())`
- `.invalid` TLD ensures no accidental email delivery
- Hash prevents de-anonymisation (one-way)

### Database Updates

Anonymisation runs this SQL:

```sql
UPDATE #__nxp_easycart_orders
SET
    email = 'gdpr+xxx@example.invalid',
    billing = NULL,
    shipping = NULL,
    carrier = NULL,
    tracking_number = NULL,
    tracking_url = NULL,
    fulfillment_events = NULL,
    modified = NOW()
WHERE email = ?
```

---

## Admin Interface

### Export Request Workflow

1. Customer requests data export (via support contact)
2. Admin navigates to **NXP Easy Cart → Settings → GDPR**
3. Enter customer email
4. Click "Export Data"
5. JSON file downloads automatically
6. Send file to customer securely

### Erasure Request Workflow

1. Customer requests data erasure (via support contact)
2. Verify customer identity (GDPR requirement)
3. Admin navigates to **NXP Easy Cart → Settings → GDPR**
4. Enter customer email
5. Review orders that will be affected
6. Click "Anonymise Data" (requires Super User)
7. Confirm action (irreversible)
8. Notify customer of completion

---

## Security Considerations

### Access Control

- Export requires `core.manage` permission
- Anonymise requires `core.admin` (Super User) permission
- All operations require valid CSRF token
- All operations are logged to audit trail

### Audit Logging

Both export and anonymise operations are logged:

```php
// Export logged as
AuditService::log('gdpr.export', [
    'email' => $email,
    'order_count' => count($orders),
]);

// Anonymise logged as
AuditService::log('gdpr.anonymise', [
    'email' => $email,
    'orders_affected' => $count,
]);
```

### Rate Limiting

GDPR endpoints are subject to the same rate limiting as other admin API endpoints to prevent abuse.

---

## Compliance Checklist

### Before Launch

- [ ] Document your data retention policy
- [ ] Create internal GDPR request handling procedure
- [ ] Train staff on export/anonymise workflows
- [ ] Test export endpoint with sample data
- [ ] Test anonymise endpoint with sample data
- [ ] Verify audit logs capture GDPR operations

### For Each Request

- [ ] Verify requestor identity (government ID, account verification)
- [ ] Log the request in your records
- [ ] Complete within 30 days (GDPR requirement)
- [ ] Notify customer of completion
- [ ] Document any exceptions (legal hold, etc.)

### Exceptions to Erasure

You may retain data despite erasure request if:

- Required for legal compliance (tax records)
- Required for legal claims (disputes)
- Required for public interest (fraud prevention)

Document any exceptions clearly.

---

## Integration with Other Systems

### CRM Sync

If syncing orders to external CRM, ensure anonymisation propagates:

```php
// In your CRM plugin
public function onNxpEasycartAfterGdprAnonymise($event)
{
    $email = $event->getArgument('original_email');
    $this->crmClient->anonymiseCustomer($email);
}
```

### Analytics

Anonymised orders are still counted in analytics but cannot be traced to individuals:

- Order counts: ✓ Preserved
- Revenue totals: ✓ Preserved
- Customer identity: ✗ Removed

### Backups

Remember that backups may contain pre-anonymisation data. Document backup retention in your privacy policy.

---

## Testing

### Unit Tests

See `tests/Unit/Administrator/Service/GdprServiceTest.php`:

- `testEmailValidationForExport()` - Valid/invalid email handling
- `testAnonymisationHashGeneration()` - Hash format validation
- `testAnonymisedEmailsAreUnique()` - No hash collisions
- `testExportDataStructure()` - JSON structure validation
- `testAnonymisationRemovesPii()` - All PII fields cleared
- `testOrderDataRetainedForAccounting()` - Financial data preserved
- `testGdprExportJsonFormat()` - Valid JSON output
- `testRightToErasure()` - Cannot reverse anonymisation
- `testDataPortabilityFormat()` - Machine-readable format

### Manual Testing

1. Create orders with test email
2. Run export - verify all data included
3. Run anonymise - verify PII removed
4. Run export again - verify anonymised data returned
5. Check audit logs for both operations

---

## Related Documentation

- [security-audit-fixes.md](security-audit-fixes.md) - Overall security documentation
- [risk-register.md](risk-register.md) - Data handling risks
- [testing.md](testing.md) - Test procedures

---

## External Resources

- [GDPR Official Text](https://gdpr-info.eu/)
- [Article 17 - Right to Erasure](https://gdpr-info.eu/art-17-gdpr/)
- [Article 20 - Data Portability](https://gdpr-info.eu/art-20-gdpr/)
- [ICO Guide to GDPR](https://ico.org.uk/for-organisations/guide-to-data-protection/guide-to-the-general-data-protection-regulation-gdpr/)

---

**Last Updated**: 2025-11-28
