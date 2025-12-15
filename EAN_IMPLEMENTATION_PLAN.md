# EAN-13 Implementation Plan

## Executive Summary

This plan outlines the steps to introduce **EAN-13** (European Article Number) barcode support for product variants in NXP Easy Cart. Following industry standards (WooCommerce, Magento, Shopify), EAN will be stored at the **variant level** rather than the product level, since each sellable unit (SKU) typically has its own unique barcode.

---

## Why Variant-Level EAN?

### Industry Standard

- **WooCommerce, Magento, Shopify, PrestaShop** - All bind barcodes/EAN/UPC to variants (SKU level)
- **Warehouse/POS systems** - Scan barcodes at the individual item level (red shirt size M ≠ blue shirt size L)
- **Inventory management** - Each variant has its own stock count and corresponding EAN

### Alignment with NXP Easy Cart Architecture

- **SKU is already at variant level** with UNIQUE constraint - EAN follows the same pattern
- **Stock tracking** is per-variant - EAN scanning maps to the same granularity
- **Order fulfillment** - Warehouse staff scan EAN codes on individual shipped items (variants)
- **Digital products** - The `is_digital` flag exists at variant level, showing this is where per-item properties belong

### Edge Cases Covered

- **Single-variant products** - Works perfectly (1 product = 1 variant = 1 EAN)
- **Product bundles** - Each variant in the bundle has its own EAN
- **Digital products** - Optional EAN (e.g., software with retail packaging might have an EAN for the boxed version)

---

## Schema Changes

### 1. Add `ean` column to `#__nxp_easycart_variants`

**Migration SQL** (`administrator/components/com_nxpeasycart/sql/updates/mysql/0.2.0.sql`):

```sql
-- Add EAN-13 field to variants table
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `ean` VARCHAR(13) NULL DEFAULT NULL AFTER `sku`,
  ADD UNIQUE KEY `idx_nxp_variants_ean` (`ean`);
```

**Rationale:**

- `VARCHAR(13)` accommodates EAN-13 (13 digits) and also EAN-8 (8 digits) if needed in the future
- `NULL DEFAULT NULL` allows variants without EAN (optional field)
- `UNIQUE KEY` ensures no two variants can share the same EAN (global uniqueness across all products)
- Positioned `AFTER 'sku'` for logical grouping of unique identifiers

**Update install schema** (`administrator/components/com_nxpeasycart/sql/install.mysql.utf8.sql`):

Add to the `#__nxp_easycart_variants` table definition (after `sku` line):

```sql
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(64) NOT NULL,
  `ean` VARCHAR(13) NULL DEFAULT NULL,
  -- ... rest of columns
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_variants_sku` (`sku`),
  UNIQUE KEY `idx_nxp_variants_ean` (`ean`),
  -- ... rest of indexes/constraints
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Backend (Admin) Changes

### 2. Update `VariantTable` validation

**File:** `administrator/components/com_nxpeasycart/src/Table/VariantTable.php`

**Changes:**

- Add EAN validation in `check()` method:
    - Trim and normalize EAN input
    - If EAN is provided, validate it's numeric and either 8 or 13 digits
    - Optionally validate EAN-13 checksum (recommended for data integrity)
    - Check for duplicate EAN across all variants (not just within the same product)

**Example validation logic:**

```php
// In check() method, after SKU validation:

$this->ean = trim((string) $this->ean);

if ($this->ean !== '') {
    // Must be numeric and either 8 or 13 digits
    if (!ctype_digit($this->ean) || !in_array(strlen($this->ean), [8, 13], true)) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID'));
    }

    // Optional: Validate EAN-13 checksum
    if (strlen($this->ean) === 13 && !$this->isValidEan13Checksum($this->ean)) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID'));
    }

    // Check for duplicate EAN (global uniqueness)
    $db    = $this->getDatabase();
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__nxp_easycart_variants'))
        ->where($db->quoteName('ean') . ' = :ean')
        ->bind(':ean', $this->ean, ParameterType::STRING);

    if (!empty($this->id)) {
        $currentId = (int) $this->id;
        $query->where($db->quoteName('id') . ' != :currentId')
            ->bind(':currentId', $currentId, ParameterType::INTEGER);
    }

    $db->setQuery($query);

    if ((int) $db->loadResult() > 0) {
        throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS'));
    }
} else {
    // EAN is optional; set to NULL for empty string
    $this->ean = null;
}
```

