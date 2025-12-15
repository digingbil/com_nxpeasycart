# EAN Barcodes

NXP Easy Cart supports European Article Number (EAN) barcodes at the variant level. This allows merchants to associate standard product barcodes with their inventory for scanning, inventory management, and SEO benefits.

## Supported Formats

| Format | Length | Example |
|--------|--------|---------|
| EAN-8  | 8 digits | `96385074` |
| EAN-13 | 13 digits | `5901234123457` |

## Validation

All EAN codes are validated using the standard GS1 checksum algorithm:

1. **Format check**: Must contain only digits (0-9)
2. **Length check**: Must be exactly 8 or 13 digits
3. **Check digit validation**: The last digit is verified against the calculated checksum

Invalid EAN codes will be rejected with a clear error message indicating the specific validation failure.

## Admin Panel Usage

In the Product Editor, each variant card includes an optional EAN field:

- Located below the SKU field
- Placeholder text indicates expected format ("8 or 13 digits")
- HTML5 pattern validation provides client-side feedback
- Backend validation ensures data integrity

## API

EAN is included in variant data when reading products:

```json
{
  "variants": [
    {
      "id": 1,
      "sku": "WIDGET-001",
      "ean": "5901234123457",
      "price_cents": 1999,
      "currency": "EUR",
      "stock": 50
    }
  ]
}
```

When creating or updating products via the API, include `ean` in the variant payload:

```json
{
  "variants": [
    {
      "sku": "WIDGET-001",
      "ean": "5901234123457",
      "price": "19.99",
      "currency": "EUR",
      "stock": 50
    }
  ]
}
```

## Schema.org Integration

When a product is displayed on the storefront, EAN codes are automatically included in the Schema.org structured data:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Widget Pro",
  "gtin13": "5901234123457",
  "offers": {
    "@type": "Offer",
    "sku": "WIDGET-001",
    "gtin13": "5901234123457",
    "price": "19.99",
    "priceCurrency": "EUR"
  }
}
```

- EAN-13 codes are expressed as `gtin13`
- EAN-8 codes are expressed as `gtin8`
- For single-variant products, the GTIN appears at both product and offer level
- For multi-variant products, each offer includes its own GTIN

This structured data helps search engines understand your products and can improve visibility in shopping results.

## Database Schema

The EAN field is stored in the `#__nxp_easycart_variants` table:

```sql
`ean` VARCHAR(13) NULL DEFAULT NULL
```

An index is created for efficient EAN lookups:

```sql
KEY `idx_nxp_variants_ean` (`ean`)
```

## Migration

Existing installations will have the EAN column added automatically when updating to version 0.1.17 or later. The migration script adds:

1. The `ean` column to the variants table
2. An index for EAN lookups

No data migration is required as EAN is optional.
