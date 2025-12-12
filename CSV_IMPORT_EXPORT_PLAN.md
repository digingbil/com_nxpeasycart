# CSV Import/Export - Implementation Plan

## Overview

A comprehensive CSV import/export system that enables:
1. **Quick setup** - Import existing product catalogs in minutes (supports "10-minute setup" goal)
2. **Migration path** - Easy migration from competing platforms (VirtueMart, HikaShop, WooCommerce, Shopify, etc.)
3. **Bulk operations** - Mass update products, export orders for accounting
4. **Data portability** - GDPR-compliant data export

---

## Business Value

| Use Case | Benefit |
|----------|---------|
| New store setup | Import 100+ products in minutes vs. hours of manual entry |
| Platform migration | Reduce switching friction from competitors |
| Bulk updates | Update prices, stock for hundreds of products at once |
| Accounting integration | Export orders to QuickBooks, Xero, etc. |
| Inventory sync | Integrate with warehouse management systems |
| Backup/restore | Periodic catalog backups |

---

## Supported Platforms

### Joomla E-commerce

| Platform | Products | Orders | Customers | Notes |
|----------|----------|--------|-----------|-------|
| VirtueMart 3/4 | ✅ | ✅ | ✅ | Most common Joomla cart |
| HikaShop | ✅ | ✅ | ✅ | Second most popular |
| J2Store | ✅ | ✅ | ✅ | Simple cart |
| Eshop | ✅ | ⚠️ | ⚠️ | Limited export options |
| PhocaCart | ✅ | ✅ | ✅ | Growing platform |

### WordPress E-commerce

| Platform | Products | Orders | Customers | Notes |
|----------|----------|--------|-----------|-------|
| WooCommerce | ✅ | ✅ | ✅ | #1 WordPress cart |
| Easy Digital Downloads | ✅ | ✅ | ✅ | Digital products focus |
| WP eCommerce | ✅ | ⚠️ | ⚠️ | Legacy platform |

### Standalone Platforms

| Platform | Products | Orders | Customers | Notes |
|----------|----------|--------|-----------|-------|
| Shopify | ✅ | ✅ | ✅ | Standardized export |
| Magento 1/2 | ✅ | ✅ | ✅ | Complex variants |
| BigCommerce | ✅ | ✅ | ✅ | Similar to Shopify |
| OpenCart | ✅ | ✅ | ✅ | Common PHP cart |
| PrestaShop | ✅ | ✅ | ✅ | Popular in EU |
| Squarespace | ✅ | ✅ | ⚠️ | Limited export |
| Ecwid | ✅ | ✅ | ✅ | Embeddable cart |
| Wix | ✅ | ⚠️ | ⚠️ | Limited export |

### Generic Formats

| Format | Description |
|--------|-------------|
| NXP Native | Our canonical format (recommended) |
| Generic CSV | Basic columns, manual mapping |
| Google Merchant | Google Shopping feed format |

---

## Phase 1: Database & Architecture

### 1.1 Import Job Tracking

```sql
-- Track import/export jobs for progress and debugging
CREATE TABLE `#__nxp_easycart_import_jobs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('import', 'export') NOT NULL,
    `entity` ENUM('products', 'orders', 'customers', 'categories') NOT NULL,
    `source_format` VARCHAR(50) NOT NULL,           -- 'woocommerce', 'shopify', 'native', etc.
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    `total_rows` INT UNSIGNED DEFAULT 0,
    `processed_rows` INT UNSIGNED DEFAULT 0,
    `success_count` INT UNSIGNED DEFAULT 0,
    `error_count` INT UNSIGNED DEFAULT 0,
    `warning_count` INT UNSIGNED DEFAULT 0,
    `file_path` VARCHAR(500) DEFAULT NULL,          -- Uploaded file location
    `result_file` VARCHAR(500) DEFAULT NULL,        -- Export output or error log
    `options` JSON DEFAULT NULL,                    -- Import options (update existing, etc.)
    `errors` JSON DEFAULT NULL,                     -- Detailed error log
    `user_id` INT UNSIGNED NOT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Field mapping presets for reuse