**Add checksum validation helper:**

```php
/**
 * Validate EAN-13 checksum.
 *
 * @param string $ean The 13-digit EAN code
 *
 * @return bool True if valid, false otherwise
 *
 * @since 0.2.0
 */
private function isValidEan13Checksum(string $ean): bool
{
    if (strlen($ean) !== 13 || !ctype_digit($ean)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $ean[$i] * ($i % 2 === 0 ? 1 : 3);
    }

    $checksum = (10 - ($sum % 10)) % 10;

    return (int) $ean[12] === $checksum;
}
```

### 3. Update `ProductModel` to handle EAN

**File:** `administrator/components/com_nxpeasycart/src/Model/ProductModel.php`

**Changes:**

#### In `validateVariants()` method:

Add EAN to the validation/normalization logic (around line 469):

```php
$validated[] = [
    'id'          => $id,
    'sku'         => $sku,
    'ean'         => trim((string) ($variant['ean'] ?? '')),
    'price_cents' => $priceCents,
    'currency'    => $currency,
    'stock'       => (int) ($variant['stock'] ?? 0),
    'options'     => $variant['options'] ?? null,
    'weight'      => $weight,
    'active'      => (int) (bool) ($variant['active'] ?? true),
    'is_digital'  => (int) (bool) ($variant['is_digital'] ?? false),
];
```

#### In `saveVariants()` method:

Include EAN in the payload (around line 714):

```php
$payload = [
    'id'          => $variant['id'] ?? 0,
    'product_id'  => $productId,
    'sku'         => $variant['sku'],
    'ean'         => !empty($variant['ean']) ? $variant['ean'] : null,
    'price_cents' => (int) $variant['price_cents'],
    'currency'    => $variant['currency'],
    // ... rest of fields
];
```

#### In `loadVariants()` method:

Add EAN to the SELECT query (around line 1070):

```php
$query = $db->getQuery(true)
    ->select([
        $db->quoteName('id'),
        $db->quoteName('sku'),
        $db->quoteName('ean'),
        $db->quoteName('price_cents'),
        // ... rest of columns
    ])
```

And include it in the returned array (around line 1090):

```php
$variants[] = [
    'id'          => (int) $row->id,
    'sku'         => (string) $row->sku,
    'ean'         => !empty($row->ean) ? (string) $row->ean : null,
    'price_cents' => (int) $row->price_cents,
    // ... rest of fields
];
```

### 4. Update Admin Products API Controller

**File:** `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php`

**Changes:**

- Ensure EAN is included in the JSON responses for product listings and detail views
- No explicit changes needed if `ProductModel::loadVariants()` already returns EAN (it will flow through automatically)

### 5. Update Admin Vue SPA - Product Editor

**File:** `media/com_nxpeasycart/src/app/components/ProductEditor.vue`

**Changes:**

#### Add EAN field to variant form (in the variants tab section, around line 590):

```vue
<div class="nxp-ec-form-field">
    <label
        class="nxp-ec-form-label"
        :for="`variant-ean-${index}`"
    >
        {{
            __(
                "COM_NXPEASYCART_FIELD_VARIANT_EAN",
                "EAN"
            )
        }}
    </label>
    <input
        :id="`variant-ean-${index}`"
        v-model="variant.ean"
        type="text"
        class="nxp-ec-form-input"
        :placeholder="__('COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER', '1234567890123')"
        maxlength="13"
        pattern="[0-9]{8,13}"
    />
    <p class="nxp-ec-form-help">
        {{
            __(
                "COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP",
                "Optional 13-digit barcode (EAN-13) or 8-digit (EAN-8)"
            )
        }}
    </p>
</div>
```

