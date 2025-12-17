-- NXP Easy Cart 0.3.0 - Import/Export Feature
-- Add import tracking columns to products and variants
-- Create import_jobs table for job management

-- Add source_images column to products table (needed for import feature)
ALTER TABLE `#__nxp_easycart_products`
  ADD COLUMN `source_images` JSON NULL AFTER `images`;

-- Add import tracking columns to products table
ALTER TABLE `#__nxp_easycart_products`
  ADD COLUMN `imported_from` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Source platform: virtuemart, woocommerce, shopify, hikashop, native' AFTER `source_images`,
  ADD COLUMN `original_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Original product ID from source platform' AFTER `imported_from`,
  ADD INDEX `idx_nxp_products_imported_from` (`imported_from`);

-- Add import tracking columns to variants table
ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `imported_from` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Source platform: virtuemart, woocommerce, shopify, hikashop, native' AFTER `active`,
  ADD COLUMN `original_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Original variant ID from source platform' AFTER `imported_from`,
  ADD COLUMN `original_images` JSON NULL DEFAULT NULL COMMENT 'JSON array of original image URLs (not downloaded)' AFTER `original_id`,
  ADD INDEX `idx_nxp_variants_imported_from` (`imported_from`);

-- Add import tracking column to categories table
ALTER TABLE `#__nxp_easycart_categories`
  ADD COLUMN `imported_from` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Source platform: virtuemart, woocommerce, shopify, hikashop, native' AFTER `sort`,
  ADD INDEX `idx_nxp_categories_imported_from` (`imported_from`);

-- Create import/export jobs table
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_import_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `original_filename` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Original uploaded filename for display',
  `file_hash` CHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 hash to detect re-uploads',
  `mapping` JSON NULL DEFAULT NULL COMMENT 'JSON column mapping configuration',
  `options` JSON NULL DEFAULT NULL COMMENT 'JSON import options (create categories, update existing, etc)',
  `created_by` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL DEFAULT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_nxp_import_jobs_status` (`status`),
  INDEX `idx_nxp_import_jobs_created_by` (`created_by`),
  INDEX `idx_nxp_import_jobs_created` (`created`),
  INDEX `idx_nxp_import_jobs_file_hash` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