CREATE TABLE `#__nxp_easycart_import_mappings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `source_format` VARCHAR(50) NOT NULL,
    `entity` VARCHAR(50) NOT NULL,
    `mapping` JSON NOT NULL,                        -- Column mappings
    `transformations` JSON DEFAULT NULL,            -- Value transformations
    `is_default` TINYINT(1) DEFAULT 0,
    `created` DATETIME NOT NULL,
    `modified` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_format_entity` (`source_format`, `entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Service Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     ImportExportService                      │
│  - uploadFile()                                             │
│  - detectFormat()                                           │
│  - startImport()                                            │
│  - startExport()                                            │
│  - getJobStatus()                                           │
│  - cancelJob()                                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      FormatDetector                          │
│  - detectByHeaders()                                        │
│  - detectByContent()                                        │
│  - suggestMapping()                                         │
└─────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ ProductImporter │  │ OrderExporter   │  │ CustomerImporter│
│                 │  │                 │  │                 │
│ Adapters:       │  │ Adapters:       │  │ Adapters:       │
│ - WooCommerce   │  │ - Native        │  │ - Native        │
│ - Shopify       │  │ - QuickBooks    │  │ - Mailchimp     │
│ - VirtueMart    │  │ - Xero          │  │ - Generic       │
│ - Native        │  │ - Generic       │  │                 │
│ - Generic       │  │                 │  │                 │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

---

## Phase 2: NXP Native Format (Canonical)

### 2.1 Product Export Format

This is our canonical format. All imports are normalized to this structure internally.

**Filename:** `nxp_products_export_YYYYMMDD.csv`

```csv
id,sku,title,slug,short_description,long_description,product_type,active,featured,primary_category,categories,tags,images,variant_sku,variant_price,variant_compare_price,variant_currency,variant_stock,variant_weight,variant_options,variant_is_digital,digital_files,seo_title,seo_description,created,modified
1,TSHIRT-BLK-M,"Classic T-Shirt",classic-t-shirt,"Comfortable cotton tee","<p>100% organic cotton...</p>",physical,1,0,clothing,"clothing,mens,tops","cotton,summer","https://example.com/img1.jpg|https://example.com/img2.jpg",TSHIRT-BLK-M,2500,,USD,50,0.200,"{""color"":""Black"",""size"":""M""}",0,,"Classic T-Shirt | Your Store","Shop our classic cotton t-shirt",2025-01-01 10:00:00,2025-01-15 14:30:00
1,TSHIRT-BLK-L,"Classic T-Shirt",classic-t-shirt,"Comfortable cotton tee","<p>100% organic cotton...</p>",physical,1,0,clothing,"clothing,mens,tops","cotton,summer","https://example.com/img1.jpg|https://example.com/img2.jpg",TSHIRT-BLK-L,2500,,USD,35,0.210,"{""color"":""Black"",""size"":""L""}",0,,"Classic T-Shirt | Your Store","Shop our classic cotton t-shirt",2025-01-01 10:00:00,2025-01-15 14:30:00
2,EBOOK-VUE,"Vue.js Mastery eBook",vuejs-mastery-ebook,"Learn Vue.js from scratch","<p>Comprehensive guide...</p>",digital,1,1,ebooks,"ebooks,programming",vue,"https://example.com/cover.jpg",EBOOK-VUE,1999,,USD,999,0,{},1,"vuejs-mastery-v2.pdf|bonus-cheatsheet.pdf","Vue.js Mastery eBook","The complete guide to Vue.js development",2025-01-10 09:00:00,2025-01-10 09:00:00
```

### 2.2 Column Definitions

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | int | No | Internal ID (for updates, leave empty for new) |
| `sku` | string | Yes | Unique product/variant identifier |
| `title` | string | Yes | Product name |
| `slug` | string | No | URL slug (auto-generated if empty) |
| `short_description` | string | No | Brief description |
| `long_description` | string | No | Full HTML description |
| `product_type` | enum | No | `physical` or `digital` (default: physical) |
| `active` | bool | No | Published status (default: 1) |
| `featured` | bool | No | Featured product flag (default: 0) |
| `primary_category` | string | No | Main category slug |
| `categories` | string | No | Comma-separated category slugs |
| `tags` | string | No | Comma-separated tags |
| `images` | string | No | Pipe-separated image URLs |
| `variant_sku` | string | Yes | Variant SKU (can match product SKU for simple products) |
| `variant_price` | int | Yes | Price in cents |
| `variant_compare_price` | int | No | Original/compare-at price in cents |
| `variant_currency` | string | No | Currency code (default: store currency) |
| `variant_stock` | int | No | Stock quantity (default: 0) |
| `variant_weight` | decimal | No | Weight in kg |
| `variant_options` | json | No | Variant attributes as JSON |
| `variant_is_digital` | bool | No | Digital variant flag |
| `digital_files` | string | No | Pipe-separated digital file names |
| `seo_title` | string | No | Meta title |
| `seo_description` | string | No | Meta description |
| `created` | datetime | No | Creation timestamp |
| `modified` | datetime | No | Last modified timestamp |

### 2.3 Order Export Format

**Filename:** `nxp_orders_export_YYYYMMDD.csv`

```csv
order_no,status,email,currency,subtotal,tax,shipping,discount,total,payment_method,billing_name,billing_address1,billing_address2,billing_city,billing_region,billing_postcode,billing_country,shipping_name,shipping_address1,shipping_address2,shipping_city,shipping_region,shipping_postcode,shipping_country,items,tracking_carrier,tracking_number,tracking_url,notes,created,modified
ORD-20250115-001,paid,john@example.com,USD,5000,900,500,0,6400,stripe,"John Doe","123 Main St",,"New York",NY,10001,US,"John Doe","123 Main St",,"New York",NY,10001,US,"TSHIRT-BLK-M:2:2500|EBOOK-VUE:1:1999",,,,"Customer requested gift wrapping",2025-01-15 10:30:00,2025-01-15 10:35:00
ORD-20250115-002,fulfilled,jane@example.com,USD,2500,450,500,250,3200,paypal,"Jane Smith","456 Oak Ave",Apt 2B,"Los Angeles",CA,90001,US,"Jane Smith","456 Oak Ave",Apt 2B,"Los Angeles",CA,90001,US,"TSHIRT-BLK-L:1:2500",UPS,1Z999AA10123456784,https://ups.com/track/1Z999AA10123456784,,2025-01-14 16:20:00,2025-01-15 09:00:00
```

### 2.4 Customer Export Format

**Filename:** `nxp_customers_export_YYYYMMDD.csv`

```csv
email,first_name,last_name,phone,company,address1,address2,city,region,postcode,country,total_orders,total_spent,currency,tags,notes,accepts_marketing,created,last_order_date
john@example.com,John,Doe,+1-555-0123,Acme Inc,"123 Main St",,"New York",NY,10001,US,5,32500,USD,"vip,wholesale","Prefers express shipping",1,2024-06-15 10:00:00,2025-01-15 10:30:00
jane@example.com,Jane,Smith,+1-555-0456,,"456 Oak Ave",Apt 2B,"Los Angeles",CA,90001,US,2,5700,USD,,,0,2024-12-01 14:30:00,2025-01-14 16:20:00
```

---

## Phase 3: Platform-Specific Adapters

### 3.1 WooCommerce

**Source:** WooCommerce → Products → Export (native CSV export)

**Sample WooCommerce CSV:**
```csv
ID,Type,SKU,Name,Published,Is featured?,Visibility in catalogue,Short description,Description,Date sale price starts,Date sale price ends,Tax status,Tax class,In stock?,Stock,Low stock amount,Backorders allowed?,Sold individually?,Weight (kg),Length (cm),Width (cm),Height (cm),Allow customer reviews?,Purchase note,Sale price,Regular price,Categories,Tags,Shipping class,Images,Download limit,Download expiry days,Parent,Grouped products,Upsells,Cross-sells,External URL,Button text,Position,Attribute 1 name,Attribute 1 value(s),Attribute 1 visible,Attribute 1 global,Attribute 2 name,Attribute 2 value(s),Attribute 2 visible,Attribute 2 global
123,simple,TSHIRT-001,"Classic T-Shirt",1,0,visible,"Comfortable cotton tee","<p>100% organic cotton t-shirt</p>",,,taxable,,1,50,,0,0,0.2,,,,,,"",2500,Clothing > T-Shirts,"cotton, summer",,"http://example.com/image1.jpg, http://example.com/image2.jpg",-1,-1,,,,,,,0,Color,Black,1,1,Size,M,1,1
```

**Field Mapping:**

| WooCommerce | NXP Easy Cart | Transformation |
|-------------|---------------|----------------|
| `ID` | `id` | Direct (optional) |
| `SKU` | `sku`, `variant_sku` | Direct |
| `Name` | `title` | Direct |
| `Short description` | `short_description` | Direct |
| `Description` | `long_description` | Direct |
| `Published` | `active` | 1/0 → boolean |
| `Is featured?` | `featured` | 1/0 → boolean |
| `Regular price` | `variant_price` | Float → cents |
| `Sale price` | `variant_compare_price` | Float → cents (swap logic) |
| `Stock` | `variant_stock` | Direct |
| `Weight (kg)` | `variant_weight` | Direct |
| `Categories` | `categories` | `>` separator → comma |
| `Tags` | `tags` | Direct |
| `Images` | `images` | Comma → pipe separator |
| `Type` | `product_type` | `simple`/`variable` → physical, `virtual`/`downloadable` → digital |
| `Attribute N name/value` | `variant_options` | Build JSON object |

**WooCommerce Adapter:**

```php
class WooCommerceProductAdapter implements ProductAdapterInterface
{
    public function getFormatId(): string
    {
        return 'woocommerce';
    }

    public function getLabel(): string
    {
        return 'WooCommerce';
    }

    public function detectFormat(array $headers): bool
    {
        $required = ['SKU', 'Name', 'Regular price', 'Categories'];
        $wooSpecific = ['Is featured?', 'Visibility in catalogue', 'Tax status'];

        return count(array_intersect($required, $headers)) >= 3
            && count(array_intersect($wooSpecific, $headers)) >= 1;
    }

    public function normalizeRow(array $row): array
    {
        $price = $this->parsePrice($row['Regular price'] ?? 0);
        $salePrice = $this->parsePrice($row['Sale price'] ?? 0);

        // WooCommerce: sale_price is the active price, regular_price is compare-at
        // NXP: price is active, compare_price is original
        $activePrice = $salePrice > 0 ? $salePrice : $price;
        $comparePrice = $salePrice > 0 ? $price : 0;

        return [
            'sku' => $row['SKU'] ?? '',
            'title' => $row['Name'] ?? '',
            'slug' => $this->generateSlug($row['Name'] ?? ''),
            'short_description' => $row['Short description'] ?? '',
            'long_description' => $row['Description'] ?? '',
            'product_type' => $this->mapProductType($row['Type'] ?? 'simple'),
            'active' => ($row['Published'] ?? '1') === '1',
            'featured' => ($row['Is featured?'] ?? '0') === '1',
            'categories' => $this->parseCategories($row['Categories'] ?? ''),
            'tags' => $row['Tags'] ?? '',
            'images' => $this->parseImages($row['Images'] ?? ''),
            'variant_sku' => $row['SKU'] ?? '',
            'variant_price' => $activePrice,
            'variant_compare_price' => $comparePrice,
            'variant_stock' => (int) ($row['Stock'] ?? 0),
            'variant_weight' => (float) ($row['Weight (kg)'] ?? 0),
            'variant_options' => $this->parseAttributes($row),
        ];
    }

    private function mapProductType(string $type): string
    {
        return in_array($type, ['virtual', 'downloadable']) ? 'digital' : 'physical';
    }

    private function parseCategories(string $categories): array
    {
        // WooCommerce uses "Parent > Child" format
        $cats = array_map('trim', explode(',', $categories));
        $slugs = [];

        foreach ($cats as $cat) {
            $parts = explode('>', $cat);
            $slugs[] = $this->generateSlug(trim(end($parts)));
        }

        return array_unique($slugs);
    }

    private function parseAttributes(array $row): array
    {
        $options = [];

        for ($i = 1; $i <= 10; $i++) {
            $nameKey = "Attribute {$i} name";
            $valueKey = "Attribute {$i} value(s)";

            if (!empty($row[$nameKey]) && !empty($row[$valueKey])) {
                $options[$row[$nameKey]] = $row[$valueKey];
            }
        }

        return $options;
    }
}
```

### 3.2 Shopify

**Source:** Shopify Admin → Products → Export

**Sample Shopify CSV:**
```csv
Handle,Title,Body (HTML),Vendor,Product Category,Type,Tags,Published,Option1 Name,Option1 Value,Option2 Name,Option2 Value,Option3 Name,Option3 Value,Variant SKU,Variant Grams,Variant Inventory Tracker,Variant Inventory Qty,Variant Inventory Policy,Variant Fulfillment Service,Variant Price,Variant Compare At Price,Variant Requires Shipping,Variant Taxable,Variant Barcode,Image Src,Image Position,Image Alt Text,Gift Card,SEO Title,SEO Description,Google Shopping / Google Product Category,Google Shopping / Gender,Google Shopping / Age Group,Google Shopping / MPN,Google Shopping / Condition,Google Shopping / Custom Product,Google Shopping / Custom Label 0,Google Shopping / Custom Label 1,Google Shopping / Custom Label 2,Google Shopping / Custom Label 3,Google Shopping / Custom Label 4,Variant Image,Variant Weight Unit,Variant Tax Code,Cost per item,Included / United States,Price / United States,Compare At Price / United States,Included / International,Price / International,Compare At Price / International,Status
classic-t-shirt,Classic T-Shirt,"<p>100% organic cotton t-shirt</p>",Your Brand,Apparel & Accessories > Clothing > Shirts & Tops,T-Shirts,"cotton, summer",true,Color,Black,Size,M,,,TSHIRT-BLK-M,200,shopify,50,deny,manual,25.00,30.00,true,true,,https://cdn.shopify.com/image1.jpg,1,,false,Classic T-Shirt | Your Store,Shop our classic cotton t-shirt,,,,,,,,,,,,,kg,,,TRUE,25.00,30.00,TRUE,25.00,30.00,active
classic-t-shirt,Classic T-Shirt,"<p>100% organic cotton t-shirt</p>",Your Brand,Apparel & Accessories > Clothing > Shirts & Tops,T-Shirts,"cotton, summer",true,Color,Black,Size,L,,,TSHIRT-BLK-L,210,shopify,35,deny,manual,25.00,30.00,true,true,,https://cdn.shopify.com/image2.jpg,2,,false,,,,,,,,,,,,,,,kg,,,TRUE,25.00,30.00,TRUE,25.00,30.00,active
```

**Field Mapping:**

| Shopify | NXP Easy Cart | Transformation |
|---------|---------------|----------------|
| `Handle` | `slug` | Direct |
| `Title` | `title` | Direct |
| `Body (HTML)` | `long_description` | Direct |
| `Type` | `categories` | Map to category |
| `Tags` | `tags` | Direct |
| `Published` | `active` | true/false → 1/0 |
| `Status` | `active` | active/draft → 1/0 |
| `Variant SKU` | `variant_sku` | Direct |
| `Variant Price` | `variant_price` | Float → cents |
| `Variant Compare At Price` | `variant_compare_price` | Float → cents |
| `Variant Inventory Qty` | `variant_stock` | Direct |
| `Variant Grams` | `variant_weight` | Grams → kg |
| `Option1-3 Name/Value` | `variant_options` | Build JSON |
| `Image Src` | `images` | Collect all rows for product |
| `SEO Title` | `seo_title` | Direct |
| `SEO Description` | `seo_description` | Direct |
| `Variant Requires Shipping` | `product_type` | false → digital |
| `Gift Card` | - | Skip gift cards |

### 3.3 VirtueMart

**Source:** VirtueMart → Products → Export CSV (via extension or manual query)

**Sample VirtueMart CSV:**
```csv
product_id,product_sku,product_name,slug,product_s_desc,product_desc,product_price,product_currency,product_in_stock,product_weight,published,categories,images,customfields
1,TSHIRT-001,"Classic T-Shirt",classic-t-shirt,"Comfortable cotton tee","<p>100% organic cotton</p>",25.00,USD,50,0.200,1,"Clothing|T-Shirts","image1.jpg|image2.jpg","color:Black|size:M"
2,TSHIRT-002,"Classic T-Shirt",classic-t-shirt,"Comfortable cotton tee","<p>100% organic cotton</p>",25.00,USD,35,0.210,1,"Clothing|T-Shirts","image1.jpg|image2.jpg","color:Black|size:L"
```

**Field Mapping:**

| VirtueMart | NXP Easy Cart | Transformation |
|------------|---------------|----------------|
| `product_id` | `id` | Optional reference |
| `product_sku` | `sku`, `variant_sku` | Direct |
| `product_name` | `title` | Direct |
| `slug` | `slug` | Direct |
| `product_s_desc` | `short_description` | Direct |
| `product_desc` | `long_description` | Direct |
| `product_price` | `variant_price` | Float → cents |
| `product_currency` | `variant_currency` | Direct |
| `product_in_stock` | `variant_stock` | Direct |
| `product_weight` | `variant_weight` | Direct |
| `published` | `active` | Direct |
| `categories` | `categories` | Pipe → comma |
| `images` | `images` | Add base URL prefix |
| `customfields` | `variant_options` | Parse key:value pairs |

### 3.4 HikaShop

**Source:** HikaShop → Products → Export

**Sample HikaShop CSV:**
```csv
product_id,product_code,product_name,product_alias,product_description,product_meta_description,product_keywords,product_page_title,product_type,product_published,product_quantity,product_msrp,product_price,product_weight,product_categories,product_images,variant_code,variant_price,variant_quantity,characteristic_1,characteristic_2
1,TSHIRT,Classic T-Shirt,classic-t-shirt,"<p>100% organic cotton</p>","Shop our classic tee","cotton,tshirt","Classic T-Shirt",main,1,0,30.00,25.00,0.200,"Clothing,T-Shirts","image1.jpg,image2.jpg",TSHIRT-BLK-M,25.00,50,Black,M
1,TSHIRT,Classic T-Shirt,classic-t-shirt,"<p>100% organic cotton</p>","Shop our classic tee","cotton,tshirt","Classic T-Shirt",main,1,0,30.00,25.00,0.210,"Clothing,T-Shirts","image1.jpg,image2.jpg",TSHIRT-BLK-L,25.00,35,Black,L
```

### 3.5 Magento

**Source:** Magento Admin → System → Export (or direct CSV export)

**Sample Magento CSV:**
```csv
sku,store_view_code,attribute_set_code,product_type,categories,product_websites,name,description,short_description,weight,product_online,tax_class_name,visibility,price,special_price,special_from_date,special_to_date,url_key,meta_title,meta_keywords,meta_description,base_image,small_image,thumbnail_image,swatch_image,additional_images,qty,out_of_stock_qty,use_config_min_qty,is_qty_decimal,allow_backorders,use_config_backorders,min_cart_qty,use_config_min_sale_qty,max_cart_qty,use_config_max_sale_qty,is_in_stock,notify_on_stock_below,use_config_notify_stock_qty,manage_stock,use_config_manage_stock,use_config_qty_increments,qty_increments,use_config_enable_qty_inc,enable_qty_increments,is_decimal_divided,website_id,configurable_variations,configurable_variation_labels
TSHIRT-BLK-M,,Default,simple,"Default Category/Clothing/T-Shirts",base,"Classic T-Shirt","<p>100% organic cotton</p>","Comfortable cotton tee",0.200,1,Taxable Goods,"Catalog, Search",25.00,,,,,classic-t-shirt,"Classic T-Shirt","cotton,tshirt","Shop our classic tee",image1.jpg,image1.jpg,image1.jpg,,image2.jpg,50,0,1,0,0,1,1,1,0,1,1,,1,0,1,1,0,0,0,1,,,
```

### 3.6 OpenCart

**Source:** OpenCart Admin → Catalog → Products → Export

```csv
product_id,name,categories,sku,upc,ean,jan,isbn,mpn,location,quantity,model,manufacturer,image_name,shipping,price,points,date_added,date_modified,date_available,weight,weight_unit,length,width,height,length_unit,status,tax_class,seo_keyword,description,meta_title,meta_description,meta_keyword,stock_status,store_ids,layout,related,attribute,option,discount,special,download,additional_images
1,"Classic T-Shirt","Clothing > T-Shirts",TSHIRT-BLK-M,,,,,,,50,TSHIRT-BLK-M,,catalog/image1.jpg,1,25.0000,0,2025-01-01,2025-01-15,2025-01-01,0.20000000,kg,0.00,0.00,0.00,cm,1,Taxable Goods,classic-t-shirt,"<p>100% organic cotton</p>","Classic T-Shirt","Shop our classic tee","cotton,tshirt",In Stock,0,,,,,,,catalog/image2.jpg
```

### 3.7 PrestaShop

**Source:** PrestaShop → Catalog → Products → Export

```csv
Product ID;Active (0/1);Name;Categories (x,y,z...);Price tax excl.;Tax rule ID;Cost price;On sale (0/1);Discount amount;Discount percent;Discount from (yyyy-mm-dd);Discount to (yyyy-mm-dd);Reference #;Supplier reference #;Supplier;Brand;EAN13;UPC;MPN;Ecotax;Width;Height;Depth;Weight;Delivery time of in-stock products;Delivery time of out-of-stock products with allowed backorders;Quantity;Minimal quantity;Low stock level;Send me an email when the quantity is below or equal to this level (0/1);Visibility;Additional shipping cost;Unit for base price;Base price;Summary;Description;Tags (x,y,z...);Meta title;Meta keywords;Meta description;Rewritten URL;Label when in stock;Label when backorder allowed;Available for order (0/1);Product availability date;Product creation date;Show price (0/1);Image URLs (x,y,z...);Delete existing images (0/1);Feature (Name:Value:Position:Customized);Available online only (0/1);Condition;Customizable (0/1);Uploadable files (0/1);Text fields (0/1);Out of stock action;Virtual product (0/1);File URL;Number of allowed downloads;Expiration date;Number of days;ID / Name of shop;Advanced Stock Management;Depends on stock;Warehouse;Accessories (x,y,z...)
1;1;Classic T-Shirt;Clothing,T-Shirts;25.00;1;12.50;0;;;;TSHIRT-BLK-M;;;;;;;;0.200;;;50;1;5;0;both;0;;;Comfortable cotton tee;<p>100% organic cotton</p>;cotton,summer;Classic T-Shirt;cotton,tshirt;Shop our classic tee;classic-t-shirt;;;1;;2025-01-01;1;http://example.com/image1.jpg,http://example.com/image2.jpg;0;Color:Black:1:0,Size:M:2:0;0;new;0;0;0;0;0;;;;;0;0;;
```

### 3.8 BigCommerce

**Source:** BigCommerce → Products → Export

```csv
Item Type,Product ID,Product Name,Product Type,Product Code/SKU,Brand Name,Option Set,Option Set Align,Product Description,Price,Cost Price,Retail Price,Sale Price,Fixed Shipping Cost,Free Shipping,Product Warranty,Product Weight,Product Width,Product Height,Product Depth,Allow Purchases?,Product Visible?,Product Availability,Track Inventory,Stock Level,Low Stock Level,Category,Product Image File - 1,Product Image Description - 1,Product Image Is Thumbnail - 1,Product Image Sort - 1,Product Image File - 2,Search Keywords,Page Title,Meta Keywords,Meta Description,Product Condition,Show Product Condition?,Sort Order,Product Tax Class,Product UPC/EAN,Stop Processing Rules,Product URL,Product Custom Fields,Variants
Product,1,Classic T-Shirt,P,TSHIRT-BLK-M,,,,<p>100% organic cotton</p>,25.00,12.50,30.00,,0,N,,0.200,0,0,0,Y,Y,available,Y,50,5,Clothing/T-Shirts,image1.jpg,,Y,0,image2.jpg,cotton summer,Classic T-Shirt,cotton tshirt,Shop our classic tee,New,N,0,Default Tax Class,,N,/classic-t-shirt/,,"[Color:Black,Size:M]"
```

---

## Phase 4: Import Service Implementation

### 4.1 ProductImportService

**Location:** `administrator/components/com_nxpeasycart/src/Service/Import/ProductImportService.php`

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

class ProductImportService
{
    private DatabaseInterface $db;
    private array $adapters = [];
    private array $categoryCache = [];
    private int $batchSize = 100;

    /**
     * Register a format adapter.
     */
    public function registerAdapter(ProductAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->getFormatId()] = $adapter;
    }

    /**
     * Detect CSV format from headers.
     */
    public function detectFormat(array $headers): ?string
    {
        foreach ($this->adapters as $id => $adapter) {
            if ($adapter->detectFormat($headers)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Preview import (first N rows with validation).
     */
    public function preview(string $filePath, string $format, int $limit = 10): array
    {
        $adapter = $this->adapters[$format] ?? throw new \InvalidArgumentException("Unknown format: $format");

        $rows = $this->readCsv($filePath, $limit);
        $preview = [];

        foreach ($rows as $row) {
            $normalized = $adapter->normalizeRow($row);
            $errors = $this->validateRow($normalized);

            $preview[] = [
                'original' => $row,
                'normalized' => $normalized,
                'errors' => $errors,
                'valid' => empty($errors),
            ];
        }

        return $preview;
    }

    /**
     * Execute full import.
     */
    public function import(int $jobId, string $filePath, string $format, array $options = []): ImportResult
    {
        $adapter = $this->adapters[$format] ?? throw new \InvalidArgumentException("Unknown format: $format");

        $result = new ImportResult();
        $result->startTime = microtime(true);

        $options = array_merge([
            'update_existing' => true,      // Update products with matching SKU
            'skip_errors' => true,          // Continue on row errors
            'create_categories' => true,    // Auto-create missing categories
            'download_images' => true,      // Download remote images
            'dry_run' => false,             // Validate without saving
        ], $options);

        // Stream CSV to handle large files
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        $rowNumber = 1;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            try {
                // Map headers to values
                $data = array_combine($headers, $row);

                // Normalize to our format
                $normalized = $adapter->normalizeRow($data);

                // Validate
                $errors = $this->validateRow($normalized);

                if (!empty($errors)) {
                    $result->addError($rowNumber, $errors);

                    if (!$options['skip_errors']) {
                        break;
                    }

                    continue;
                }

                $batch[] = ['row' => $rowNumber, 'data' => $normalized];

                // Process batch
                if (count($batch) >= $this->batchSize) {
                    $this->processBatch($batch, $options, $result);
                    $batch = [];

                    // Update job progress
                    $this->updateJobProgress($jobId, $rowNumber, $result);
                }

            } catch (\Throwable $e) {
                $result->addError($rowNumber, [$e->getMessage()]);

                if (!$options['skip_errors']) {
                    break;
                }
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch, $options, $result);
        }

        fclose($handle);

        $result->endTime = microtime(true);
        $result->totalRows = $rowNumber - 1;

        return $result;
    }

    /**
     * Process a batch of normalized rows.
     */
    private function processBatch(array $batch, array $options, ImportResult $result): void
    {
        if ($options['dry_run']) {
            $result->successCount += count($batch);
            return;
        }

        $this->db->transactionStart();

        try {
            foreach ($batch as $item) {
                $this->importRow($item['data'], $options, $result);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Import a single normalized row.
     */
    private function importRow(array $data, array $options, ImportResult $result): void
    {
        $sku = $data['sku'];
        $variantSku = $data['variant_sku'] ?? $sku;

        // Check for existing product by SKU
        $existingProduct = $this->findProductBySku($sku);
        $existingVariant = $this->findVariantBySku($variantSku);

        if ($existingProduct && !$options['update_existing']) {
            $result->skippedCount++;
            return;
        }

        // Handle categories
        $categoryIds = [];
        if (!empty($data['categories'])) {
            $categoryIds = $this->resolveCategories($data['categories'], $options['create_categories']);
        }

        // Handle images
        $imageUrls = [];
        if (!empty($data['images']) && $options['download_images']) {
            $imageUrls = $this->processImages($data['images']);
        }

        // Upsert product
        $productId = $existingProduct
            ? $this->updateProduct($existingProduct['id'], $data)
            : $this->createProduct($data);

        // Upsert variant
        $variantId = $existingVariant
            ? $this->updateVariant($existingVariant['id'], $data, $productId)
            : $this->createVariant($data, $productId);

        // Link categories
        $this->linkCategories($productId, $categoryIds, $data['primary_category'] ?? null);

        // Save images
        $this->saveImages($productId, $imageUrls);

        $result->successCount++;

        if ($existingProduct) {
            $result->updatedCount++;
        } else {
            $result->createdCount++;
        }
    }

    /**
     * Validate a normalized row.
     */
    private function validateRow(array $data): array
    {
        $errors = [];

        if (empty($data['sku']) && empty($data['variant_sku'])) {
            $errors[] = 'SKU is required';
        }

        if (empty($data['title'])) {
            $errors[] = 'Product title is required';
        }

        if (!isset($data['variant_price']) || $data['variant_price'] < 0) {
            $errors[] = 'Valid price is required';
        }

        if (isset($data['variant_stock']) && $data['variant_stock'] < 0) {
            $errors[] = 'Stock cannot be negative';
        }

        return $errors;
    }

    /**
     * Resolve category slugs to IDs, creating if needed.
     */
    private function resolveCategories(array $slugs, bool $create = true): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            if (isset($this->categoryCache[$slug])) {
                $ids[] = $this->categoryCache[$slug];
                continue;
            }

            $category = $this->findCategoryBySlug($slug);

            if ($category) {
                $this->categoryCache[$slug] = $category['id'];
                $ids[] = $category['id'];
            } elseif ($create) {
                $newId = $this->createCategory($slug);
                $this->categoryCache[$slug] = $newId;
                $ids[] = $newId;
            }
        }

        return $ids;
    }
}
```

### 4.2 ImportResult Value Object

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

class ImportResult implements \JsonSerializable
{
    public int $totalRows = 0;
    public int $successCount = 0;
    public int $createdCount = 0;
    public int $updatedCount = 0;
    public int $skippedCount = 0;
    public int $errorCount = 0;
    public array $errors = [];
    public array $warnings = [];
    public float $startTime = 0;
    public float $endTime = 0;

    public function addError(int $row, array $messages): void
    {
        $this->errors[] = ['row' => $row, 'messages' => $messages];
        $this->errorCount++;
    }

    public function addWarning(int $row, string $message): void
    {
        $this->warnings[] = ['row' => $row, 'message' => $message];
    }

    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }

    public function jsonSerialize(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'success_count' => $this->successCount,
            'created_count' => $this->createdCount,
            'updated_count' => $this->updatedCount,
            'skipped_count' => $this->skippedCount,
            'error_count' => $this->errorCount,
            'errors' => array_slice($this->errors, 0, 100), // Limit for API response
            'warnings' => array_slice($this->warnings, 0, 100),
            'duration_seconds' => round($this->getDuration(), 2),
        ];
    }
}
```

---

## Phase 5: Export Service Implementation

### 5.1 ProductExportService

**Location:** `administrator/components/com_nxpeasycart/src/Service/Export/ProductExportService.php`

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Service\Export;

class ProductExportService
{
    private DatabaseInterface $db;
    private ProductService $products;

    /**
     * Export products to CSV.
     */
    public function export(array $filters = [], string $format = 'native'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'nxp_export_');
        $handle = fopen($tempFile, 'w');

        // Write BOM for Excel UTF-8 compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers
        $headers = $this->getHeaders($format);
        fputcsv($handle, $headers);

        // Stream products
        $offset = 0;
        $limit = 500;

        while (true) {
            $products = $this->fetchProducts($filters, $offset, $limit);

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $rows = $this->formatProduct($product, $format);

                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            $offset += $limit;
        }

        fclose($handle);

        return $tempFile;
    }

    /**
     * Export to specific platform format.
     */
    public function exportForPlatform(string $platform, array $filters = []): string
    {
        return match ($platform) {
            'woocommerce' => $this->exportWooCommerce($filters),
            'shopify' => $this->exportShopify($filters),
            'google_merchant' => $this->exportGoogleMerchant($filters),
            default => $this->export($filters, 'native'),
        };
    }

    private function getHeaders(string $format): array
    {
        return match ($format) {
            'native' => [
                'id', 'sku', 'title', 'slug', 'short_description', 'long_description',
                'product_type', 'active', 'featured', 'primary_category', 'categories', 'tags',
                'images', 'variant_sku', 'variant_price', 'variant_compare_price',
                'variant_currency', 'variant_stock', 'variant_weight', 'variant_options',
                'variant_is_digital', 'digital_files', 'seo_title', 'seo_description',
                'created', 'modified',
            ],
            'minimal' => [
                'sku', 'title', 'price', 'stock', 'categories',
            ],
            default => $this->getHeaders('native'),
        };
    }

    private function formatProduct(array $product, string $format): array
    {
        $rows = [];

        foreach ($product['variants'] as $variant) {
            $rows[] = match ($format) {
                'native' => $this->formatNativeRow($product, $variant),
                'minimal' => $this->formatMinimalRow($product, $variant),
                default => $this->formatNativeRow($product, $variant),
            };
        }

        return $rows;
    }

    private function formatNativeRow(array $product, array $variant): array
    {
        return [
            $product['id'],
            $product['sku'] ?? $variant['sku'],
            $product['title'],
            $product['slug'],
            $product['short_desc'] ?? '',
            $product['long_desc'] ?? '',
            $product['product_type'] ?? 'physical',
            $product['active'] ? '1' : '0',
            $product['featured'] ? '1' : '0',
            $product['primary_category_slug'] ?? '',
            implode(',', $product['category_slugs'] ?? []),
            implode(',', $product['tags'] ?? []),
            implode('|', $product['image_urls'] ?? []),
            $variant['sku'],
            $variant['price_cents'],
            $variant['compare_price_cents'] ?? '',
            $variant['currency'],
            $variant['stock'],
            $variant['weight'] ?? '',
            json_encode($variant['options'] ?? []),
            $variant['is_digital'] ? '1' : '0',
            implode('|', $variant['digital_files'] ?? []),
            $product['seo_title'] ?? '',
            $product['seo_description'] ?? '',
            $product['created'],
            $product['modified'],
        ];
    }
}
```