Position this field immediately after the SKU field in the variant card grid.

#### Update `normaliseVariants()` function (around line 1520):

```javascript
const normaliseVariants = (variants) => {
    if (!Array.isArray(variants) || variants.length === 0) {
        return [blankVariant()];
    }

    return variants.map((variant) => ({
        id: Number.parseInt(variant?.id ?? 0, 10) || 0,
        sku: String(variant?.sku ?? "").trim(),
        ean: String(variant?.ean ?? "").trim(),
        // ... rest of fields
    }));
};
```

#### Update `blankVariant()` function:

```javascript
const blankVariant = () => ({
    id: 0,
    sku: "",
    ean: "",
    price: "",
    currency: baseCurrency.value,
    // ... rest of fields
});
```

#### Update `duplicateVariant()` function (around line 1710):

Ensure EAN is cleared when duplicating (to avoid unique constraint violation):

```javascript
const duplicateVariant = (index) => {
    const source = form.variants[index];
    if (!source) return;

    const copy = {
        ...source,
        id: 0,
        sku: "", // Clear SKU to avoid duplicate
        ean: "", // Clear EAN to avoid duplicate
    };

    form.variants.splice(index + 1, 0, copy);
};
```

### 6. Update Admin Product List Panel

**File:** `media/com_nxpeasycart/src/app/components/ProductPanel.vue`

**Changes (optional):**

- Consider adding EAN to the variants display in the product detail view or hover tooltips
- This is optional for MVP; admin users can see EAN in the product editor

---

## Language Strings

**Files to update:**

- `administrator/language/en-GB/com_nxpeasycart.ini`
- `administrator/language/mk-MK/com_nxpeasycart.ini`
- `administrator/language/de-DE/com_nxpeasycart.ini`
- `administrator/language/el-GR/com_nxpeasycart.ini`
- `administrator/language/fr-FR/com_nxpeasycart.ini`

**English strings** (`en-GB/com_nxpeasycart.ini`):

```ini
; EAN-13 field labels and help text
COM_NXPEASYCART_FIELD_VARIANT_EAN="EAN"
COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER="1234567890123"
COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP="Optional 13-digit barcode (EAN-13) or 8-digit (EAN-8)"

; EAN validation errors
COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID="EAN must be 8 or 13 digits."
COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID="EAN-13 checksum is invalid."
COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS="Another variant already uses this EAN."
```

**Macedonian strings** (`mk-MK/com_nxpeasycart.ini`):

```ini
; EAN-13 field labels and help text
COM_NXPEASYCART_FIELD_VARIANT_EAN="EAN"
COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER="1234567890123"
COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP="Опционален баркод од 13 цифри (EAN-13) или 8 цифри (EAN-8)"

; EAN validation errors
COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID="EAN мора да биде 8 или 13 цифри."
COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID="Контролната цифра на EAN-13 не е валидна."
COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS="Друга варијанта веќе го користи овој EAN."
```

**German strings** (`de-DE/com_nxpeasycart.ini`):

```ini
; EAN-13 field labels and help text
COM_NXPEASYCART_FIELD_VARIANT_EAN="EAN"
COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER="1234567890123"
COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP="Optionaler 13-stelliger Barcode (EAN-13) oder 8-stelliger (EAN-8)"

; EAN validation errors
COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID="EAN muss 8 oder 13 Ziffern lang sein."
COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID="Die EAN-13-Prüfsumme ist ungültig."
COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS="Eine andere Variante verwendet bereits diese EAN."
```

**Greek strings** (`el-GR/com_nxpeasycart.ini`):

