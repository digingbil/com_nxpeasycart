-- NXP Easy Cart 0.1.9 migration
-- Adds needs_review flag for payment amount mismatch detection

ALTER TABLE `#__nxp_easycart_orders`
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_method`,
  ADD COLUMN `review_reason` VARCHAR(255) NULL AFTER `needs_review`,
  ADD INDEX `idx_nxp_orders_needs_review` (`needs_review`);
