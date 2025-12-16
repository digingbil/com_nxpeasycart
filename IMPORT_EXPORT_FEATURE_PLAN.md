# Import/Export Feature Plan (v0.3.0)

## Overview

The Import/Export feature enables merchants to migrate product catalogues from major e-commerce platforms (VirtueMart, WooCommerce, Shopify, HikaShop) and maintain portable backups of their NXP Easy Cart product data. The implementation follows NXP Easy Cart's guiding principles: security, reliability, simplicity, and speed to first sale.

**Key objectives:**

- Zero-friction migration from competing platforms
- Resilient parsing that never fails on invalid/missing data
- Variant-aware mapping (SKU, pricing, EAN, stock, weight, sale pricing live on variants)
- Real-time progress feedback for large imports
- Clean, dedicated workspace in the admin SPA
- Full product export capability for backup/portability
- Support for both physical and digital product types

---

## Architecture

### 1. Database schema changes

> **Note:** The `ean` column already exists in `#__nxp_easycart_variants` (added in v0.1.17).
> Variants also already support sale pricing (`sale_price_cents`, `sale_start`, `sale_end`).

**Add to `#__nxp_easycart_variants`:**

```sql
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `imported_from` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Source platform: virtuemart, woocommerce, shopify, hikashop, native',
  ADD COLUMN `original_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Original product/variant ID from source platform',
  ADD COLUMN `original_images` JSON NULL DEFAULT NULL COMMENT 'JSON array of original image URLs (not downloaded)',
  ADD INDEX `idx_nxp_variants_imported_from` (`imported_from`);
```

**Add to `#__nxp_easycart_products`:**

```sql
ALTER TABLE `#__nxp_easycart_products`
  ADD COLUMN `imported_from` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Source platform',
  ADD COLUMN `original_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Original product ID from source platform',
  ADD INDEX `idx_nxp_products_imported_from` (`imported_from`);
```

**New table for import/export jobs:**

```sql
CREATE TABLE `#__nxp_easycart_import_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `job_type` ENUM('import', 'export') NOT NULL DEFAULT 'import',
  `platform` VARCHAR(50) NULL DEFAULT NULL COMMENT 'virtuemart, woocommerce, shopify, hikashop, native',
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_processed_row` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'For resume capability',
  `imported_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_variants` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_categories` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `errors` JSON NULL DEFAULT NULL COMMENT 'JSON array of error messages',
  `warnings` JSON NULL DEFAULT NULL COMMENT 'JSON array of warning messages',
  `file_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Path to uploaded CSV or generated export',
  `file_hash` CHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 hash to detect re-uploads',
  `mapping` JSON NULL DEFAULT NULL COMMENT 'JSON column mapping configuration',
  `options` JSON NULL DEFAULT NULL COMMENT 'JSON import options (create categories, update existing, etc)',
  `created_by` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL DEFAULT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  INDEX `idx_nxp_import_jobs_status` (`status`),
  INDEX `idx_nxp_import_jobs_created_by` (`created_by`),
  INDEX `idx_nxp_import_jobs_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Design notes:**
> - `last_processed_row` enables job resumption after timeout/crash
> - `file_hash` prevents duplicate imports of same file
> - `imported_categories` tracks auto-created categories
> - JSON columns (errors, warnings, mapping, options) match existing schema patterns

### 2. Service layer

**New services under `Administrator\Service\Import`:**

#### `ImportExportService`

Core orchestrator managing job lifecycle:

- `createImportJob(array $options): int` - Creates job record, returns job ID
- `createExportJob(array $options): int` - Creates export job
- `processImportJob(int $jobId): void` - Processes import in batches
- `processExportJob(int $jobId): void` - Generates CSV export
- `getJobStatus(int $jobId): array` - Returns progress data
- `cancelJob(int $jobId): void` - Cancels running job
- `resumeJob(int $jobId): void` - Resumes from `last_processed_row`
- `checkDuplicateFile(string $filePath): ?int` - Returns existing job ID if file hash matches

#### `PlatformAdapterFactory`

Factory pattern for platform adapters:

- `getAdapter(string $platform): PlatformAdapterInterface` - Returns appropriate adapter
- `detectPlatform(array $headers): ?string` - Auto-detects platform from CSV headers
- Supports: `virtuemart`, `woocommerce`, `shopify`, `hikashop`, `native`

#### Platform adapters (implement `PlatformAdapterInterface`)

**Interface:**

```php
namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

interface PlatformAdapterInterface {
    /** Platform identifier (e.g., 'shopify', 'woocommerce') */
    public function getName(): string;

    /** Human-readable display name (e.g., 'Shopify', 'WooCommerce') */
    public function getDisplayName(): string;

    /** Default column mappings for this platform */
    public function getDefaultMapping(): array;

    /** Headers that uniquely identify this platform's CSV export */
    public function getSignatureHeaders(): array;

    /** Transform source row to normalized format */
    public function normalizeRow(array $row, array $mapping): array;

    /** Validate normalized row, returns array of error messages */
    public function validate(array $normalizedRow): array;

    /** True for multi-row variant formats (Shopify), false for single-row */
    public function shouldGroupVariants(): bool;

    /** Column used for grouping variants (e.g., 'Handle' for Shopify) */
    public function getGroupingColumn(): ?string;

    /** Weight unit used by this platform ('g', 'kg', 'lb', 'oz') */
    public function getWeightUnit(): string;
}
```

**Implementations:**

- `VirtueMartAdapter` - Handles pipe-separated categories, VM-specific fields
- `WooCommerceAdapter` - Maps WC attributes to options, handles type column
- `ShopifyAdapter` - Groups multi-row variants by Handle, maps options 1-3
- `HikaShopAdapter` - Maps characteristic columns to variant options
- `NativeAdapter` - Direct mapping for our own export format

**Adapter responsibilities:**

- Provide default column mappings (customizable by user)
- Normalize source data to common format
- Handle platform-specific quirks (delimiters, multi-row variants, encoding)
- Apply intelligent fallbacks for missing/invalid data
- Never throw exceptions - always return warnings/skip gracefully

#### `ImportProcessor`

Chunked processing engine:

- `processChunk(int $jobId, int $offset, int $limit): array` - Processes batch
- `saveProduct(array $normalizedData): int` - Transactional product+variants save
- `handleDuplicateSKU(string $sku): string` - Generates unique SKU with suffix
- `resolveCategories(array $categories): array` - Creates missing categories
- `fallbackValue(mixed $value, string $field): mixed` - Applies sane defaults
- Wraps each product save in a DB transaction
- Logs all warnings/errors to job record
- Updates `processed_rows`, `imported_products`, `imported_variants` counters

#### `ExportProcessor`

Generates CSV exports:

- `generateExport(array $filters): string` - Creates CSV, returns file path
- `getExportMapping(): array` - Column definitions for export
- Exports to `media/com_nxpeasycart/exports/{job_id}_{timestamp}.csv`
- Includes all product data, variants, categories, images URLs
- Uses native NXP format (importable via NativeAdapter)

### 3. Admin controllers

**New API controllers under `Administrator\Controller\Api`:**

#### `ImportexportController`

JSON endpoints for SPA:

- `GET api.importexport.jobs` - List import/export jobs with pagination/filters
- `GET api.importexport.job&id=X` - Get job details with progress
- `POST api.importexport.upload` - Handle CSV upload, detect platform, return headers
- `POST api.importexport.createImport` - Create import job with mapping
- `POST api.importexport.startImport` - Start background processing
- `POST api.importexport.cancelJob` - Cancel running job
- `GET api.importexport.downloadExport&id=X` - Download completed export CSV
- `POST api.importexport.createExport` - Create export job
- `DELETE api.importexport.deleteJob&id=X` - Delete job record + files

#### Long-running job processing

**Background processing strategy:**

1. **Server-sent events (SSE)** for real-time progress updates:
    - `GET api.importexport.streamProgress&id=X` - SSE endpoint
    - Client opens EventSource connection
    - Server streams progress events every 1-2 seconds
    - Event payload: `{processed: 150, total: 500, products: 45, variants: 98, status: 'processing'}`

2. **Chunked processing:**
    - Process 50 rows per chunk
    - After each chunk: update job counters, flush DB, send SSE event
    - Prevents timeouts, enables cancellation between chunks
    - If PHP timeout occurs, job stays in 'processing' state (admin can retry/cancel)

3. **Alternative: polling fallback**
    - If SSE not feasible (hosting restrictions), fall back to polling
    - Client polls `api.importexport.job&id=X` every 2 seconds
    - Less efficient but universally compatible

### 4. File handling

**Upload & storage:**

- Uploads go to `media/com_nxpeasycart/imports/{user_id}/{timestamp}_{random}.csv`
- Original filename stored in job record for display purposes
- Max file size: configurable setting (default 50MB)
- Allowed extensions: `.csv` only
- Validate CSV structure:
  - Detect and strip UTF-8 BOM (common in Excel exports)
  - Validate valid UTF-8 encoding (reject ISO-8859-1, Windows-1252)
  - Check for parseable header row
  - Validate at least one data row exists
- Keep uploaded files for 7 days (extend existing cleanup task plugin)

**Export files:**

- Generated to `media/com_nxpeasycart/exports/{job_id}_{timestamp}.csv`
- Protected directory (`.htaccess` deny all direct access)
- Served via controller with ACL check
- Cleanup after 7 days

**Security:**

- All file operations behind `core.manage` ACL
- CSRF tokens on upload/create endpoints
- Validate MIME type (text/csv, text/plain, application/csv)
- Sanitize filenames (strip path traversal, spaces, special chars)
- Files stored outside web root if possible (or protected directory)

---

## Resilient data handling

### Fallback strategies

**Missing/invalid values:**

- **SKU (required):** Generate from product title + variant options: `{slug}-{option1}-{option2}` (max 64 chars to match schema)
- **Price (required):** Default to `0.00` with warning logged
- **Sale price:** Skip if missing (no sale active)
- **Sale dates:** Skip if missing (sale price ignored without both dates)
- **Stock:** Default to `0` (out of stock is safe default)
- **Weight:** Default to `0.000`
- **Currency:** Use store base currency from settings (reject rows with different currency - single-currency MVP)
- **Product title:** If missing, use `Imported Product {row_number}` with warning
- **Slug:** Auto-generate from title using Joomla's `OutputFilter::stringURLSafe()`, ensure uniqueness
- **Active/published:** Default to `true` (merchant can review)
- **Featured:** Default to `false`
- **Product type:** Default to `'physical'` (physical/digital)
- **Is digital:** Default to `false`, inherit from product_type if product is digital
- **EAN:** Skip if invalid format (must be 8 or 13 digits with valid checksum, or empty)
- **Categories:** If missing/invalid, assign to auto-created "Imported" category
- **Images:** Store URLs in `original_images` JSON, don't download (optional future enhancement)

> **Currency handling (Single-currency MVP):**
> The component enforces a single base currency. If imported data contains prices in a different currency:
> - Log warning: "Currency {X} does not match store currency {Y}"
> - Use price value as-is (assume merchant exported in correct currency)
> - Future enhancement: Add currency conversion option

**Duplicate SKUs:**

- Check if SKU exists before save
- If exists, append counter: `SKU-001`, `SKU-002`, etc.
- Log warning: "Duplicate SKU '{original}' renamed to '{new}'"

**Invalid categories:**

- Parse category paths: `Clothing > T-Shirts` or `Clothing|T-Shirts`
- Create missing categories automatically with proper parent/child hierarchy
- Slugify category names, ensure uniqueness
- If category creation fails, assign to "Imported" fallback category

**Variant grouping logic:**

- **Single-row platforms (WooCommerce, VirtueMart, HikaShop):** Each row = one variant
    - Group by product name + slug
    - First row creates product, subsequent rows add variants
- **Multi-row platforms (Shopify):** Rows with same Handle belong to one product
    - Parse Option1/2/3 columns into variant options array
- **Native format:** Explicit product_id grouping

**Data type coercion:**

- **Decimals:** Strip currency symbols, thousands separators; parse with `floatval()`
- **Booleans:** Recognize `1, true, yes, on, published` as true; everything else false
- **Integers:** Cast with `intval()`, default to 0 on failure
- **Text:** Trim whitespace, strip null bytes, ensure valid UTF-8

### Error handling principles

1. **Never fail entire import on single row error**
    - Catch exceptions per row, log error, increment `skipped_rows`, continue
2. **Distinguish errors vs warnings**
    - **Errors:** Row completely unprocessable (missing required fields after fallbacks) - skip row
    - **Warnings:** Row imported with fallback values - log but import succeeds
3. **Detailed logging**
    - Store errors/warnings as JSON arrays in job record
    - Format: `{row: 42, message: 'Duplicate SKU renamed', field: 'sku', original: 'ABC', new: 'ABC-001'}`
4. **Transactional safety**
    - Each product save wrapped in DB transaction
    - Rollback on save failure, log error, continue to next row
5. **Job completion**
    - Job succeeds even if some rows skipped (partial success)
    - Status = `completed` with non-zero `skipped_rows` and errors/warnings populated
    - Job fails (`status=failed`) only on catastrophic errors (DB down, out of memory, etc.)

---

## Admin UI/UX

### New workspace: Import/Export

**Location:** Submenu item alongside existing workspaces in the NXP Easy Cart admin panel:
`Dashboard | Products | Categories | Orders | Customers | Coupons | Tax | Shipping | **Import/Export** | Settings | Logs`

> **Note:** This follows the established pattern for other admin workspaces (Tax, Shipping) - a dedicated Vue panel component with its own HtmlView routing through the SPA via `appSection`.