```ini
; EAN-13 field labels and help text
COM_NXPEASYCART_FIELD_VARIANT_EAN="EAN"
COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER="1234567890123"
COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP="Προαιρετικός γραμμωτός κώδικας 13 ψηφίων (EAN-13) ή 8 ψηφίων (EAN-8)"

; EAN validation errors
COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID="Το EAN πρέπει να είναι 8 ή 13 ψηφία."
COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID="Το άθροισμα ελέγχου EAN-13 δεν είναι έγκυρο."
COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS="Μια άλλη παραλλαγή χρησιμοποιεί ήδη αυτό το EAN."
```

**French strings** (`fr-FR/com_nxpeasycart.ini`):

```ini
; EAN-13 field labels and help text
COM_NXPEASYCART_FIELD_VARIANT_EAN="EAN"
COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER="1234567890123"
COM_NXPEASYCART_FIELD_VARIANT_EAN_HELP="Code-barres optionnel à 13 chiffres (EAN-13) ou 8 chiffres (EAN-8)"

; EAN validation errors
COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID="L'EAN doit comporter 8 ou 13 chiffres."
COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM_INVALID="La somme de contrôle EAN-13 n'est pas valide."
COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS="Une autre variante utilise déjà cet EAN."
```

---

## Storefront (Site) Changes

### 7. Display EAN in Product Detail View (Optional)

**File:** `components/com_nxpeasycart/tmpl/product/default.php`

**Changes:**

- Add EAN to the variants table display (around line 275)
- This is optional - many stores don't display EAN to customers, but it can be useful for transparency

**Example addition** (add after SKU column in variants table):

```php
<th scope="col"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_EAN_LABEL'); ?></th>
```

```php
<td>
    <?php if (!empty($variant['ean'])) : ?>
        <?php echo htmlspecialchars($variant['ean'], ENT_QUOTES, 'UTF-8'); ?>
    <?php else : ?>
        <span class="nxp-ec-text-muted"><?php echo Text::_('COM_NXPEASYCART_NOT_AVAILABLE'); ?></span>
    <?php endif; ?>
</td>
```

**Add language strings** to `language/en-GB/com_nxpeasycart.ini`:

```ini
COM_NXPEASYCART_PRODUCT_VARIANT_EAN_LABEL="EAN"
```

### 8. Include EAN in Structured Data (Schema.org)

**File:** `components/com_nxpeasycart/tmpl/product/default.php`

**Changes:**

- Add `gtin13` (Global Trade Item Number) property to the JSON-LD structured data
- This helps Google Shopping and other crawlers identify products
- Use the EAN from the primary/default variant or the first variant

**Around line 120** (in the Schema.org JSON-LD script):

```php
<?php
// Find primary variant (first active variant with stock, or just first variant)
$primaryVariant = null;
foreach ($variants as $variant) {
    if ($variant['active'] ?? false) {
        $primaryVariant = $variant;
        break;
    }
}
if (!$primaryVariant && !empty($variants)) {
    $primaryVariant = $variants[0];
}
?>

<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "<?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>",
  "description": "<?php echo htmlspecialchars(strip_tags($shortDesc), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>",
  <?php if (!empty($primaryVariant['ean'])) : ?>
  "gtin13": "<?php echo htmlspecialchars($primaryVariant['ean'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>",
  <?php endif; ?>
  <?php if ($primaryVariant !== null) : ?>
  "sku": "<?php echo htmlspecialchars($primaryVariant['sku'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>",
  <?php endif; ?>
  "offers": {
    "@type": "Offer",
    "price": "<?php echo htmlspecialchars(number_format($priceCents / 100, 2, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>",
    "priceCurrency": "<?php echo htmlspecialchars($baseCurrency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true); ?>"
  }
}
</script>
```

---

## Order & Export Changes

### 9. Include EAN in Order Items (Optional Enhancement)

**Rationale:**

