-- NXP Easy Cart 0.2.1 - Per-User Coupon Usage Limits
-- Add per-user limit column to coupons table

ALTER TABLE `#__nxp_easycart_coupons`
  ADD COLUMN `max_uses_per_user` INT UNSIGNED NULL DEFAULT NULL AFTER `max_uses`;

-- Create usage tracking table for per-user coupon usage
CREATE TABLE IF NOT EXISTS `#__nxp_easycart_coupon_usage` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coupon_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `guest_email` VARCHAR(255) NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_coupon_usage_coupon` (`coupon_id`),
  KEY `idx_nxp_coupon_usage_user` (`coupon_id`, `user_id`),
  KEY `idx_nxp_coupon_usage_email` (`coupon_id`, `guest_email`(100)),
  KEY `idx_nxp_coupon_usage_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