**Initial view (job list):**

```
┌─────────────────────────────────────────────────────────────────────┐
│ Import/Export                                              [+ Import] [+ Export] │
├─────────────────────────────────────────────────────────────────────┤
│ Filters: [ All Jobs ▼ ] [ All Platforms ▼ ] [Search...]   [Filter] │
├──────┬─────────┬───────────┬──────────┬────────────┬───────────────┤
│ ID   │ Type    │ Platform  │ Status   │ Progress   │ Created       │ Actions     │
├──────┼─────────┼───────────┼──────────┼────────────┼───────────────┼─────────────┤
│ 42   │ Import  │ Shopify   │ Complete │ 500/500    │ 2 hours ago   │ [View] [⊗]  │
│      │         │           │          │ 98 products│               │             │
│ 41   │ Export  │ Native    │ Complete │ 150/150    │ 1 day ago     │ [Download]  │
│ 40   │ Import  │ WooCommerce│ Failed  │ 45/200     │ 3 days ago    │ [Retry] [⊗] │
└──────┴─────────┴───────────┴──────────┴────────────┴───────────────┴─────────────┘
```

**Features:**

- Pagination (20 per page)
- Filter by type (import/export), platform, status
- Search by filename
- Click row to view details modal
- Actions: View details, Download export, Retry failed, Delete job

### Import flow (3 steps)

#### Step 1: Upload

```
┌─────────────────────────────────────────────────────────────────────┐
│ Import Products - Step 1: Upload                           [Cancel] │
├─────────────────────────────────────────────────────────────────────┤
│ Upload CSV File                                                     │
│                                                                     │
│ ┌─────────────────────────────────────────────────────────────────┐│
│ │  Drag & drop CSV file here or [Browse]                          ││
│ │                                                                  ││
│ │  Max file size: 50 MB                                           ││
│ │  Supported platforms: VirtueMart, WooCommerce, Shopify, HikaShop││
│ └─────────────────────────────────────────────────────────────────┘│
│                                                                     │
│ Or select sample:                                                   │
│ [ VirtueMart sample ▼ ]  [Load Sample]                            │
│                                                                     │
│                                             [Next: Configure Mapping] │
└─────────────────────────────────────────────────────────────────────┘
```

**On upload:**

- Validate file (CSV format, UTF-8, max size)
- Parse headers
- Detect platform automatically (show detected platform badge)
- Parse first 5 rows as preview
- Proceed to mapping step

#### Step 2: Mapping

```
┌─────────────────────────────────────────────────────────────────────┐
│ Import Products - Step 2: Configure Mapping          [Back] [Cancel]│
├─────────────────────────────────────────────────────────────────────┤
│ Platform detected: [Shopify ✓]    (or) [Manual ▼] if not detected  │
│                                                                     │
│ Column Mapping                                                      │
│ ┌─────────────────────────────────────────────────────────────────┐│
│ │ Source Column          → Target Field      Sample Value          ││
│ ├───────────────────────┼──────────────────┼──────────────────────┤│
│ │ Handle                → Product Slug     │ classic-cotton-tshirt││
│ │ Title                 → Product Name     │ Classic Cotton T-Shirt│
│ │ Body (HTML)           → Description      │ <p>Our classic...</p>││
│ │ Variant SKU           → SKU (variant)    │ TSHIRT-BLK-S         ││
│ │ Variant Price         → Price (variant)  │ 24.99                ││
│ │ Variant Inventory Qty → Stock (variant)  │ 45                   ││
│ │ Variant Grams         → Weight (variant) │ 180                  ││
│ │ Option1 Name          → Option Name 1    │ Color                ││
│ │ Option1 Value         → Option Value 1   │ Black                ││
│ │ Option2 Name          → Option Name 2    │ Size                 ││
│ │ Option2 Value         → Option Value 2   │ S                    ││
│ │ Variant Barcode       → EAN (variant)    │ 1234567890123        ││
│ │ Product Category      → Categories       │ Apparel & Accessories││
│ │ Image Src             → Images (original)│ https://cdn.shopify..││
│ │ [Skip]                → (Not mapped)     │ SEO Title            ││
│ │ [Skip]                → (Not mapped)     │ Google Shopping...   ││
│ └─────────────────────────────────────────────────────────────────┘│
│                                                                     │
│ Import Options                                                      │
│ ☑ Create missing categories automatically                          │
│ ☑ Generate unique SKUs for duplicates                             │
│ ☐ Update existing products (match by SKU)                         │
│ ☑ Set imported products as active                                 │
│ ☑ Store original image URLs (don't download)                      │
│                                                                     │
│                                        [Back] [Start Import →]     │
└─────────────────────────────────────────────────────────────────────┘
```

**Features:**

- Pre-filled mappings based on detected platform
- Drag & drop to reorder/remap columns
- Dropdowns to change target fields
- Preview shows first row sample values
- Visual indicators for required fields (red asterisk)
- Validation warnings (e.g., "Price column not mapped - will default to 0.00")
- Collapsible "Advanced Options" section
- "Reset to defaults" button restores platform defaults

**Mapping presets:**

- Stored per platform in adapter `getDefaultMapping()` method
- User can save custom mappings (future enhancement)

#### Step 3: Processing