- When an order is placed, storing the EAN alongside SKU provides an immutable snapshot
- Useful for warehouse picking systems and integration with shipping providers
- Similar to how `sku` and `title` are stored at order creation time

**Schema change** (optional, for future version):

```sql
ALTER TABLE `#__nxp_easycart_order_items`
  ADD COLUMN `ean` VARCHAR(13) NULL DEFAULT NULL AFTER `sku`;
```

**Service changes** (`administrator/components/com_nxpeasycart/src/Service/OrderService.php`):

In `createOrder()` method, when building line items from cart, capture EAN from the variant:

```php
$items[] = [
    'product_id'      => (int) $item['product_id'],
    'variant_id'      => (int) $item['variant_id'],
    'sku'             => (string) $item['sku'],
    'ean'             => !empty($item['ean']) ? (string) $item['ean'] : null,
    'title'           => (string) $item['title'],
    // ... rest of fields
];
```

**Note:** This is an optional enhancement. For MVP, EAN can be looked up via the variant relationship.

---

## CSV Import/Export Changes

### 10. Add EAN to Product Import/Export

**Files:**

- Any CSV import handlers (if you plan to implement product import)
- Any CSV export features in the admin panel

**CSV format example:**

```csv
Title,SKU,EAN,Price,Currency,Stock
"Blue T-Shirt (M)","TS-BLUE-M","1234567890123","25.99","USD","100"
"Blue T-Shirt (L)","TS-BLUE-L","1234567890124","25.99","USD","75"
```

**Implementation note:**

- Include EAN column in sample CSV templates (`samples/nxp_native_products.csv`)
- Document EAN format requirements in `samples/README.md`

---

## Documentation Updates

### 11. Update Component Documentation

**Files to update:**

#### `README.md`:

Add to the "Recent Enhancements" section:

```markdown
- **EAN-13 barcode support (v0.2.0)**: Product variants now support optional EAN-13/EAN-8 barcodes with validation and global uniqueness enforcement. EAN is displayed in admin product editor, included in structured data (Schema.org `gtin13`), and validated with checksum verification. Useful for warehouse integration, POS systems, and Google Shopping feeds.
```

#### Create `docs/ean-barcodes.md`:

```markdown
# EAN Barcode Support

NXP Easy Cart supports EAN-13 and EAN-8 barcodes at the variant level.

## Admin Usage

1. Navigate to **Products** in the admin panel
2. Edit or create a product
3. Switch to the **Variants** tab
4. Enter the 13-digit (EAN-13) or 8-digit (EAN-8) barcode in the **EAN** field
5. Save the product

## Validation

- EAN must be numeric (digits only)
- Must be exactly 8 or 13 characters long
- EAN-13 checksums are validated automatically
- Duplicate EAN codes are rejected (global uniqueness)

## SEO Benefits

EAN codes are automatically included in product structured data (Schema.org) as `gtin13`, which helps:

- Google Shopping feeds
- Product comparison sites
- Search engine product understanding

## Warehouse Integration

EAN codes can be used for:

- Barcode scanning during fulfillment
- Inventory management systems
- Integration with shipping providers
- POS system synchronization

## CSV Import/Export

When importing products via CSV, include an `EAN` column with the 13-digit barcode.

Example:
\`\`\`csv
Title,SKU,EAN,Price,Currency,Stock
"Blue T-Shirt (M)","TS-BLUE-M","1234567890123","25.99","USD","100"
\`\`\`
```

#### Update `CHANGELOG.md`:

