# Variant Images Implementation Plan

## Overview

Add per-variant images support so that selecting a different variant (e.g., colour) on the storefront updates the displayed product image. Uses Option A: direct `images` JSON field on the variants table.

## Current State

### Database Schema

**Products table** (`#__nxp_easycart_products`):
```sql
`images` JSON NULL  -- Product-level images array
`source_images` JSON NULL  -- Original URLs from import
```

**Variants table** (`#__nxp_easycart_variants`):
```sql
`original_images` JSON NULL  -- Import source URLs (not used for display)
-- No display images field exists
```

### Current Behaviour

- Product images are shared across all variants
- Storefront displays `product.images[0]` regardless of selected variant
- Admin UI only allows image upload at product level
- Imports store variant image URLs in `original_images` but they're not displayed

## Target State

- Each variant can have its own `images` array
- Storefront swaps images when variant selection changes
- Admin UI allows image upload per variant
- Imports populate variant images from source data
- Fallback to product images when variant has no images

---

## Phase 1: Database Schema

### 1.1 Add `images` column to variants table

**File:** `administrator/components/com_nxpeasycart/sql/install.mysql.utf8.sql`

Add after `options` column:
```sql
`images` JSON NULL DEFAULT NULL COMMENT 'Variant-specific images array',
```

### 1.2 Create migration script

**File:** `administrator/components/com_nxpeasycart/sql/updates/mysql/0.3.1.sql`

```sql
-- NXP Easy Cart 0.3.1 - Variant Images
-- Add images column to variants table for per-variant image support

ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `images` JSON NULL DEFAULT NULL COMMENT 'Variant-specific images array' AFTER `options`;
```

### 1.3 Update version in manifest

**File:** `nxpeasycart.xml`

Update version to `0.3.1`.

---

## Phase 2: Backend API Changes

### 2.1 Update ProductsController to handle variant images

**File:** `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php`

- Accept `images` in variant payload during create/update
- Validate images array structure (array of strings/objects)
- Store in database

### 2.2 Update product fetch to include variant images

**File:** `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php`

- Include `images` in variant data when fetching products
- Ensure JSON decode of variant images

### 2.3 Add variant image upload endpoint (optional)

If we want direct upload to variant (vs product-level upload):

**File:** `administrator/components/com_nxpeasycart/src/Controller/Api/ProductsController.php`

- Add `uploadVariantImage` task
- Store in `/media/com_nxpeasycart/images/variants/{variant_id}/`
- Return image path for frontend

**Alternative:** Reuse existing product image upload, let frontend associate to variant.

---

## Phase 3: Admin UI Changes

### 3.1 Update VariantCard component

**File:** `media/com_nxpeasycart/src/app/components/VariantCard.vue` (or within ProductPanel.vue)

Add image management section to each variant card:

```vue
<div class="nxp-ec-variant-images">
  <label>Variant Images</label>
  <div class="nxp-ec-variant-images__list">
    <!-- Show existing images with remove button -->
    <div v-for="(img, idx) in variant.images" :key="idx" class="nxp-ec-variant-image">
      <img :src="img" :alt="'Variant image ' + (idx + 1)" />
      <button @click="removeVariantImage(variantIndex, idx)">×</button>
    </div>
    <!-- Add image button -->
    <button @click="openVariantImageUpload(variantIndex)">+ Add Image</button>
  </div>
  <small class="nxp-ec-hint">Leave empty to use product images</small>
</div>
```

### 3.2 Update ProductPanel variant handling

**File:** `media/com_nxpeasycart/src/app/components/ProductPanel.vue`

- Add `images: []` to variant data structure
- Handle variant image upload/remove
- Include variant images in save payload

### 3.3 Update useProducts composable

**File:** `media/com_nxpeasycart/src/app/composables/useProducts.js`

- Ensure variant images are included in API payloads
- Parse variant images from API responses

### 3.4 Add CSS for variant image UI

**File:** `media/com_nxpeasycart/src/admin.css`

```css
.nxp-ec-variant-images { margin-top: 0.75rem; }
.nxp-ec-variant-images__list { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.nxp-ec-variant-image { position: relative; width: 60px; height: 60px; }
.nxp-ec-variant-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
.nxp-ec-variant-image button { position: absolute; top: -6px; right: -6px; /* ... */ }
```

---

## Phase 4: Storefront Changes

### 4.1 Update product detail page data

**File:** `components/com_nxpeasycart/src/View/Product/HtmlView.php`

- Include variant images in product data passed to Vue island
- Structure: `variants: [{ id, sku, images: [...], ... }]`

### 4.2 Update ProductDetail Vue island

**File:** `media/com_nxpeasycart/src/site/islands/ProductDetail.vue` (or equivalent)

Add reactive image switching:

```javascript
const selectedVariant = ref(null);

const displayImages = computed(() => {
  if (selectedVariant.value?.images?.length) {
    return selectedVariant.value.images;
  }
  return product.images || [];
});

const onVariantChange = (variant) => {
  selectedVariant.value = variant;
};
```

Update template:
```vue
<div class="nxp-ec-product-gallery">
  <img :src="displayImages[currentImageIndex]" :alt="product.title" />
  <!-- Thumbnails -->
  <div class="nxp-ec-product-thumbnails">
    <img
      v-for="(img, idx) in displayImages"
      :key="idx"
      :src="img"
      :class="{ active: idx === currentImageIndex }"
      @click="currentImageIndex = idx"
    />
  </div>
</div>
```

