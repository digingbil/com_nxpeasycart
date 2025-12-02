-- Add payment_method column to track which gateway was used for checkout
ALTER TABLE `#__nxp_easycart_orders`
  ADD COLUMN `payment_method` VARCHAR(32) NULL AFTER `state`,
  ADD INDEX `idx_nxp_orders_payment_method` (`payment_method`);
