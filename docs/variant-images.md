# Variant Images

Per-variant image support for product variants in NXP Easy Cart. When a customer selects a variant (e.g., a different colour), the product gallery updates to show that variant's specific images.

---

## Overview

Products can have multiple variants (e.g., sizes, colours). Each variant can now have its own set of images that override the product-level images when selected on the storefront.

**Key Features:**

- Per-variant image assignment in admin UI
- Automatic image switching on variant selection in storefront
- Fallback to product images when variant has no images
- Full import/export support
- Smooth CSS transitions during image changes

---

## Database Schema

### Variants Table Addition

```sql
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `images` JSON NULL DEFAULT NULL COMMENT 'Variant-specific images array (overrides product images)' AFTER `options`;
```

**Column Details:**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `images` | JSON | NULL | Array of image URLs for this variant |

**Values:**

- `NULL` - Variant inherits product images (default)
- `[]` (empty array) - Treated as NULL, inherits product images
- `["path/to/image1.jpg", "path/to/image2.jpg"]` - Variant-specific images

---

## Admin UI

### Managing Variant Images

In the Product Editor, each variant card now includes an **Images** section:

1. Open a product for editing
2. Expand a variant card
3. Find the "Variant Images" section at the bottom
4. Click "Add Image" to open the Joomla Media Manager
5. Select an image to add it to the variant
6. Click the X button on any image to remove it

**Notes:**

- Variants without images display "Leave empty to use product images"
- Multiple images per variant are supported
- Images are displayed as thumbnails with remove buttons

### Image Inheritance

- If a variant has no images (`images = null`), it inherits the product's images
- If a variant has images, they completely replace the product images for that variant
- This allows you to have some variants with custom images and others using product defaults

---

## Storefront Behaviour

### Automatic Image Switching

When a customer selects a variant from the dropdown:

1. The system checks if the selected variant has images
2. If yes: Gallery updates to show variant images
3. If no: Gallery shows product images (default)
4. Thumbnail strip rebuilds dynamically
5. Main image resets to first image of the new set
6. Lightbox works with the current image set

### CSS Transitions

A subtle opacity transition provides visual feedback during image switching:

```css
.nxp-ec-product__figure img {
  transition: opacity 0.2s ease;
}
.nxp-ec-gallery--switching .nxp-ec-product__figure img {
  opacity: 0.6;
}
```

---

## API Structure

### Product API Response

The `/api/products/{id}` endpoint includes variant images:

```json
{
  "product": {
    "id": 123,
    "title": "T-Shirt",
    "images": ["images/products/tshirt-main.jpg"],
    "variants": [
      {
        "id": 456,
        "sku": "TSHIRT-RED-M",
        "images": ["images/products/tshirt-red.jpg"],
        "options": [
          {"name": "Color", "value": "Red"},
          {"name": "Size", "value": "M"}
        ]
      },
      {
        "id": 457,
        "sku": "TSHIRT-BLUE-M",
        "images": null,
        "options": [
          {"name": "Color", "value": "Blue"},
          {"name": "Size", "value": "M"}
        ]
      }
    ]
  }
}
```

In this example:
- The Red variant shows its own image (`tshirt-red.jpg`)
- The Blue variant shows the product image (`tshirt-main.jpg`) because `images: null`

### Saving Variant Images

When saving a product, include variant images in the payload:

```json
{
  "variants": [
    {
      "sku": "TSHIRT-RED-M",
      "price_cents": 2999,
      "images": ["images/products/tshirt-red.jpg", "images/products/tshirt-red-back.jpg"]
    },
    {
      "sku": "TSHIRT-BLUE-M",
      "price_cents": 2999,
      "images": null
    }
  ]
}
```

---

## Import Support

### WooCommerce Import

For WooCommerce variation rows (Type = "variation"), the Images column is captured as variant images:

- Parent "variable" rows: Images go to product-level images
- Variation rows: Images go to variant-level images

### Shopify Import

The `Variant Image` column is imported as variant images:

```csv
Handle,Title,Variant SKU,Variant Image
tshirt,T-Shirt,TSHIRT-RED,https://example.com/tshirt-red.jpg
tshirt,T-Shirt,TSHIRT-BLUE,
```

The Red variant gets its image; Blue inherits from product.

### Native Format Import

The `variant_images` column contains comma-separated image URLs:

```csv
product_id,sku,variant_images
1,TSHIRT-RED,"images/tshirt-red.jpg,images/tshirt-red-back.jpg"
1,TSHIRT-BLUE,""
```

---

## Export Support

### Native Format Export

Exports include the `variant_images` column:

```csv
product_id,product_slug,...,variant_images
1,t-shirt,...,"images/tshirt-red.jpg,images/tshirt-red-back.jpg"
```

### Shopify Format Export

The `Variant Image` column contains the first variant image (or first original image if no variant images):

```csv
Handle,Variant SKU,Image Src,Variant Image
tshirt,TSHIRT-RED,images/tshirt-main.jpg,images/tshirt-red.jpg
```

---

## Migration

### Existing Installations

Run the 0.3.1 update SQL to add the column:

```sql
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `images` JSON NULL DEFAULT NULL COMMENT 'Variant-specific images array (overrides product images)' AFTER `options`;
```

**No data migration required** - existing variants will have `images = NULL`, which means they inherit product images (existing behaviour).

### Using original_images

The existing `original_images` column stores raw URLs from imports for reference. The new `images` column is for curated display images. They serve different purposes:

- `original_images`: Source reference from import (not displayed)
- `images`: Active display images for storefront

---

## Testing Checklist

### Admin UI

- [ ] Can add images to a variant
- [ ] Can remove images from a variant
- [ ] Variant without images shows inheritance hint
- [ ] Save product preserves variant images
- [ ] Edit product loads variant images correctly
- [ ] Status toggle preserves variant images

### Storefront

- [ ] Product page shows product images by default
- [ ] Selecting variant with images swaps to variant images
- [ ] Selecting variant without images shows product images
- [ ] Thumbnail strip updates on variant change
- [ ] Image transition is smooth

### Import

- [ ] WooCommerce import captures variation images
- [ ] Shopify import captures variant images
- [ ] Native format import preserves variant images
- [ ] Variants without images in CSV result in null

### Export

- [ ] Native export includes variant_images column
- [ ] Shopify export includes Variant Image
- [ ] Re-importing exported data preserves variant images

---

## Version History

| Version | Changes |
|---------|---------|
| 0.3.1 | Initial variant images support |

---

**Last Updated**: 2025-12-16