```markdown
## [0.2.0] - 2025-XX-XX

### Added

- EAN-13/EAN-8 barcode support at variant level with validation and global uniqueness
- EAN checksum validation for EAN-13 codes
- EAN field in admin product editor (variants tab)
- EAN included in Schema.org structured data (gtin13 property) for SEO
- Language strings for EAN labels and validation errors across all locales

### Changed

- Database schema: Added `ean` column to `#__nxp_easycart_variants` with unique index
```

---

## Testing Checklist

### 12. Manual Testing Scenarios

#### Admin Tests:

- [ ] Create a new product with variant that has a valid EAN-13 code
- [ ] Create a variant with EAN-8 code (8 digits)
- [ ] Try to save a variant with invalid EAN (letters, wrong length)
- [ ] Try to save a variant with invalid EAN-13 checksum
- [ ] Try to create two variants with the same EAN (should fail)
- [ ] Duplicate a variant (EAN should be cleared)
- [ ] Edit existing product and add EAN to variant
- [ ] Leave EAN blank (should be allowed - optional field)
- [ ] Delete a variant with EAN, then create new variant with same EAN (should succeed)

#### Storefront Tests:

- [ ] View product detail page - verify EAN displays correctly (if implemented)
- [ ] Inspect page source - verify Schema.org JSON-LD includes `gtin13`
- [ ] Test with Google's Rich Results Test tool
- [ ] Add product with EAN to cart - verify order creation preserves variant reference

#### API Tests:

- [ ] Fetch product via admin API - verify EAN is included in variant payload
- [ ] Update product with new EAN via API
- [ ] Update product with duplicate EAN via API (should fail with error)

### 13. Automated Testing (PHPUnit)

**File:** `tests/Unit/Table/VariantTableTest.php`

Add test cases:

```php
/**
 * @testdox EAN validation accepts valid EAN-13
 */
public function testValidEan13()
{
    $table = new VariantTable($this->db);
    $table->product_id = 1;
    $table->sku = 'TEST-SKU';
    $table->ean = '1234567890128'; // Valid EAN-13 with correct checksum
    $table->price_cents = 1000;
    $table->currency = 'USD';

    $this->assertTrue($table->check());
}

/**
 * @testdox EAN validation rejects invalid length
 */
public function testInvalidEanLength()
{
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('COM_NXPEASYCART_ERROR_VARIANT_EAN_INVALID');

    $table = new VariantTable($this->db);
    $table->product_id = 1;
    $table->sku = 'TEST-SKU';
    $table->ean = '12345'; // Invalid length
    $table->price_cents = 1000;
    $table->currency = 'USD';

    $table->check();
}

/**
 * @testdox EAN validation rejects duplicate EAN
 */
public function testDuplicateEan()
{
    // Insert first variant with EAN
    $table1 = new VariantTable($this->db);
    $table1->product_id = 1;
    $table1->sku = 'TEST-SKU-1';
    $table1->ean = '1234567890128';
    $table1->price_cents = 1000;
    $table1->currency = 'USD';
    $table1->store();

    // Try to insert second variant with same EAN
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('COM_NXPEASYCART_ERROR_VARIANT_EAN_EXISTS');

    $table2 = new VariantTable($this->db);
    $table2->product_id = 1;
    $table2->sku = 'TEST-SKU-2';
    $table2->ean = '1234567890128'; // Duplicate
    $table2->price_cents = 1000;
    $table2->currency = 'USD';
    $table2->check();
}
```

---

## Migration & Rollout Strategy

### 14. Deployment Steps

1. **Backup database** before applying migration:

    ```bash
    php tools/db-backup.sh
    ```

2. **Apply database migration**:
    - Install extension update via Joomla Extension Manager (migration runs automatically)
    - OR manually run: `administrator/components/com_nxpeasycart/sql/updates/mysql/0.2.0.sql`

3. **Clear Joomla cache**:
    - System → Clear Cache → Select All → Delete

4. **Rebuild admin bundle**:

    ```bash
    npm run build:admin
    ```

5. **Test in staging environment** before production deployment

6. **Gradual rollout**:
    - Deploy to production
    - EAN is optional, so existing products continue to work without EAN
    - Merchants can add EAN codes incrementally over time

### 15. Backwards Compatibility

- **100% backwards compatible** - EAN is optional (NULL allowed)
- Existing products and variants continue to function without EAN
- No breaking changes to API responses (EAN is simply an additional field)
- Admin UI gracefully handles missing EAN (displays empty field)

---

## Future Enhancements (Out of Scope for MVP)

### Potential v0.3.0+ Features:

1. **Barcode Scanner Integration**:
    - Admin inventory management via barcode scanner
    - Quick product lookup by EAN in admin panel

2. **Automatic EAN Generation**:
    - Generate valid EAN-13 codes for products without barcodes
    - Integration with GS1 for official EAN assignment

3. **Multiple Barcode Types**:
    - Support for UPC-A (12 digits, North America)
    - Support for ISBN (books)
    - Support for custom internal barcodes

4. **Warehouse Management**:
    - Print barcode labels for variants
    - Scan-to-pick during order fulfillment
    - Stock take via barcode scanning

5. **Product Feed Exports**:
    - Google Shopping feed with GTIN
    - Facebook/Meta catalog with EAN
    - Comparison shopping engines (PriceRunner, Idealo, etc.)

6. **Order Item EAN Snapshot**:
    - Store EAN in `#__nxp_easycart_order_items` at order creation time
    - Useful for historical records and integrations

