# Digital Product Support - Implementation Plan

## Overview

This plan adds digital product support to NXP Easy Cart while maintaining backward compatibility with physical products. The approach is modular - you can ship a basic version first and enhance later.

---

## Phase 1: Database Schema Updates (Foundation)

### New Tables

```sql
-- Digital files attached to products/variants
CREATE TABLE `#__nxp_easycart_digital_files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,           -- NULL = applies to all variants
    `filename` VARCHAR(255) NOT NULL,          -- Original filename for display
    `storage_path` VARCHAR(500) NOT NULL,      -- Relative path in protected storage
    `file_size` BIGINT UNSIGNED DEFAULT 0,     -- Bytes
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `version` VARCHAR(50) DEFAULT '1.0',       -- For software versioning
    `created` DATETIME NOT NULL,
    `modified` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Download tokens for purchased digital products
CREATE TABLE `#__nxp_easycart_downloads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `order_item_id` INT UNSIGNED NOT NULL,
    `file_id` INT UNSIGNED NOT NULL,
    `token` CHAR(64) NOT NULL,                 -- Secure random token for URL
    `download_count` INT UNSIGNED DEFAULT 0,
    `max_downloads` INT UNSIGNED DEFAULT NULL, -- NULL = unlimited
    `expires_at` DATETIME DEFAULT NULL,        -- NULL = never expires
    `last_download_at` DATETIME DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,     -- Last download IP
    `created` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_token` (`token`),
    KEY `idx_order` (`order_id`),
    KEY `idx_order_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Schema Modifications

```sql
-- Products table: add product type
ALTER TABLE `#__nxp_easycart_products`
    ADD COLUMN `product_type` ENUM('physical', 'digital') NOT NULL DEFAULT 'physical' AFTER `active`;

-- Variants table: add digital override (for mixed products)
ALTER TABLE `#__nxp_easycart_variants`
    ADD COLUMN `is_digital` TINYINT(1) NOT NULL DEFAULT 0 AFTER `weight`;

-- Order items: track digital delivery
ALTER TABLE `#__nxp_easycart_order_items`
    ADD COLUMN `is_digital` TINYINT(1) NOT NULL DEFAULT 0 AFTER `total_cents`,
    ADD COLUMN `delivered_at` DATETIME DEFAULT NULL AFTER `is_digital`;

-- Orders: track if order contains digital items (for quick filtering)
ALTER TABLE `#__nxp_easycart_orders`
    ADD COLUMN `has_digital` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_method`,
    ADD COLUMN `has_physical` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_digital`;
```

### Settings Additions

| Key | Default | Description |
|-----|---------|-------------|
| `digital_download_max` | 5 | Default max downloads per purchase |
| `digital_download_expiry` | 30 | Days until download link expires (0 = never) |
| `digital_storage_path` | /media/com_nxpeasycart/downloads | Protected storage location |
| `digital_auto_fulfill` | 1 | Auto-mark digital orders as fulfilled |

---

## Phase 2: Backend Services

### 2.1 DigitalFileService

**Location:** `administrator/components/com_nxpeasycart/src/Service/DigitalFileService.php`

**Responsibilities:**
- Upload/store files to protected directory (outside webroot or with .htaccess protection)
- Generate secure download tokens
- Validate download requests (token, limits, expiry)
- Track download statistics
- Stream file downloads with proper headers

**Key Methods:**

```php
class DigitalFileService
{
    // File management
    public function upload(int $productId, ?int $variantId, UploadedFile $file): array;
    public function delete(int $fileId): bool;
    public function getFilesForProduct(int $productId): array;
    public function getFilesForVariant(int $variantId): array;

    // Download management
    public function createDownloadToken(int $orderId, int $orderItemId, int $fileId): string;
    public function validateToken(string $token): ?array; // Returns download record or null
    public function recordDownload(string $token, string $ipAddress): bool;
    public function streamDownload(string $token): void; // Sends file with headers

