-- NXP Easy Cart 0.2.0 - Sale Price Implementation
-- Add sale pricing fields to variants table

ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `sale_price_cents` INT NULL DEFAULT NULL AFTER `price_cents`,
  ADD COLUMN `sale_start` DATETIME NULL DEFAULT NULL AFTER `sale_price_cents`,
  ADD COLUMN `sale_end` DATETIME NULL DEFAULT NULL AFTER `sale_start`;

-- Composite index for efficient "active sales" queries
ALTER TABLE `#__nxp_easycart_variants`
  ADD INDEX `idx_nxp_variants_sale_active` (`sale_start`, `sale_end`);

-- Add coupon option to control applicability to sale items
ALTER TABLE `#__nxp_easycart_coupons`
  ADD COLUMN `allow_sale_items` TINYINT(1) NOT NULL DEFAULT 1 AFTER `times_used`;