```
┌─────────────────────────────────────────────────────────────────────┐
│ Importing Products...                                      [Cancel] │
├─────────────────────────────────────────────────────────────────────┤
│ Status: Processing                                                  │
│                                                                     │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░  65% (325 / 500 rows)                   │
│                                                                     │
│ Products created: 87                                                │
│ Variants imported: 245                                              │
│ Rows skipped: 3                                                     │
│                                                                     │
│ Recent activity:                                                    │
│ • Created product "Leather Wallet" with 4 variants                  │
│ • Warning: Duplicate SKU "WALLET-BRN" renamed to "WALLET-BRN-001"  │
│ • Created product "Canvas Backpack" with 2 variants                │
│ • Error: Row 203 skipped - invalid price format                    │
│ • Created product "Denim Jeans" with 6 variants                    │
│                                                                     │
│ [View Full Log]                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Features:**

- Real-time progress bar
- Live counters (products, variants, skipped)
- Activity feed (last 10 events, scrollable)
- Cancel button (stops after current chunk)
- On completion, shows summary modal with "View Products" / "View Log" / "Close" buttons

**Progress updates:**

- Uses Server-Sent Events (SSE) for real-time updates
- Falls back to polling if SSE unavailable
- Updates every 1-2 seconds
- Smooth animations (progress bar, counters increment)

#### Job details modal

```
┌─────────────────────────────────────────────────────────────────────┐
│ Import Job #42 - Shopify Import                           [Close] [⊗]│
├─────────────────────────────────────────────────────────────────────┤
│ Status: Completed                                                   │
│ Platform: Shopify                                                   │
│ File: shopify_products_2025_12_15.csv                              │
│ Started: Dec 15, 2025 14:32:18                                     │
│ Completed: Dec 15, 2025 14:35:42 (3m 24s)                         │
│                                                                     │
│ Results:                                                            │
│ • Total rows: 500                                                   │
│ • Products created: 98                                              │
│ • Variants imported: 456                                            │
│ • Rows skipped: 12                                                  │
│                                                                     │
│ Errors (12):                                                        │
│ • Row 42: Invalid price format '€25,99' - defaulted to 0.00       │
│ • Row 89: Missing required field 'title' - skipped                 │
│ • Row 156: Invalid EAN '12345' (must be 13 digits) - skipped      │
│ ...  [Show all]                                                     │
│                                                                     │
│ Warnings (34):                                                      │
│ • Row 23: Duplicate SKU 'TSHIRT-BLK' renamed to 'TSHIRT-BLK-001'  │
│ • Row 67: Category 'Accessories/Bags' created automatically        │
│ ...  [Show all]                                                     │
│                                                                     │
│ [Download Error Log CSV] [View Imported Products] [Delete Job]     │
└─────────────────────────────────────────────────────────────────────┘
```

### Export flow

**Export configuration:**

```
┌─────────────────────────────────────────────────────────────────────┐
│ Export Products                                            [Cancel] │
├─────────────────────────────────────────────────────────────────────┤
│ Select Products to Export                                           │
│                                                                     │
│ ○ All products (152 products, 487 variants)                        │
│ ○ Filtered products                                                │
│   ☐ Only active products                                           │
│   ☐ Only products with stock                                       │
│   ☐ Only products imported from: [All platforms ▼]                │
│   Categories: [ All categories ▼ ]  (multi-select)                │
│                                                                     │
│ Export Format                                                       │
│ ○ Native (NXP Easy Cart format - recommended for backup)           │
│ ○ WooCommerce compatible                                           │
│ ○ Shopify compatible                                               │
│                                                                     │
│ Options                                                             │
│ ☑ Include all variants                                             │
│ ☑ Include categories                                               │
│ ☑ Include images (as URLs)                                         │
│ ☑ Include EAN barcodes                                             │
│ ☐ Include metadata (created/modified dates)                        │
│                                                                     │
│                                          [Cancel] [Start Export →] │
└─────────────────────────────────────────────────────────────────────┘
```

**Export processing:**

- Generates CSV in background (same job processing as import)
- Progress modal shows rows exported
- On completion, auto-downloads CSV
- Export file stored for 7 days (downloadable from job list)

---

## Settings

**New section in Settings → General:**

```
Import/Export Settings
├─ Max upload file size: [50] MB
├─ Job retention period: [7] days
├─ Chunk size (rows per batch): [50]
├─ Auto-create missing categories: [✓]
├─ Default product status for imports: [ Active ▼ ]
└─ Enable image URL storage: [✓] (stores original URLs without downloading)
```

---

## API endpoints summary

| Endpoint                          | Method | Purpose                        | ACL         |
| --------------------------------- | ------ | ------------------------------ | ----------- |
| `api.importexport.jobs`           | GET    | List import/export jobs        | core.manage |
| `api.importexport.job`            | GET    | Get job details + progress     | core.manage |
| `api.importexport.upload`         | POST   | Upload CSV, detect platform    | core.create |
| `api.importexport.createImport`   | POST   | Create import job with mapping | core.create |
| `api.importexport.startImport`    | POST   | Start import processing        | core.create |
| `api.importexport.cancelJob`      | POST   | Cancel running job             | core.edit   |
| `api.importexport.deleteJob`      | DELETE | Delete job + files             | core.delete |
| `api.importexport.createExport`   | POST   | Create export job              | core.create |
| `api.importexport.downloadExport` | GET    | Download export CSV            | core.manage |
| `api.importexport.streamProgress` | GET    | SSE progress stream            | core.manage |

---

## Platform adapter specifications

### Common normalized format

All adapters output this structure (aligned with existing schema):

```php
[
    'product' => [
        'title' => 'Classic Cotton T-Shirt',
        'slug' => 'classic-cotton-tshirt',
        'short_desc' => 'Premium organic cotton t-shirt',
        'long_desc' => '<p>Full HTML description</p>',
        'active' => true,
        'featured' => false,
        'product_type' => 'physical', // 'physical' or 'digital' (matches ENUM in schema)
        'categories' => ['Clothing', 'T-Shirts'], // array of category paths
        'images' => [ // product-level images (stored in products.images JSON)
            'https://cdn.example.com/tshirt-main.jpg',
        ],
        'original_id' => '12345', // platform-specific ID
    ],
    'variant' => [
        'sku' => 'TSHIRT-BLK-S',
        'price' => 24.99, // float, major units (converted to price_cents)
        'sale_price' => 19.99, // optional, float (converted to sale_price_cents)
        'sale_start' => '2025-12-01 00:00:00', // optional, datetime string
        'sale_end' => '2025-12-31 23:59:59', // optional, datetime string
        'currency' => 'USD',
        'stock' => 45,
        'weight' => 0.180, // always in kg (adapters convert from source unit)
        'ean' => '1234567890123', // optional, EAN-8 or EAN-13
        'is_digital' => false, // matches is_digital flag in schema
        'active' => true,
        'options' => [ // variant options (stored in variants.options JSON)
            ['name' => 'Color', 'value' => 'Black'],
            ['name' => 'Size', 'value' => 'S'],
        ],
        'original_images' => [ // variant-specific image URLs (stored in variants.original_images JSON)
            'https://cdn.example.com/tshirt-black-1.jpg',
            'https://cdn.example.com/tshirt-black-2.jpg',
        ],
        'original_id' => 'variant-67890',
    ],
]
```

> **Field mapping to schema:**
> - `product.images` → `#__nxp_easycart_products.images` (JSON)
> - `variant.original_images` → `#__nxp_easycart_variants.original_images` (JSON, new field)
> - `variant.price` × 100 → `#__nxp_easycart_variants.price_cents`
> - `variant.sale_price` × 100 → `#__nxp_easycart_variants.sale_price_cents`
> - Weight always stored in kg (DECIMAL(10,3))

### VirtueMart adapter

**Key mappings:**

- `product_name` → product title
- `product_sku` → variant SKU
- `product_price` → variant price
- `product_in_stock` → variant stock
- `categories` (pipe-separated: `Clothing|T-Shirts`) → categories array
- `customfields` (pipe-separated: `color:Black|size:S`) → variant options
- `images` (pipe-separated) → original_images array

**Quirks:**

- Each row = one variant
- Group rows by `slug` to create products with multiple variants
- `published` field (0/1) → active boolean

### WooCommerce adapter

**Key mappings:**