    // Bulk operations for order creation
    public function createDownloadsForOrder(int $orderId, array $orderItems): array;
    public function getDownloadsForOrder(int $orderId): array;
}
```

### 2.2 OrderService Updates

**Modifications to existing `OrderService.php`:**

```php
public function create(array $data): array
{
    // ... existing validation ...

    // NEW: Determine if order has digital/physical items
    $hasDigital = false;
    $hasPhysical = false;

    foreach ($data['items'] as $item) {
        if ($this->isDigitalItem($item)) {
            $hasDigital = true;
        } else {
            $hasPhysical = true;
        }
    }

    // NEW: Skip shipping validation for digital-only orders
    if (!$hasPhysical) {
        $data['shipping_cents'] = 0;
        $data['shipping'] = null;
    }

    // ... existing order creation ...

    // NEW: Store digital/physical flags
    $order['has_digital'] = $hasDigital;
    $order['has_physical'] = $hasPhysical;

    // ... insert order ...

    // NEW: Generate download tokens for digital items
    if ($hasDigital) {
        $this->digitalFileService->createDownloadsForOrder($orderId, $digitalItems);
    }

    return $order;
}

// NEW: Auto-fulfill digital-only orders on payment
public function onPaymentComplete(int $orderId): void
{
    $order = $this->get($orderId);

    if ($order['has_digital'] && !$order['has_physical']) {
        // Digital-only: auto-fulfill
        if ($this->settings->get('digital_auto_fulfill', true)) {
            $this->transitionState($orderId, 'fulfilled', 0); // System actor
        }
    }
}
```

---

## Phase 3: Admin UI Updates

### 3.1 Product Editor - Digital Files Tab

**Location:** `media/com_nxpeasycart/src/app/components/ProductEditor.vue`

Add a new tab for digital products:

```
[General] [Variants] [Images] [Categories] [Digital Files]
```

**Digital Files Tab Features:**
- Product type selector: Physical / Digital
- File upload dropzone (drag & drop)
- File list with: filename, size, version, upload date, delete button
- Per-variant file assignment (for variants with different downloads)
- Version field for software products

### 3.2 Settings Panel - Digital Products Tab

**New settings section:**

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| Default max downloads | Number | 5 | Downloads allowed per purchase |
| Download link expiry | Number | 30 | Days until links expire (0=never) |
| Auto-fulfill digital orders | Toggle | On | Automatically mark paid digital orders as fulfilled |
| Storage path | Text | (auto) | Protected file storage location |

### 3.3 Orders Panel - Download Links

**Order detail modal additions:**
- "Digital Downloads" section showing:
  - File name
  - Download count / max
  - Expiry date
  - Copy download link button
  - Resend download email button
  - Reset download count button

---

## Phase 4: Checkout Flow Updates

### 4.1 Cart Analysis

**CartPresentationService updates:**

```php
public function hydrate(array $cart): array
{
    // ... existing hydration ...

    // NEW: Analyze cart composition
    $hasDigital = false;
    $hasPhysical = false;

    foreach ($items as $item) {
        $variant = $this->getVariant($item['variant_id']);
        $product = $this->getProduct($item['product_id']);

        if ($variant['is_digital'] || $product['product_type'] === 'digital') {
            $hasDigital = true;
            $item['is_digital'] = true;
        } else {
            $hasPhysical = true;
            $item['is_digital'] = false;
        }
    }

    $cart['has_digital'] = $hasDigital;
    $cart['has_physical'] = $hasPhysical;
    $cart['requires_shipping'] = $hasPhysical;

    return $cart;
}
```

### 4.2 Checkout UI Conditional Logic

**checkout.js island updates:**

```javascript
// Computed properties
const requiresShipping = computed(() => cart.value.has_physical);
const isDigitalOnly = computed(() => cart.value.has_digital && !cart.value.has_physical);

