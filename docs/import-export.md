# Import/Export

NXP Easy Cart provides a comprehensive import/export system for migrating product catalogues from other e-commerce platforms or creating backups of your data.

## Supported Platforms

### Import

| Platform | Format | Auto-Detection |
|----------|--------|----------------|
| WooCommerce | CSV | Yes |
| Shopify | CSV | Yes |
| VirtueMart | CSV | Yes |
| HikaShop | CSV | Yes |
| Native | CSV | Yes |

### Export

| Format | Description |
|--------|-------------|
| Native | NXP Easy Cart format (recommended for backups) |
| WooCommerce | Compatible with WooCommerce import |

## Accessing Import/Export

Navigate to **Components → NXP Easy Cart → Import/Export** tab in the admin panel.

## Import Workflow

### 1. Upload CSV File

- Drag & drop or click to select your CSV file
- Maximum file size: 50MB (configurable)
- UTF-8 encoding required (BOM supported)

### 2. Platform Detection

The system automatically detects the source platform by analysing CSV headers:

- **WooCommerce**: Detected by `ID`, `Type`, `SKU`, `Name`, `Published`, `Regular price`
- **Shopify**: Detected by `Handle`, `Title`, `Vendor`, `Type`, `Tags`
- **VirtueMart**: Detected by `product_sku`, `product_name`, `product_price`
- **HikaShop**: Detected by `product_code`, `product_name`, `product_price`

If auto-detection fails, you can manually select the platform.

### 3. Preview & Confirm

Before importing, you'll see:

- Detected platform
- Number of rows to import
- CSV headers and sample data
- Import options

### 4. Import Options

| Option | Description | Default |
|--------|-------------|---------|
| Create categories | Auto-create categories from CSV | Yes |
| Set products active | Make imported products visible | No |
| Store image URLs | Save original image URLs for reference | Yes |

### 5. Processing

Import runs synchronously for reliability. Progress is displayed in real-time:

- Total rows processed
- Products created
- Variants created
- Categories created
- Skipped rows (with reasons)
- Errors and warnings

## WooCommerce Import Details

### Variant Grouping

WooCommerce exports products as multiple rows:

| Type | Description | Import Behaviour |
|------|-------------|-----------------|
| `variable` | Parent product container | Provides product title, description, images, categories |
| `variation` | Individual variant | Creates variant with SKU, price, options |
| `simple` | Single product | Creates product with one variant |

Variations are grouped by the `Parent` column (parent SKU), ensuring all colour/size variations belong to the same product.

### Field Mapping

| WooCommerce Column | NXP Easy Cart Field |
|--------------------|---------------------|
| Name | Product title |
| Short description | Short description |
| Description | Long description |
| SKU | Variant SKU |
| Regular price | Variant price |
| Sale price | Sale price |
| Stock | Stock quantity |
| Categories | Product categories |
| Images | Product images (URLs) |
| Attribute 1-3 name/value | Variant options |
| Weight (kg) | Variant weight |

## Shopify Import Details

### Handle-Based Grouping

Shopify uses the `Handle` column to group variants. All rows with the same handle become variants of one product.

### Field Mapping

| Shopify Column | NXP Easy Cart Field |
|----------------|---------------------|
| Title | Product title |
| Body (HTML) | Long description |
| Variant SKU | Variant SKU |
| Variant Price | Variant price |
| Variant Compare At Price | Sale price |
| Variant Inventory Qty | Stock |
| Tags | Categories |
| Image Src | Product images |
| Option1/2/3 Name/Value | Variant options |

## Export Workflow

### 1. Select Format

Choose your export format:

- **Native**: Full data export for backups
- **WooCommerce**: Compatible format for WooCommerce import

### 2. Generate Export

Click "Start Export" to generate the CSV file. Progress is displayed during generation.

### 3. Download

Once complete, click "Download" to save the CSV file. Filename format: `nxp_easycart_export_{format}_{date}.csv`

## Import Tracking

All imported data is tagged with source information:

- `imported_from`: Platform identifier (e.g., "woocommerce")
- `original_id`: Original ID from source platform

This allows you to:

1. Identify imported vs manually created products
2. Clean up test imports
3. Re-import with updates

### Cleanup Queries

Delete all imported products:

```sql
DELETE FROM #__nxp_easycart_products WHERE imported_from IS NOT NULL;
```

Delete products from specific platform:

```sql
DELETE FROM #__nxp_easycart_products WHERE imported_from = 'woocommerce';
DELETE FROM #__nxp_easycart_variants WHERE imported_from = 'woocommerce';
DELETE FROM #__nxp_easycart_categories WHERE imported_from = 'woocommerce';
```

## Duplicate Detection

The system calculates a SHA-256 hash of uploaded files to prevent accidental re-imports. If you need to re-import the same file:

1. Delete the previous import job from the jobs list, OR
2. Modify the CSV slightly (the hash will change)

## File Storage

### Import Files

Uploaded CSV files are stored in:
```
/media/com_nxpeasycart/imports/{user_id}/{timestamp}_{random}.csv
```

Files are protected by `.htaccess` to prevent direct web access.

### Export Files

Generated export files are stored in:
```
/media/com_nxpeasycart/exports/export_{job_id}_{timestamp}.csv
```

## API Endpoints

### Import

| Endpoint | Method | Description |
|----------|--------|-------------|
| `?task=api.import.upload` | POST | Upload CSV file |
| `?task=api.import.start` | POST | Start import job |
| `?task=api.import.progress` | GET | Get job progress |
| `?task=api.import.platforms` | GET | List supported platforms |

### Export

| Endpoint | Method | Description |
|----------|--------|-------------|
| `?task=api.export.start` | POST | Start export job |
| `?task=api.export.progress` | GET | Get job progress |
| `?task=api.export.download` | GET | Download export file |
| `?task=api.export.platforms` | GET | List export formats |

## Troubleshooting

### "File has already been imported"

The same CSV file was previously imported. Delete the old job or modify the file.

### "Invalid file type"

Ensure the file is a valid CSV with UTF-8 encoding. Check for:
- Correct file extension (.csv)
- UTF-8 encoding (not ANSI or other)
- Valid CSV structure

### Missing Descriptions

For WooCommerce imports, descriptions are stored on the parent "variable" row, not on individual variations. Ensure your export includes the parent products.

### Products Not Grouped

For WooCommerce, ensure the `Parent` column contains the parent product's SKU for all variations. For Shopify, ensure all variants share the same `Handle`.

## Database Schema

### Import Jobs Table

```sql
CREATE TABLE `#__nxp_easycart_import_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_type` ENUM('import', 'export') NOT NULL,
  `platform` VARCHAR(50) NULL,
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_variants` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_categories` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `errors` JSON NULL,
  `warnings` JSON NULL,
  `file_path` VARCHAR(500) NULL,
  `original_filename` VARCHAR(255) NULL,
  `file_hash` CHAR(64) NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`)
);
```

## Best Practices

1. **Backup First**: Always export your current catalogue before importing
2. **Test Import**: Use "Set products active: No" for initial imports to review before publishing
3. **Small Batches**: For large catalogues (10,000+ products), consider splitting into multiple CSV files
4. **Clean Data**: Review and clean CSV data before import to minimise errors
5. **Check Categories**: Verify category names match your intended structure
