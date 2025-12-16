-- NXP Easy Cart 0.3.1 - Variant Images
-- Add images column to variants table for per-variant image support

ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `images` JSON NULL DEFAULT NULL COMMENT 'Variant-specific images array (overrides product images)' AFTER `options`;