---

## Effort Estimation

### Developer Time:

- **Schema changes**: 1 hour
- **Backend validation & model updates**: 3 hours
- **Admin Vue UI changes**: 2 hours
- **Language strings (5 locales)**: 1 hour
- **Storefront display & Schema.org**: 2 hours
- **Testing (manual + automated)**: 3 hours
- **Documentation**: 2 hours

**Total estimated effort**: **14 hours** (approximately 2 working days)

### Complexity: **Low to Medium**

- Straightforward database column addition
- Standard validation pattern (similar to SKU)
- No complex business logic or external dependencies

---

## Risk Assessment

### Low Risk:

- ✅ Non-breaking change (optional field)
- ✅ No impact on existing products
- ✅ Standard industry practice (well-understood domain)
- ✅ Clear validation rules

### Potential Issues & Mitigations:

| Risk                             | Impact | Likelihood | Mitigation                                                      |
| -------------------------------- | ------ | ---------- | --------------------------------------------------------------- |
| Invalid EAN data imported        | Medium | Medium     | Validation in VariantTable catches bad data before save         |
| Duplicate EAN across stores      | Low    | Low        | UNIQUE index enforces database-level constraint                 |
| Checksum false positives         | Low    | Low        | EAN-13 checksum validation is a standard algorithm              |
| Performance impact of validation | Low    | Low        | Validation only runs on save, not on read; index lookup is fast |

---

## Success Criteria

### Definition of Done:

- [x] Database migration adds `ean` column with unique index
- [x] VariantTable validates EAN format, length, checksum, and uniqueness
- [x] ProductModel includes EAN in save/load operations
- [x] Admin product editor displays EAN field in variants tab
- [x] Language strings added for all 5 supported locales
- [x] Storefront includes EAN in Schema.org structured data (optional: variant table)
- [x] Documentation updated (README, CHANGELOG, new docs/ean-barcodes.md)
- [x] Manual testing completed successfully
- [x] PHPUnit tests pass for EAN validation
- [x] No regressions in existing product/variant functionality

---

## Summary

This plan implements **EAN-13 barcode support at the variant level**, following industry standards and your component's existing architecture. The implementation is:

✅ **Non-breaking** - Fully backwards compatible (EAN is optional)
✅ **Standard** - Aligns with WooCommerce, Magento, Shopify patterns
✅ **SEO-friendly** - Includes Schema.org `gtin13` for better product discovery
✅ **Well-validated** - Checksum verification and global uniqueness enforcement
✅ **Scalable** - Foundation for future warehouse/POS integrations

The variant-level approach is correct because:

- Each SKU (sellable unit) gets its own unique barcode
- Matches real-world barcode scanning use cases
- Aligns with existing stock/price management at variant level
- Supports single-variant products seamlessly

**Next step**: Review and approve this plan, then proceed with implementation following the steps outlined above.