- `Name` → product title
- `SKU` → variant SKU
- `Regular price` → variant price
- `Stock` → variant stock
- `Weight (kg)` → variant weight
- `Categories` (delimiter: `>`) → categories array (hierarchical)
- `Attribute 1 name` + `Attribute 1 value(s)` → variant option 1
- `Attribute 2 name` + `Attribute 2 value(s)` → variant option 2
- `Images` (comma-separated URLs) → original_images array
- `Type` column: only process `simple` type (skip variable parent rows)

**Quirks:**

- Each row = one variant (WC exports simple products as separate rows)
- Group by `Name` to create products
- `Published` (0/1) → active boolean
- `In stock?` (0/1) + `Stock` → stock value

### Shopify adapter

**Key mappings:**

- `Handle` → product slug (grouping key)
- `Title` → product title
- `Body (HTML)` → long_desc
- `Variant SKU` → variant SKU
- `Variant Price` → variant price
- `Variant Inventory Qty` → variant stock
- `Variant Grams` → variant weight (convert to kg: grams / 1000)
- `Variant Barcode` → EAN
- `Option1 Name` + `Option1 Value` → variant option 1
- `Option2 Name` + `Option2 Value` → variant option 2
- `Option3 Name` + `Option3 Value` → variant option 3
- `Product Category` (path: `Apparel & Accessories > Clothing`) → categories
- `Image Src` → original_images (first variant row with image)
- `Published` (true/false) → active boolean

**Quirks:**

- Multi-row format: each variant is a separate row with same `Handle`
- Group all rows by `Handle` to create one product with multiple variants
- Only first row has product-level data (Title, Body); subsequent rows repeat it
- Weight in grams, convert to kg

### HikaShop adapter

**Key mappings:**

- `product_name` → product title
- `product_alias` → product slug
- `product_description` → long_desc
- `variant_code` → variant SKU
- `variant_price` or `product_price` → variant price
- `variant_quantity` or `product_quantity` → variant stock
- `product_weight` → variant weight
- `product_categories` (comma-separated) → categories array
- `characteristic_1` + `characteristic_2` → variant options (e.g., "Black", "S")
- `product_images` (comma-separated) → original_images array
- `product_published` (0/1) → active boolean

**Quirks:**

- Each row = one variant
- HikaShop uses `characteristic_*` columns for variant options (no explicit names)
- Infer option names from values: if value looks like color (Black, Red), name it "Color"
- Group by `product_id` or `product_name` + `product_alias`

### Native adapter (NXP Easy Cart)

**Key mappings:**

- Direct 1:1 mapping to our schema
- `product_id` → explicit grouping
- No transformations needed

**Export format (Native) - complete column list:**

```csv
product_id,product_slug,product_title,short_desc,long_desc,product_type,featured,active,categories,images,variant_id,sku,price,sale_price,sale_start,sale_end,currency,stock,weight,ean,is_digital,variant_active,options,original_images
1,classic-tshirt,Classic T-Shirt,"Premium cotton","<p>Description</p>",physical,0,1,"Clothing,T-Shirts","https://cdn.example.com/main.jpg",27,TSHIRT-BLK-S,24.99,19.99,2025-12-01 00:00:00,2025-12-31 23:59:59,USD,45,0.180,5901234123457,0,1,"Color:Black|Size:S","https://cdn.example.com/img1.jpg,https://cdn.example.com/img2.jpg"
1,classic-tshirt,Classic T-Shirt,"Premium cotton","<p>Description</p>",physical,0,1,"Clothing,T-Shirts","https://cdn.example.com/main.jpg",28,TSHIRT-BLK-M,24.99,,,USD,62,0.195,,0,1,"Color:Black|Size:M",
2,ebook-guide,Digital Marketing Guide,"Learn digital marketing","<p>Full guide</p>",digital,1,1,"Books,Digital","",,EBOOK-001,9.99,,,USD,999,0,,,1,1,,
```

> **Export includes all variant fields:**
> - Sale pricing (`sale_price`, `sale_start`, `sale_end`)
> - Digital product flags (`product_type`, `is_digital`)
> - Featured flag for product spotlight
> - Separate `active` and `variant_active` columns

---

## Testing strategy

### Unit tests

- Test each adapter's `normalizeRow()` with sample platform data
- Test `fallbackValue()` with missing/invalid inputs
- Test SKU uniqueness logic with duplicates
- Test category path parsing and creation
- Test data type coercion edge cases

### Integration tests

- Import sample CSVs for each platform (files in `/samples`)
- Verify correct product/variant counts
- Verify categories created with proper hierarchy
- Verify fallback values applied correctly
- Test duplicate SKU handling
- Test job cancellation mid-import

### Manual testing

- Upload large CSV (1000+ rows), monitor progress
- Test invalid CSV formats (missing headers, bad encoding)
- Test platform auto-detection
- Test custom mapping changes
- Test export then re-import (round-trip)
- Test concurrent jobs (multiple imports)

---

## Security considerations

### File upload security

- Validate MIME type: `text/csv`, `text/plain`, `application/csv` only
- Max file size enforcement (server-side + client-side)
- Sanitize filename: remove path traversal (`..`), special chars
- Store uploads in protected directory with `.htaccess` deny
- Generate random filename: `{user_id}_{timestamp}_{random}.csv`
- ACL check: require `core.create` or `core.manage`

### Data sanitization

