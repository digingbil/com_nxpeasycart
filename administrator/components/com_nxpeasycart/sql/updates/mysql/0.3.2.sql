-- NXP Easy Cart 0.3.2 - Remove variant-level is_digital
-- Digital products are now determined solely by products.product_type
-- The is_digital flag in order_items is still preserved (derived from product_type at checkout)

ALTER TABLE `#__nxp_easycart_variants`
  DROP COLUMN `is_digital`;