// Template conditionals
// - Hide shipping address section if !requiresShipping
// - Hide shipping method selector if !requiresShipping
// - Show "Instant delivery" badge for digital items
// - Show different messaging for digital-only orders
```

**UI Changes:**
- Digital-only cart: Hide shipping address form entirely
- Mixed cart: Show shipping for physical items, badge digital items
- Digital items show "Instant Download" or "Email Delivery" badge
- Order summary distinguishes physical vs digital items

---

## Phase 5: Download Delivery

### 5.1 Download Controller

**Location:** `components/com_nxpeasycart/src/Controller/DownloadController.php`

```php
class DownloadController extends BaseController
{
    /**
     * Public download endpoint: /shop/download/{token}
     */
    public function download(): void
    {
        $token = $this->input->getString('token', '');

        $download = $this->digitalFileService->validateToken($token);

        if (!$download) {
            throw new \RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID'), 404);
        }

        if ($download['expires_at'] && strtotime($download['expires_at']) < time()) {
            throw new \RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_EXPIRED'), 410);
        }

        if ($download['max_downloads'] && $download['download_count'] >= $download['max_downloads']) {
            throw new \RuntimeException(Text::_('COM_NXPEASYCART_ERROR_DOWNLOAD_LIMIT'), 403);
        }

        // Record download and stream file
        $this->digitalFileService->recordDownload($token, $_SERVER['REMOTE_ADDR']);
        $this->digitalFileService->streamDownload($token);
    }
}
```

### 5.2 Email Template Updates

**order_confirmation.php additions:**

```php
<?php if (!empty($order['downloads'])): ?>
<h3><?php echo Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_TITLE'); ?></h3>
<p><?php echo Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_INTRO'); ?></p>

<table>
    <tr>
        <th><?php echo Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_FILE'); ?></th>
        <th><?php echo Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_LINK'); ?></th>
    </tr>
    <?php foreach ($order['downloads'] as $download): ?>
    <tr>
        <td><?php echo htmlspecialchars($download['filename']); ?></td>
        <td>
            <a href="<?php echo $download['url']; ?>">
                <?php echo Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_BUTTON'); ?>
            </a>
            <?php if ($download['expires_at']): ?>
            <br><small><?php echo Text::sprintf('COM_NXPEASYCART_EMAIL_DOWNLOADS_EXPIRES', $download['expires_at']); ?></small>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
```

### 5.3 Order Status Page Updates

**order/default.php additions:**

Show download links on the public order status page for digital items:
- Only visible when order is paid/fulfilled
- Shows remaining downloads and expiry
- Direct download buttons

---

## Phase 6: File Storage Security

### 6.1 Protected Storage Directory

**Structure:**
```
/media/com_nxpeasycart/
├── downloads/           # Protected - no direct access
│   ├── .htaccess       # Deny all
│   └── {product_id}/
│       └── {hash}_{filename}
├── js/
├── css/
└── images/
```

**.htaccess for downloads folder:**
```apache
# Deny all direct access - files served via PHP only
Order deny,allow
Deny from all
```

### 6.2 File Naming

Files stored with hash prefix to prevent enumeration:
```
/downloads/42/a1b2c3d4e5f6_product-manual.pdf
```

---

## Implementation Order & Estimates

| Phase | Scope | Effort | Dependencies |
|-------|-------|--------|--------------|
| **Phase 1** | Database schema | ~2 hours | None |
| **Phase 2** | Backend services | ~6-8 hours | Phase 1 |
| **Phase 3** | Admin UI | ~4-6 hours | Phase 2 |
| **Phase 4** | Checkout flow | ~4 hours | Phase 2 |
| **Phase 5** | Download delivery | ~3-4 hours | Phase 2, 4 |
| **Phase 6** | Security hardening | ~2 hours | Phase 5 |

**Total estimate: ~21-26 hours of development**

---

## MVP vs Full Implementation

### MVP (Ship First)
- Product type field (physical/digital)
- Skip shipping for digital-only orders
- Manual download link generation in admin
- Email download links on payment

### Full Implementation (Later)
- File upload UI in product editor
- Per-variant file assignments
- Download limits and expiry
- Download statistics
- License key generation (for software)
- Watermarking (for PDFs)

---

## Migration Path for Existing Stores

1. Schema migration sets all existing products to `product_type = 'physical'`
2. No breaking changes - existing orders unaffected
3. Merchants opt-in to digital by changing product type
4. Settings default to sensible values (5 downloads, 30-day expiry)

---

## Language Strings Required

```ini
; Digital Products
COM_NXPEASYCART_FIELD_PRODUCT_TYPE="Product Type"
COM_NXPEASYCART_FIELD_PRODUCT_TYPE_PHYSICAL="Physical Product"
COM_NXPEASYCART_FIELD_PRODUCT_TYPE_DIGITAL="Digital Product"
COM_NXPEASYCART_DIGITAL_FILES="Digital Files"
COM_NXPEASYCART_DIGITAL_FILES_UPLOAD="Upload File"
COM_NXPEASYCART_DIGITAL_FILES_EMPTY="No files attached to this product."