### 4.3 Update variant selector component

**File:** `media/com_nxpeasycart/src/site/islands/ProductDetail.vue`

- Emit variant change event when user selects different option
- Reset image index to 0 when variant changes (show first variant image)

### 4.4 Add smooth image transition

CSS transition for image swap:

```css
.nxp-ec-product-gallery img {
  transition: opacity 0.2s ease;
}
.nxp-ec-product-gallery.switching img {
  opacity: 0.5;
}
```

---

## Phase 5: Import System Updates

### 5.1 Update WooCommerce adapter

**File:** `administrator/components/com_nxpeasycart/src/Service/Import/Adapter/WoocommerceAdapter.php`

WooCommerce exports have per-row `Images` column. For variations:

```php
// In normalizeRow() for variation type:
$normalized['variant']['images'] = $this->parseImages($this->getValue($row, 'Images', ''));
```

Note: Parent "variable" rows have the main product images. Variation rows may have their own images or be empty (inherits from parent).

### 5.2 Update Shopify adapter

**File:** `administrator/components/com_nxpeasycart/src/Service/Import/Adapter/ShopifyAdapter.php`

Shopify has `Image Src` per row:

```php
$normalized['variant']['images'] = $this->parseImages($this->getValue($row, 'Image Src', ''));
```

### 5.3 Update ImportProcessor

**File:** `administrator/components/com_nxpeasycart/src/Service/Import/ImportProcessor.php`

In `saveVariant()`:

```php
$variant = (object) [
    // ... existing fields ...
    'images' => !empty($variantData['images'])
        ? json_encode($variantData['images'], JSON_UNESCAPED_SLASHES)
        : null,
];
```

### 5.4 Update Native adapter and ExportProcessor

Ensure native format includes variant images for round-trip backup/restore.

---

## Phase 6: Export System Updates

### 6.1 Update ExportProcessor

**File:** `administrator/components/com_nxpeasycart/src/Service/Import/ExportProcessor.php`

Include variant images in export:

```php
// In buildVariantRow():
$row['Variant Images'] = implode(',', json_decode($variant->images ?? '[]', true));
```

### 6.2 Update Native adapter export mapping

Ensure `Variant Images` column is included in native format exports.

---

## Phase 7: Documentation

### 7.1 Create variant-images.md

**File:** `docs/variant-images.md`

Document:
- Feature overview
- Admin UI usage
- API structure
- Import/export behaviour
- Storefront behaviour

### 7.2 Update docs/README.md

Add entry for variant-images.md.

### 7.3 Update main README.md

Add to Recent Enhancements section.

---

## Testing Checklist

### Admin UI
- [ ] Can add images to a variant
- [ ] Can remove images from a variant
- [ ] Can reorder variant images (drag & drop if implemented)
- [ ] Variant without images shows "uses product images" hint
- [ ] Save product preserves variant images
- [ ] Edit product loads variant images correctly

### Storefront
- [ ] Product page shows product images by default
- [ ] Selecting variant with images swaps to variant images
- [ ] Selecting variant without images keeps product images
- [ ] Image gallery thumbnails update on variant change
- [ ] Image transition is smooth (no flash)

### Import
- [ ] WooCommerce import captures variation images
- [ ] Shopify import captures variant images
- [ ] Variants without images in CSV result in null (not empty array)
- [ ] Parent product images are not duplicated to variants

### Export
- [ ] Native export includes variant images
- [ ] Re-importing exported data preserves variant images

### API
- [ ] GET product returns variant images
- [ ] POST/PUT product accepts variant images
- [ ] Invalid images array is rejected with validation error

---

## Migration Notes

### Existing Installations

- Running the 0.3.1 update adds the `images` column
- Existing variants will have `images = NULL` (fallback to product images)
- No data migration needed - existing behaviour preserved

### Import Data with `original_images`

The existing `original_images` field stores imported source URLs but isn't used for display. Options:

1. **Keep separate**: `original_images` for reference, `images` for display
2. **Migrate on import**: Copy `original_images` to `images` during import

Recommendation: Keep separate. `original_images` is a source reference; `images` is the curated display set.

---

## Estimated Scope

| Phase | Files Changed | Complexity |
|-------|---------------|------------|
| 1. Database | 3 | Low |
| 2. Backend API | 1-2 | Low |
| 3. Admin UI | 3-4 | Medium |
| 4. Storefront | 2-3 | Medium |
| 5. Import | 3-4 | Low |
| 6. Export | 1-2 | Low |
| 7. Documentation | 3 | Low |

**Total:** ~15-20 files, mostly straightforward changes.

---

## Open Questions

1. **Image upload workflow**: Upload to variant directly, or select from product image library?
   - Recommendation: Direct upload to variant (simpler UX)

2. **Image limit per variant**: Should we cap variant images (e.g., max 5)?
   - Recommendation: No hard limit, but UI should handle gracefully

3. **Thumbnail generation**: Create thumbnails for variant images?
   - Recommendation: Use same approach as product images (if thumbnails exist)

4. **Gallery lightbox**: Should variant images work with lightbox?
   - Recommendation: Yes, same behaviour as product images

---

## Version

This plan targets version **0.3.1** of NXP Easy Cart.