- Escape all output in UI (XSS prevention)
- Use Joomla's JInput filters on all API inputs
- Prepared statements for all DB queries (already standard)
- HTML purification on imported descriptions (Joomla's HTML filter)
- Validate numeric fields (price, stock, weight) are actual numbers
- Validate EAN format (13 digits) before saving

### CSRF protection

- All API endpoints require `Session::checkToken('request')`
- Vue SPA includes token in all POST/PATCH/DELETE requests

### ACL enforcement

- Import/Export menu item requires `core.manage`
- Upload/create endpoints require `core.create`
- Delete jobs requires `core.delete`
- Non-admin users cannot access import/export features

### Rate limiting

- Limit uploads to 10 per hour per user (cache-based counter)
- Prevent DOS via large file uploads (max size)
- Job processing uses chunking (prevents memory exhaustion)

---

## Performance considerations

### Chunked processing

- Process 50 rows per chunk (configurable)
- Flush output buffer after each chunk
- Prevents PHP timeout (each chunk completes in <5s)
- Enables cancellation between chunks
- Reduces memory footprint (don't load entire CSV into memory)

### Database optimization

- Batch insert variants (transaction per product)
- Use `INSERT ... ON DUPLICATE KEY UPDATE` for idempotent imports
- Index on `imported_from`, `ean`, `original_id` for lookups
- Cleanup old jobs/files via cron (reduce table bloat)

### Memory management

- Stream CSV parsing (don't load entire file)
- Unset processed rows immediately
- Limit preview to first 5 rows
- Export uses cursor/streaming writes for large datasets

### Caching

- Cache category tree lookups (avoid repeated queries)
- Memoise platform adapters (single instance per job)
- Cache base currency from settings

---

## Language strings

**Add to `administrator/language/en-GB/com_nxpeasycart.ini`:**

```ini
; Import/Export workspace
COM_NXPEASYCART_SUBMENU_IMPORT_EXPORT="Import/Export"
COM_NXPEASYCART_IMPORT_EXPORT_TITLE="Import/Export Products"
COM_NXPEASYCART_IMPORT_EXPORT_DESC="Import products from other platforms or export for backup"

; Import wizard
COM_NXPEASYCART_IMPORT_STEP_UPLOAD="Upload CSV File"
COM_NXPEASYCART_IMPORT_STEP_MAPPING="Configure Mapping"
COM_NXPEASYCART_IMPORT_STEP_PROCESSING="Importing..."
COM_NXPEASYCART_IMPORT_DRAG_DROP="Drag & drop CSV file here or"
COM_NXPEASYCART_IMPORT_BROWSE="Browse"
COM_NXPEASYCART_IMPORT_MAX_SIZE="Max file size: %s MB"
COM_NXPEASYCART_IMPORT_PLATFORMS="Supported platforms: VirtueMart, WooCommerce, Shopify, HikaShop"
COM_NXPEASYCART_IMPORT_PLATFORM_DETECTED="Platform detected: %s"
COM_NXPEASYCART_IMPORT_PLATFORM_MANUAL="Select platform manually"
COM_NXPEASYCART_IMPORT_START="Start Import"
COM_NXPEASYCART_IMPORT_CANCEL="Cancel"

; Mapping
COM_NXPEASYCART_MAPPING_SOURCE="Source Column"
COM_NXPEASYCART_MAPPING_TARGET="Target Field"
COM_NXPEASYCART_MAPPING_SAMPLE="Sample Value"
COM_NXPEASYCART_MAPPING_SKIP="Skip"
COM_NXPEASYCART_MAPPING_REQUIRED="Required"
COM_NXPEASYCART_MAPPING_RESET="Reset to defaults"

; Import options
COM_NXPEASYCART_IMPORT_OPT_CREATE_CATEGORIES="Create missing categories automatically"
COM_NXPEASYCART_IMPORT_OPT_UNIQUE_SKU="Generate unique SKUs for duplicates"
COM_NXPEASYCART_IMPORT_OPT_UPDATE_EXISTING="Update existing products (match by SKU)"
COM_NXPEASYCART_IMPORT_OPT_SET_ACTIVE="Set imported products as active"
COM_NXPEASYCART_IMPORT_OPT_STORE_IMAGES="Store original image URLs (don't download)"

; Progress
COM_NXPEASYCART_IMPORT_PROGRESS="Progress"
COM_NXPEASYCART_IMPORT_PRODUCTS_CREATED="Products created"
COM_NXPEASYCART_IMPORT_VARIANTS_IMPORTED="Variants imported"
COM_NXPEASYCART_IMPORT_CATEGORIES_CREATED="Categories created"
COM_NXPEASYCART_IMPORT_ROWS_SKIPPED="Rows skipped"
COM_NXPEASYCART_IMPORT_RECENT_ACTIVITY="Recent activity"
COM_NXPEASYCART_IMPORT_VIEW_LOG="View Full Log"

; Job status
COM_NXPEASYCART_JOB_STATUS_PENDING="Pending"
COM_NXPEASYCART_JOB_STATUS_PROCESSING="Processing"
COM_NXPEASYCART_JOB_STATUS_COMPLETED="Completed"
COM_NXPEASYCART_JOB_STATUS_FAILED="Failed"
COM_NXPEASYCART_JOB_STATUS_CANCELLED="Cancelled"

; Export
COM_NXPEASYCART_EXPORT_TITLE="Export Products"
COM_NXPEASYCART_EXPORT_ALL="All products"
COM_NXPEASYCART_EXPORT_FILTERED="Filtered products"
COM_NXPEASYCART_EXPORT_FORMAT_NATIVE="Native (NXP Easy Cart format - recommended for backup)"
COM_NXPEASYCART_EXPORT_FORMAT_WOOCOMMERCE="WooCommerce compatible"
COM_NXPEASYCART_EXPORT_FORMAT_SHOPIFY="Shopify compatible"
COM_NXPEASYCART_EXPORT_START="Start Export"
COM_NXPEASYCART_EXPORT_DOWNLOAD="Download"

; Errors/warnings
COM_NXPEASYCART_IMPORT_ERROR_FILE_TOO_LARGE="File exceeds maximum size of %s MB"
COM_NXPEASYCART_IMPORT_ERROR_INVALID_FORMAT="Invalid CSV format"
COM_NXPEASYCART_IMPORT_ERROR_NO_HEADERS="CSV file has no header row"
COM_NXPEASYCART_IMPORT_ERROR_NO_DATA="CSV file has no data rows"
COM_NXPEASYCART_IMPORT_ERROR_INVALID_UTF8="File encoding is not valid UTF-8"
COM_NXPEASYCART_IMPORT_WARNING_DUPLICATE_SKU="Duplicate SKU '%s' renamed to '%s'"
COM_NXPEASYCART_IMPORT_WARNING_CATEGORY_CREATED="Category '%s' created automatically"
COM_NXPEASYCART_IMPORT_WARNING_PRICE_DEFAULT="Row %d: Missing price, defaulted to 0.00"
COM_NXPEASYCART_IMPORT_WARNING_CURRENCY_MISMATCH="Row %d: Currency %s does not match store currency %s"
COM_NXPEASYCART_IMPORT_ERROR_INVALID_EAN="Row %d: Invalid EAN '%s' (must be 8 or 13 digits)"
COM_NXPEASYCART_IMPORT_ERROR_ROW_SKIPPED="Row %d skipped: %s"
```

---

## User documentation

### Admin docs (`docs/import-export.md`)

Document:

- Supported platforms and format examples
- Step-by-step import guide with screenshots
- Mapping guide (common fields, platform-specific)
- Troubleshooting common errors (encoding, duplicates, missing data)
- Export guide and use cases (backup, migration)
- FAQs:
    - What happens to existing products?
    - Can I update products via import?
    - Why are images not downloaded?
    - How do I handle large imports (2000+ products)?
    - How to map custom fields?

### Inline help

- Tooltip on each mapping field explaining what it does
- Warning messages for unmapped required fields
- Link to docs from import wizard header
- Sample CSV download links for each platform

### Sample files

Create sample CSVs in `administrator/components/com_nxpeasycart/samples/`:

```
samples/
├── virtuemart_sample.csv      (5 products, various categories)
├── woocommerce_sample.csv     (5 products with attributes)
├── shopify_sample.csv         (3 products with multi-row variants)
├── hikashop_sample.csv        (4 products with characteristics)
└── native_sample.csv          (complete example with all fields)
```

Each sample should include:
- At least one product with multiple variants
- At least one product with sale pricing
- Categories with hierarchy (parent > child)
- Mix of active/inactive products
- Valid EAN barcodes on some variants
- Unicode characters in descriptions (to test encoding)

---

## Future enhancements (out of scope for v0.3.0)

### Phase 2 (v0.4.0)

- **Image downloading:** Option to download images during import
    - Configurable: store locally or use CDN URLs
    - Progress tracking for image downloads
    - Fallback if image URL fails (log warning, continue)
- **Update existing products:** Match by SKU, update prices/stock
    - Option: "Update existing" vs "Skip duplicates"
- **Scheduled imports:** Cron job to auto-import from URL/FTP
    - Useful for dropshippers syncing inventory
- **Custom field mapping:** Support additional custom fields
    - User-defined field mapping UI
    - Store custom mappings per platform
- **Multi-file imports:** Upload zip with CSV + images
    - Extract zip, match images by filename in CSV
- **Magento, PrestaShop, OpenCart adapters**
    - Sample CSVs already present in `/samples`

### Phase 3 (v0.5.0)

- **Advanced export filters:**
    - Date range (created between X and Y)
    - Price range
    - Stock levels (low stock, out of stock)
- **Scheduled exports:** Auto-export weekly for backup
- **Import templates:** Save custom mappings as reusable templates
- **Rollback:** "Undo import" feature (delete all products from a job)
- **API import:** REST endpoint for programmatic imports
    - Useful for integrations, automated syncs
- **Import validation mode:** Dry-run that reports errors without importing

---

## Milestone breakdown (M3)

### M3.1: Database & service layer

- [ ] Create migration for schema changes (import fields on products/variants, jobs table)
- [ ] Implement `ImportExportService` with job lifecycle management
- [ ] Implement `PlatformAdapterFactory` + `PlatformAdapterInterface`
- [ ] Implement 5 platform adapters:
  - [ ] `VirtueMartAdapter` (pipe-separated categories/options)
  - [ ] `WooCommerceAdapter` (skip variable parents, handle attributes)
  - [ ] `ShopifyAdapter` (multi-row grouping by Handle, grams→kg)
  - [ ] `HikaShopAdapter` (characteristic inference)
  - [ ] `NativeAdapter` (direct 1:1 mapping)
- [ ] Implement `ImportProcessor` with:
  - [ ] Chunked processing with resume capability
  - [ ] Fallback value application
  - [ ] Duplicate SKU handling
  - [ ] Category tree creation
  - [ ] Transaction wrapping per product
- [ ] Implement `ExportProcessor` with streaming writes
- [ ] Create sample CSV files for each platform (5 files)
- [ ] Unit tests for adapters + processor

### M3.2: Admin API controllers

- [ ] Implement `ImportexportController` JSON endpoints:
  - [ ] `api.importexport.jobs` - List with pagination/filters
  - [ ] `api.importexport.job` - Get job details
  - [ ] `api.importexport.upload` - Upload + validate + detect platform
  - [ ] `api.importexport.createImport` - Create job with mapping
  - [ ] `api.importexport.startImport` - Begin processing
  - [ ] `api.importexport.cancelJob` - Cancel running job
  - [ ] `api.importexport.resumeJob` - Resume from last_processed_row
  - [ ] `api.importexport.createExport` - Start export
  - [ ] `api.importexport.downloadExport` - Serve CSV file
  - [ ] `api.importexport.deleteJob` - Delete job + files
- [ ] File upload handling:
  - [ ] BOM detection/stripping
  - [ ] UTF-8 validation
  - [ ] MIME type validation
  - [ ] Max size enforcement
  - [ ] SHA-256 hash for duplicate detection
- [ ] SSE progress streaming endpoint (`api.importexport.streamProgress`)
- [ ] ACL enforcement (`core.manage` for all endpoints)
- [ ] CSRF checks (`Session::checkToken('request')`)
- [ ] Integration tests for API

### M3.3: Admin UI (Vue SPA)

- [ ] Create `ImportExportPanel.vue` workspace component
- [ ] Create `ImportExport/HtmlView.php` + template for SPA routing
- [ ] Add submenu item in manifest XML
- [ ] Job list view:
  - [ ] Filters (type, platform, status)
  - [ ] Pagination (20 per page)
  - [ ] Search by filename
  - [ ] Action buttons (View, Download, Retry, Delete)
- [ ] Import wizard (3-step modal):
  - [ ] `ImportUpload.vue` - Drag & drop + validation + sample loader
  - [ ] `ImportMapping.vue` - Column mapping table + options checkboxes
  - [ ] `ImportProgress.vue` - Progress bar + counters + activity feed + SSE
- [ ] `JobDetailsModal.vue` - Status, errors, warnings, actions
- [ ] `ExportModal.vue` - Filter configuration + format selection
- [ ] Composable: `useImportExport()` with cache-first strategy (5-min TTL)
- [ ] Add skeleton loaders for loading states
- [ ] Add language strings to `useTranslations()`

### M3.4: Testing & polish

- [ ] Manual testing with all sample CSVs (5 platforms)
- [ ] Test large imports (1000+ rows, measure memory/time)
- [ ] Test error scenarios:
  - [ ] Invalid CSV (bad encoding, missing headers)
  - [ ] Malformed data (invalid prices, bad EANs)
  - [ ] Duplicate SKUs across imports
  - [ ] Browser close during SSE
  - [ ] PHP timeout mid-import (test resume)
- [ ] Test platform auto-detection accuracy
- [ ] Test export → re-import round-trip (all fields preserved)
- [ ] Performance profiling:
  - [ ] Optimize chunk size (50 rows baseline)
  - [ ] Benchmark Shopify multi-row (products-per-chunk consideration)
  - [ ] Memory usage under 256MB for 2000-row imports
- [ ] Write `docs/import-export.md`
- [ ] Update `docs/README.md` with import/export section

### M3.5: Security audit & release

- [ ] Security review:
  - [ ] File upload validation (MIME, size, sanitization)
  - [ ] XSS prevention in error messages
  - [ ] CSRF on all endpoints
  - [ ] ACL enforcement verified
  - [ ] HTML purification on imported descriptions
- [ ] Rate limiting implementation (10 uploads/hour/user)
- [ ] Extend cleanup task plugin for import/export files
- [ ] Final testing on staging environment
- [ ] Update `CHANGELOG.md`
- [ ] Update `README.md` with feature summary
- [ ] Run PHPStan + PHP-CS-Fixer
- [ ] Release v0.3.0

---

## Dependencies & prerequisites

### PHP extensions

- `ext-mbstring` (UTF-8 handling)
- `ext-fileinfo` (MIME type detection)
- `ext-json` (JSON encoding/decoding)

### Composer packages

- No new dependencies required (use existing Joomla + Guzzle)

### Server requirements

- PHP 8.0+ with `max_execution_time >= 60` (or 0 for CLI)
- `upload_max_filesize >= 50M`
- `post_max_size >= 50M`
- `memory_limit >= 256M` (for large imports)

### Joomla version

- Joomla 5.0+ (uses existing DI container, MVC, Web Assets)

---

## Risk register

| Risk                     | Impact   | Likelihood | Mitigation                                           |
| ------------------------ | -------- | ---------- | ---------------------------------------------------- |
| Large imports timeout    | High     | Medium     | Chunked processing, SSE progress, resumable jobs via `last_processed_row` |
| Invalid/malformed CSV    | Medium   | High       | Robust parsing, fallback values, skip bad rows       |
| Memory exhaustion        | High     | Low        | Stream parsing, chunk size limit, memory limit check |
| Platform detection fails | Low      | Medium     | Manual platform selection fallback, `getSignatureHeaders()` |
| Duplicate SKUs conflict  | Medium   | Medium     | Auto-suffix logic, log warnings                      |
| Category creation fails  | Low      | Low        | Fallback to "Imported" category                      |
| File upload security     | Critical | Low        | MIME validation, sanitization, protected storage     |
| Concurrent job conflicts | Medium   | Low        | Database locking on job status updates, warn if job running |
| Export file too large    | Medium   | Low        | Stream writes, chunked generation, ZIP compression   |
| Duplicate file upload    | Low      | Medium     | SHA-256 hash check warns about re-importing same file |
| Browser close during SSE | Low      | Medium     | Job continues server-side, user can view status from job list |
| Weight unit confusion    | Medium   | Medium     | Adapters normalize to kg, document expected unit |
| UTF-8 BOM in Excel CSV   | Medium   | High       | BOM detection and stripping during upload validation |
| WooCommerce variable products | Medium | Medium  | Skip parent rows with `Type=variable`, only import simple/variation |

---

## Definitions of Done (DoD)

**Database & Schema:**
- [ ] All migrations applied and tested (new columns + jobs table)
- [ ] Indexes verified for performance (imported_from, original_id)

**Service Layer:**
- [ ] All 5 platform adapters implemented with unit tests
- [ ] ImportProcessor handles all fallback scenarios
- [ ] ExportProcessor generates complete CSV with all fields
- [ ] Resume capability tested (job continues from last_processed_row)

**API:**
- [ ] All 10+ endpoints functional with ACL + CSRF
- [ ] SSE streaming works, polling fallback tested
- [ ] File upload validation comprehensive (BOM, UTF-8, MIME, size)
- [ ] Rate limiting prevents abuse (10 uploads/hour/user)

**Admin UI:**
- [ ] Vue SPA components complete with real-time progress
- [ ] Skeleton loaders for loading states
- [ ] Mobile-responsive (follows existing patterns)
- [ ] All language strings in translation files

**Testing:**
- [ ] All sample CSVs successfully imported (5 platforms)
- [ ] Export generates valid re-importable CSV
- [ ] Round-trip test: export → import preserves all data
- [ ] 1000+ row import completes without timeout
- [ ] Memory stays under 256MB for large imports

**Security:**
- [ ] Security audit completed (file upload, XSS, CSRF, ACL)
- [ ] HTML purification on imported descriptions
- [ ] No SQL injection vectors

**Documentation & Quality:**
- [ ] Documentation written (`docs/import-export.md`)
- [ ] No PHP errors/warnings in logs
- [ ] PHPStan level 6+ passes
- [ ] Code follows PSR-12 style
- [ ] Changelog updated
- [ ] README updated with import/export feature description

---

## Open questions

1. **Image downloading:** Should we download images automatically, or always store URLs?
   - **Decision:** URLs only for v0.3.0, download option in v0.4.0 ✓

2. **Update vs skip:** Should duplicate SKUs update existing products or create new ones?
   - **Decision:** Create new with suffix for v0.3.0, add "update mode" checkbox in v0.4.0 ✓

3. **Job retention:** 7 days default for job cleanup?
   - **Decision:** Yes, configurable setting. Extend existing `plg_task_nxpeasycartcleanup` plugin ✓

4. **Chunk size:** 50 rows optimal?
   - **Decision:** Start with 50, make configurable, benchmark with Shopify multi-row format (may need products-per-chunk not rows-per-chunk) ✓

5. **SSE vs polling:** Should we implement both or SSE-only?
   - **Decision:** SSE primary with automatic polling fallback if EventSource fails ✓

6. **Concurrent imports:** What happens if user starts second import while first is running?
   - **Decision:** Show warning modal, offer to queue or cancel. Only one import job can be `processing` at a time per user.

7. **Category separator detection:** How to handle `>` vs `|` vs `,` separators?
   - **Decision:** Auto-detect from platform adapter, provide manual override in mapping step

8. **HikaShop characteristic inference:** How to name options when HikaShop only provides values?
   - **Decision:** Use heuristics (color names → "Color", sizes like S/M/L → "Size"), fall back to "Option 1", "Option 2"

---

## Conclusion

This plan delivers a production-ready, resilient import/export system that empowers merchants to migrate from competing platforms with zero friction. The architecture prioritises security, reliability, and user experience while maintaining NXP Easy Cart's core philosophy: simplicity and speed to first sale.

**Key design decisions aligned with existing codebase:**

1. **Schema alignment:** Leverages existing `ean` column, `sale_price_cents` fields, and `product_type` ENUM—no redundant fields
2. **JSON consistency:** Uses JSON columns for errors/warnings/mapping to match existing patterns (variants.options, products.images)
3. **Service layer organization:** New services under `Administrator\Service\Import` following established PSR-4 namespacing
4. **Admin SPA pattern:** Follows Tax/Shipping workspace pattern with dedicated HtmlView + Vue panel component
5. **Security model:** Same ACL (`core.manage`) and CSRF approach as other admin endpoints
6. **Cleanup integration:** Extends existing `plg_task_nxpeasycartcleanup` rather than creating new plugin

By implementing platform adapters with signature-based detection, intelligent fallbacks, and real-time SSE progress feedback, we ensure that imports succeed even with messy real-world data. The chunked processing architecture with resume capability handles datasets of any size without timeouts or memory issues.

The modular adapter pattern makes it trivial to add new platforms (Magento, PrestaShop) in future releases, and the export capability provides merchants with full data portability and backup functionality—including sale pricing, digital product flags, and all variant-level data.

**Next step:** Approve scope and proceed with M3.1 implementation (database migrations + service layer).

---

## Revision history

| Date       | Author | Changes                                                      |
|------------|--------|--------------------------------------------------------------|
| 2025-12-15 | Zoran  | Initial plan draft                                            |
| 2025-12-15 | Claude | Reviewed and improved: aligned with existing schema (EAN already exists), added sale pricing/digital product support, enhanced interface, added language strings section, sample files spec, expanded milestones, resolved open questions |