; Download Settings
COM_NXPEASYCART_SETTINGS_DIGITAL_TITLE="Digital Products"
COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_DOWNLOADS="Default Max Downloads"
COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_DOWNLOADS_DESC="Maximum number of times a customer can download their purchase. Set to 0 for unlimited."
COM_NXPEASYCART_SETTINGS_DIGITAL_EXPIRY="Download Link Expiry (Days)"
COM_NXPEASYCART_SETTINGS_DIGITAL_EXPIRY_DESC="Number of days until download links expire. Set to 0 for no expiry."
COM_NXPEASYCART_SETTINGS_DIGITAL_AUTO_FULFILL="Auto-Fulfill Digital Orders"
COM_NXPEASYCART_SETTINGS_DIGITAL_AUTO_FULFILL_DESC="Automatically mark digital-only orders as fulfilled when payment is complete."

; Checkout
COM_NXPEASYCART_CHECKOUT_DIGITAL_DELIVERY="Instant Download"
COM_NXPEASYCART_CHECKOUT_DIGITAL_NOTE="Digital products will be available for download immediately after payment."

; Emails
COM_NXPEASYCART_EMAIL_DOWNLOADS_TITLE="Your Downloads"
COM_NXPEASYCART_EMAIL_DOWNLOADS_INTRO="Your digital products are ready for download:"
COM_NXPEASYCART_EMAIL_DOWNLOADS_FILE="File"
COM_NXPEASYCART_EMAIL_DOWNLOADS_LINK="Download"
COM_NXPEASYCART_EMAIL_DOWNLOADS_BUTTON="Download Now"
COM_NXPEASYCART_EMAIL_DOWNLOADS_EXPIRES="Expires: %s"

; Errors
COM_NXPEASYCART_ERROR_DOWNLOAD_INVALID="Invalid or expired download link."
COM_NXPEASYCART_ERROR_DOWNLOAD_EXPIRED="This download link has expired."
COM_NXPEASYCART_ERROR_DOWNLOAD_LIMIT="You have reached the maximum number of downloads for this file."

; Order Status Page
COM_NXPEASYCART_ORDER_DOWNLOADS_TITLE="Your Downloads"
COM_NXPEASYCART_ORDER_DOWNLOADS_REMAINING="%d downloads remaining"
COM_NXPEASYCART_ORDER_DOWNLOADS_UNLIMITED="Unlimited downloads"
COM_NXPEASYCART_ORDER_DOWNLOADS_EXPIRES="Expires %s"
```

---

## Future Enhancements (Post-MVP)

1. **License Key Generation** - For software products, generate unique license keys per purchase
2. **PDF Watermarking** - Automatically watermark PDFs with buyer's email
3. **Streaming Media** - Support for video/audio with time-limited access
4. **Subscription Downloads** - Recurring access to updated files
5. **Download Analytics** - Track download patterns, popular files, geographic distribution
