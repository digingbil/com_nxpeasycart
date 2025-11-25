# Security & Rate Limiting

## Overview

NXP Easy Cart implements multi-layer rate limiting to protect checkout and payment endpoints from abuse, bot spam, and brute-force attacks. The system uses PSR-16 cache-backed counters with configurable limits per IP address, email, and session.

## Architecture

### RateLimiter Service

**Location:** `administrator/components/com_nxpeasycart/src/Administrator/Service/RateLimiter.php`

The `RateLimiter` service provides three tracking scopes:

- **IP-based limiting**: Tracks attempts by client IP address
- **Email-based limiting**: Tracks attempts by customer email
- **Session-based limiting**: Tracks attempts by Joomla session ID

Each scope maintains independent counters with configurable limits and time windows.

### Integration Points

#### CartController
- `add()`, `remove()`, `update()`: Soft rate limits to prevent cart manipulation abuse
- Validates against session-based limits

#### PaymentController
- `checkout()`: Hard rate limits with stricter rules
- Separate limits for hosted gateways (Stripe, PayPal) vs offline methods (COD, bank transfer)
- Validates against IP, email, and session limits

### Configuration

Rate limits are managed through the admin Settings panel → Security tab. All settings persist to the `#__nxp_easycart_settings` table under the `security.rate_limits` key.

#### Checkout Limits (All Gateways)
- **Checkout window**: Time window in minutes for tracking attempts (default: 10)
- **Checkout attempts per IP**: Maximum attempts from a single IP (default: 10)
- **Checkout attempts per email**: Maximum attempts using a single email (default: 5)
- **Checkout attempts per session**: Maximum attempts per Joomla session (default: 15)

#### Offline Payment Limits (COD & Bank Transfer)
- **Offline window**: Time window in minutes for tracking attempts (default: 30)
- **Offline attempts per IP**: Maximum attempts from a single IP (default: 3)
- **Offline attempts per email**: Maximum attempts using a single email (default: 3)

Setting any limit to `0` disables that specific check.

## Database Schema

Rate limits are stored as JSON in the `#__nxp_easycart_settings` table:

```sql
SELECT `key`, `value` FROM `#__nxp_easycart_settings`
WHERE `key` = 'security.rate_limits';
```

Example stored value:
```json
{
  "checkout_ip_limit": 10,
  "checkout_email_limit": 5,
  "checkout_session_limit": 15,
  "checkout_window": 600,
  "offline_ip_limit": 3,
  "offline_email_limit": 3,
  "offline_window": 1800
}
```

**Note**: Time windows are stored in **seconds** in the database, but displayed and edited in **minutes** in the admin UI.

## Admin Settings Panel

### Vue Component
**Location:** `media/com_nxpeasycart/src/app/components/SettingsPanel.vue`

The Security tab includes form fields for all rate limit settings. Values are bound to the `securityDraft` reactive object:

```javascript
const securityDraft = reactive({
    checkoutWindowMinutes: 10,
    checkoutIpLimit: 10,
    checkoutEmailLimit: 5,
    checkoutSessionLimit: 15,
    offlineWindowMinutes: 30,
    offlineIpLimit: 3,
    offlineEmailLimit: 3,
});
```

### API Endpoint
**Location:** `administrator/components/com_nxpeasycart/src/Administrator/Controller/Api/SettingsController.php`

- **GET** `/administrator/index.php?option=com_nxpeasycart&task=api.settings.show` – Loads current settings
- **POST** `/administrator/index.php?option=com_nxpeasycart&task=api.settings.update` – Saves settings

### Payload Format

When saving security settings, the frontend sends:

```json
{
  "security": {
    "rate_limits": {
      "checkout_window_minutes": 10,
      "checkout_ip_limit": 10,
      "checkout_email_limit": 5,
      "checkout_session_limit": 15,
      "offline_window_minutes": 30,
      "offline_ip_limit": 3,
      "offline_email_limit": 3
    }
  }
}
```

The backend converts `_minutes` fields to seconds before persisting to the database.

## Bug Fixes

### Issue: Settings Resetting on Save

**Symptom**: When saving security settings, values would reset to defaults (e.g., offline window changing from 45 to 30 minutes) either immediately or after page reload.

**Root Cause**: Two bugs in `SettingsController.php`:

1. **Missing `checkout_session_limit` field**: The Vue component wasn't sending this field in the save payload, causing the backend normalization to fall back to defaults for all values.

2. **Incorrect `show()` method**: The `show()` method was calling `normaliseRateLimits()` with database values as the first argument, treating stored seconds as if they were input with `_minutes` fields. This caused the method to use default fallbacks instead of the actual stored values.

**Solution**:

1. Added `checkoutSessionLimit` field to:
   - `securityDraft` reactive object initialization
   - `applySettings()` function to load the value from server
   - `saveSecurity()` function to include it in the save payload
   - Security tab UI with proper form field
   - Language strings for the new field

2. Fixed `show()` method to read stored values directly:
   ```php
   // OLD (incorrect):
   $rateLimits = $this->normaliseRateLimits((array) $service->get('security.rate_limits', []));

   // NEW (correct):
   $rateLimits = (array) $service->get('security.rate_limits', []);
   $defaults = $this->getDefaultRateLimits();
   foreach ($defaults as $key => $defaultValue) {
       if (!isset($rateLimits[$key])) {
           $rateLimits[$key] = $defaultValue;
       }
   }
   ```

3. Fixed `update()` method to return saved values directly instead of re-reading from database:
   ```php
   // OLD (incorrect):
   $service->set('security.rate_limits', $rateLimits);
   $rateLimits = $this->normaliseRateLimits((array) $service->get('security.rate_limits', []));

   // NEW (correct):
   $service->set('security.rate_limits', $rateLimits);
   // Use $rateLimits variable directly in response
   ```

**Files Changed**:
- `administrator/components/com_nxpeasycart/src/Administrator/Controller/Api/SettingsController.php:47-70` (show method)
- `administrator/components/com_nxpeasycart/src/Administrator/Controller/Api/SettingsController.php:190-192` (update method)
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue:2280-2288` (securityDraft initialization)
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue:2391-2413` (applySettings function)
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue:2517-2529` (saveSecurity function)
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue:521-540` (UI form field)
- `administrator/language/en-GB/com_nxpeasycart.ini:387` (language string)

## Future Enhancements

Potential improvements for Phase 2:

- Honeypot field integration on checkout forms
- CAPTCHA support for high-risk scenarios
- Redis cache backend for distributed rate limiting
- Configurable block duration and temporary bans
- Admin notification on repeated limit violations
- IP whitelist/blacklist management
- Rate limit analytics and reporting
