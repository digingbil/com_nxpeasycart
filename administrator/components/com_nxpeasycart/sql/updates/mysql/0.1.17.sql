-- NXP Easy Cart 0.1.17 - Add EAN column to variants table

ALTER TABLE `#__nxp_easycart_variants`
  ADD COLUMN `ean` VARCHAR(13) NULL DEFAULT NULL AFTER `sku`;

-- Index for EAN lookups (optional, useful for barcode scanning)
ALTER TABLE `#__nxp_easycart_variants`
  ADD KEY `idx_nxp_variants_ean` (`ean`);