### 5.2 OrderExportService

```php
<?php
namespace Joomla\Component\Nxpeasycart\Administrator\Service\Export;

class OrderExportService
{
    /**
     * Export orders to CSV for accounting.
     */
    public function export(array $filters = [], string $format = 'native'): string
    {
        // Similar structure to ProductExportService
    }

    /**
     * Export to accounting software formats.
     */
    public function exportForAccounting(string $platform, array $filters = []): string
    {
        return match ($platform) {
            'quickbooks' => $this->exportQuickBooks($filters),
            'xero' => $this->exportXero($filters),
            'sage' => $this->exportSage($filters),
            default => $this->export($filters, 'native'),
        };
    }

    /**
     * QuickBooks IIF format.
     */
    private function exportQuickBooks(array $filters): string
    {
        // QuickBooks-specific format
    }

    /**
     * Xero CSV format.
     */
    private function exportXero(array $filters): string
    {
        // Xero-specific format with their required columns
    }
}
```

---

## Phase 6: Admin UI

### 6.1 Import Wizard

```
┌─────────────────────────────────────────────────────────────┐
│  Import Products                                    Step 1/4 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Upload your product CSV file                               │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │     📁 Drag and drop your CSV file here            │   │
│  │        or click to browse                          │   │
│  │                                                     │   │
│  │     Supported: .csv, .tsv (max 50MB)               │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Or select a platform to see export instructions:           │
│                                                             │
│  [WooCommerce ▾] [Shopify ▾] [VirtueMart ▾] [More... ▾]    │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  📥 Download sample CSV template                            │
│                                                             │
│                                        [Cancel] [Next →]    │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Format Detection & Mapping (Step 2)

```
┌─────────────────────────────────────────────────────────────┐
│  Import Products                                    Step 2/4 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✅ File uploaded: products_export.csv (2.3 MB, 1,247 rows) │
│                                                             │
│  Detected format: WooCommerce                               │
│  [Change format ▾]                                          │
│                                                             │
│  Column Mapping                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Your CSV Column        →    NXP Easy Cart Field     │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │ SKU                    →    [SKU ▾]            ✅   │   │
│  │ Name                   →    [Title ▾]          ✅   │   │
│  │ Regular price          →    [Price ▾]          ✅   │   │
│  │ Description            →    [Long Description ▾] ✅  │   │
│  │ Categories             →    [Categories ▾]      ✅   │   │
│  │ Stock                  →    [Stock ▾]          ✅   │   │
│  │ Weight (kg)            →    [Weight ▾]         ✅   │   │
│  │ custom_field_1         →    [-- Skip -- ▾]     ⚠️   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  💾 Save this mapping as preset: [________________] [Save]  │
│                                                             │
│                              [← Back] [Cancel] [Next →]     │
└─────────────────────────────────────────────────────────────┘
```

### 6.3 Preview & Options (Step 3)

```
┌─────────────────────────────────────────────────────────────┐
│  Import Products                                    Step 3/4 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Preview (first 5 rows)                                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SKU          Title              Price    Stock      │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │ TSHIRT-001   Classic T-Shirt    $25.00   50    ✅   │   │
│  │ TSHIRT-002   Classic T-Shirt    $25.00   35    ✅   │   │
│  │ MUG-001      Coffee Mug         $12.00   100   ✅   │   │
│  │ ❌ ROW-ERR   [Missing title]    $10.00   20    ❌   │   │
│  │ POSTER-001   Vintage Poster     $18.00   25    ✅   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Import Options                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ☑ Update existing products (match by SKU)          │   │
│  │ ☑ Create missing categories automatically          │   │
│  │ ☑ Download and save remote images                  │   │
│  │ ☐ Skip rows with errors (continue import)          │   │
│  │ ☐ Dry run (validate only, don't save)              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Summary: 1,247 rows • 1,246 valid • 1 error               │
│                                                             │
│                              [← Back] [Cancel] [Import →]   │
└─────────────────────────────────────────────────────────────┘
```

### 6.4 Progress & Results (Step 4)

```
┌─────────────────────────────────────────────────────────────┐
│  Import Products                                    Step 4/4 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✅ Import Complete!                                        │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │  📦 Products Created:     892                       │   │
│  │  🔄 Products Updated:     354                       │   │
│  │  ⏭️ Rows Skipped:         0                         │   │
│  │  ❌ Errors:               1                         │   │
│  │                                                     │   │
│  │  ⏱️ Duration:             23.4 seconds              │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Errors (1)                                        [Expand] │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Row 847: Missing required field 'title'            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  📥 Download error log                                      │
│                                                             │
│                    [Import Another File] [Go to Products]   │
└─────────────────────────────────────────────────────────────┘
```

### 6.5 Export Panel

```
┌─────────────────────────────────────────────────────────────┐
│  Export Data                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  What to export:                                            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ○ Products (1,246 total)                            │   │
│  │ ○ Orders (523 total)                                │   │
│  │ ○ Customers (412 total)                             │   │
│  │ ○ Categories (24 total)                             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Format:                                                    │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ [NXP Native (recommended) ▾]                        │   │
│  │                                                     │   │
│  │ Other formats:                                      │   │
│  │ • WooCommerce compatible                            │   │
│  │ • Shopify compatible                                │   │
│  │ • Google Merchant Center                            │   │
│  │ • QuickBooks (orders only)                          │   │
│  │ • Xero (orders only)                                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Filters:                                                   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Status: [All ▾]  Category: [All ▾]                  │   │
│  │ Date range: [Last 30 days ▾]                        │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│                                          [Export to CSV →]  │
└─────────────────────────────────────────────────────────────┘
```

---

## Phase 7: API Endpoints

### 7.1 Import Endpoints

```
POST /administrator/index.php?option=com_nxpeasycart&task=api.import.upload
Content-Type: multipart/form-data
- file: CSV file
- entity: products|orders|customers
Response: { job_id, detected_format, headers, preview }

POST /administrator/index.php?option=com_nxpeasycart&task=api.import.start
Content-Type: application/json
{
    "job_id": 123,
    "format": "woocommerce",
    "mapping": { ... },
    "options": {
        "update_existing": true,
        "create_categories": true,
        "skip_errors": true
    }
}
Response: { job_id, status: "processing" }

GET /administrator/index.php?option=com_nxpeasycart&task=api.import.status&job_id=123
Response: {
    "job_id": 123,
    "status": "processing",
    "progress": 45,
    "processed_rows": 560,
    "total_rows": 1247,
    "success_count": 558,
    "error_count": 2
}

POST /administrator/index.php?option=com_nxpeasycart&task=api.import.cancel&job_id=123
Response: { success: true }
```

### 7.2 Export Endpoints

```
POST /administrator/index.php?option=com_nxpeasycart&task=api.export.start
Content-Type: application/json
{
    "entity": "products",
    "format": "native",
    "filters": {
        "status": "active",
        "category_id": 5
    }
}
Response: { job_id, status: "processing" }

GET /administrator/index.php?option=com_nxpeasycart&task=api.export.download&job_id=123
Response: CSV file download
```

---

## Phase 8: Platform-Specific Instructions

### 8.1 WooCommerce Export Instructions

Display in UI when user selects WooCommerce:

```markdown
## Exporting from WooCommerce

1. Go to **WooCommerce → Products**
2. Click **Export** at the top of the page
3. Select columns to export (recommended: all)
4. Click **Generate CSV**
5. Download the file and upload it here

### Supported Fields
- Product name, SKU, description
- Regular and sale prices
- Stock quantity
- Categories and tags
- Images (URLs)
- Attributes/variations
- Weight and dimensions

### Notes
- Variable products will be imported with all variations
- Image URLs will be downloaded automatically
- Categories will be created if they don't exist
```

### 8.2 Shopify Export Instructions

```markdown
## Exporting from Shopify

1. Go to **Products** in your Shopify admin
2. Click **Export** at the top
3. Select **All products** or use filters
4. Choose **CSV for Excel, Numbers, or other spreadsheet programs**
5. Click **Export products**
6. Download from email and upload here

### Supported Fields
- Title, description, vendor
- Price and compare-at price
- Inventory quantity
- Variants with options
- Images
- SEO fields
- Tags

### Notes
- Gift cards are not imported
- Shopify's multi-row variant format is fully supported
```

### 8.3 VirtueMart Export Instructions

```markdown
## Exporting from VirtueMart

### Option 1: Using CSV Export Extension
1. Install a VirtueMart CSV export extension (e.g., VM CSV Import/Export)
2. Go to **Components → VirtueMart → Tools → Export**
3. Select products to export
4. Download CSV

### Option 2: Manual Database Export
1. Access phpMyAdmin
2. Run the following query:
   ```sql
   SELECT p.*, pd.product_name, pd.product_s_desc, pd.product_desc
   FROM #__virtuemart_products p
   JOIN #__virtuemart_products_xx_xx pd ON p.virtuemart_product_id = pd.virtuemart_product_id
   ```
3. Export results as CSV

### Supported Fields
- Product name, SKU, descriptions
- Prices and currencies
- Stock levels
- Categories
- Custom fields (as variant options)
```

---

## Phase 9: Language Strings

```ini
; Import/Export
COM_NXPEASYCART_IMPORT_TITLE="Import Products"
COM_NXPEASYCART_IMPORT_UPLOAD="Upload CSV File"
COM_NXPEASYCART_IMPORT_UPLOAD_DESC="Drag and drop your CSV file or click to browse"
COM_NXPEASYCART_IMPORT_SUPPORTED_FORMATS="Supported: .csv, .tsv (max %s)"
COM_NXPEASYCART_IMPORT_DOWNLOAD_TEMPLATE="Download sample CSV template"
COM_NXPEASYCART_IMPORT_SELECT_PLATFORM="Or select a platform to see export instructions"
COM_NXPEASYCART_IMPORT_DETECTED_FORMAT="Detected format: %s"
COM_NXPEASYCART_IMPORT_CHANGE_FORMAT="Change format"
COM_NXPEASYCART_IMPORT_COLUMN_MAPPING="Column Mapping"
COM_NXPEASYCART_IMPORT_SAVE_MAPPING="Save this mapping as preset"
COM_NXPEASYCART_IMPORT_PREVIEW="Preview (first %d rows)"
COM_NXPEASYCART_IMPORT_OPTIONS="Import Options"
COM_NXPEASYCART_IMPORT_OPT_UPDATE_EXISTING="Update existing products (match by SKU)"
COM_NXPEASYCART_IMPORT_OPT_CREATE_CATEGORIES="Create missing categories automatically"
COM_NXPEASYCART_IMPORT_OPT_DOWNLOAD_IMAGES="Download and save remote images"
COM_NXPEASYCART_IMPORT_OPT_SKIP_ERRORS="Skip rows with errors (continue import)"
COM_NXPEASYCART_IMPORT_OPT_DRY_RUN="Dry run (validate only, don't save)"
COM_NXPEASYCART_IMPORT_SUMMARY="Summary: %d rows • %d valid • %d errors"
COM_NXPEASYCART_IMPORT_COMPLETE="Import Complete!"
COM_NXPEASYCART_IMPORT_CREATED="Products Created"
COM_NXPEASYCART_IMPORT_UPDATED="Products Updated"
COM_NXPEASYCART_IMPORT_SKIPPED="Rows Skipped"
COM_NXPEASYCART_IMPORT_ERRORS="Errors"
COM_NXPEASYCART_IMPORT_DURATION="Duration"
COM_NXPEASYCART_IMPORT_DOWNLOAD_ERROR_LOG="Download error log"
COM_NXPEASYCART_IMPORT_ANOTHER="Import Another File"

COM_NXPEASYCART_EXPORT_TITLE="Export Data"
COM_NXPEASYCART_EXPORT_WHAT="What to export"
COM_NXPEASYCART_EXPORT_PRODUCTS="Products (%d total)"
COM_NXPEASYCART_EXPORT_ORDERS="Orders (%d total)"
COM_NXPEASYCART_EXPORT_CUSTOMERS="Customers (%d total)"
COM_NXPEASYCART_EXPORT_CATEGORIES="Categories (%d total)"
COM_NXPEASYCART_EXPORT_FORMAT="Format"
COM_NXPEASYCART_EXPORT_FORMAT_NATIVE="NXP Native (recommended)"
COM_NXPEASYCART_EXPORT_FILTERS="Filters"
COM_NXPEASYCART_EXPORT_BUTTON="Export to CSV"

; Platform names
COM_NXPEASYCART_PLATFORM_WOOCOMMERCE="WooCommerce"
COM_NXPEASYCART_PLATFORM_SHOPIFY="Shopify"
COM_NXPEASYCART_PLATFORM_VIRTUEMART="VirtueMart"
COM_NXPEASYCART_PLATFORM_HIKASHOP="HikaShop"
COM_NXPEASYCART_PLATFORM_J2STORE="J2Store"
COM_NXPEASYCART_PLATFORM_MAGENTO="Magento"
COM_NXPEASYCART_PLATFORM_OPENCART="OpenCart"
COM_NXPEASYCART_PLATFORM_PRESTASHOP="PrestaShop"
COM_NXPEASYCART_PLATFORM_BIGCOMMERCE="BigCommerce"
COM_NXPEASYCART_PLATFORM_SQUARESPACE="Squarespace"
COM_NXPEASYCART_PLATFORM_ECWID="Ecwid"
COM_NXPEASYCART_PLATFORM_NATIVE="NXP Native"
COM_NXPEASYCART_PLATFORM_GENERIC="Generic CSV"

; Errors
COM_NXPEASYCART_IMPORT_ERROR_FILE_TYPE="Invalid file type. Please upload a CSV file."
COM_NXPEASYCART_IMPORT_ERROR_FILE_SIZE="File too large. Maximum size is %s."
COM_NXPEASYCART_IMPORT_ERROR_EMPTY_FILE="The uploaded file is empty."
COM_NXPEASYCART_IMPORT_ERROR_INVALID_FORMAT="Could not detect CSV format. Please check your file."
COM_NXPEASYCART_IMPORT_ERROR_MISSING_SKU="Row %d: SKU is required."
COM_NXPEASYCART_IMPORT_ERROR_MISSING_TITLE="Row %d: Product title is required."
COM_NXPEASYCART_IMPORT_ERROR_INVALID_PRICE="Row %d: Invalid price value."
COM_NXPEASYCART_IMPORT_ERROR_DUPLICATE_SKU="Row %d: Duplicate SKU '%s'."
```

---

## Implementation Order

| Phase | Scope | Effort | Dependencies |
|-------|-------|--------|--------------|
| **Phase 1** | Database & architecture | ~2 hours | None |
| **Phase 2** | NXP native format | ~2 hours | Phase 1 |
| **Phase 3** | Platform adapters (WooCommerce, Shopify, VirtueMart) | ~6-8 hours | Phase 2 |
| **Phase 4** | Import service | ~4-5 hours | Phase 2, 3 |
| **Phase 5** | Export service | ~3-4 hours | Phase 2 |
| **Phase 6** | Admin UI (wizard) | ~4-5 hours | Phase 4, 5 |
| **Phase 7** | API endpoints | ~2 hours | Phase 4, 5 |
| **Phase 8** | Platform instructions | ~2 hours | Phase 3 |
| **Phase 9** | Additional adapters (Magento, OpenCart, etc.) | ~4-6 hours | Phase 3 |

**Total estimate: ~29-36 hours**

---

## Testing Checklist

### Import Tests
- [ ] Upload CSV file via drag-drop
- [ ] Format auto-detection works
- [ ] Manual format selection works
- [ ] Column mapping UI functions correctly
- [ ] Preview shows correct data
- [ ] Dry run validates without saving
- [ ] Full import creates products
- [ ] Update existing products by SKU
- [ ] Categories created automatically
- [ ] Images downloaded from URLs
- [ ] Variants imported correctly
- [ ] Progress updates during import
- [ ] Error handling and logging
- [ ] Large file handling (10k+ rows)

### Export Tests
- [ ] Products export to CSV
- [ ] Orders export to CSV
- [ ] Customers export to CSV
- [ ] Filters work correctly
- [ ] File downloads properly
- [ ] UTF-8 encoding correct
- [ ] Excel opens file correctly

### Platform Adapter Tests
- [ ] WooCommerce import
- [ ] Shopify import
- [ ] VirtueMart import
- [ ] HikaShop import
- [ ] Magento import
- [ ] OpenCart import
- [ ] PrestaShop import

---

## Future Enhancements

1. **Scheduled Imports** - Auto-import from URL/FTP on schedule
2. **Google Sheets Integration** - Direct import from Google Sheets
3. **Inventory Sync** - Two-way sync with warehouse systems
4. **Multi-language Import** - Import translations alongside products
5. **Image Optimization** - Compress/resize images on import
6. **Webhook Export** - Push exports to external systems
7. **API Import** - Direct API-to-API migration from platforms
8. **Rollback** - Undo recent imports
9. **Change Detection** - Show diff before updating existing products
10. **Bulk Price Updates** - CSV for price/stock updates only
