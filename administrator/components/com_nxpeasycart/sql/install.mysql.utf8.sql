-- NXP Easy Cart install schema

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `short_desc` TEXT NULL,
  `long_desc` MEDIUMTEXT NULL,
  `images` JSON NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `primary_category_id` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  `modified` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `modified_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_products_slug` (`slug`),
  KEY `idx_nxp_products_primary_category` (`primary_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_categories_slug` (`slug`),
  KEY `idx_nxp_categories_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_product_categories` (
  `product_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `category_id`),
  CONSTRAINT `fk_nxp_product_categories_product`
    FOREIGN KEY (`product_id`) REFERENCES `#__nxp_easycart_products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_nxp_product_categories_category`
    FOREIGN KEY (`category_id`) REFERENCES `#__nxp_easycart_categories` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__nxp_easycart_products`
  ADD CONSTRAINT `fk_nxp_products_primary_category`
    FOREIGN KEY (`primary_category_id`) REFERENCES `#__nxp_easycart_categories` (`id`)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(64) NOT NULL,
  `price_cents` INT NOT NULL,
  `currency` CHAR(3) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `options` JSON NULL,
  `weight` DECIMAL(10,3) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_variants_sku` (`sku`),
  KEY `idx_nxp_variants_product` (`product_id`),
  CONSTRAINT `fk_nxp_variants_product`
    FOREIGN KEY (`product_id`) REFERENCES `#__nxp_easycart_products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(40) NOT NULL,
  `public_token` CHAR(64) NOT NULL DEFAULT '',
  `user_id` INT UNSIGNED NULL,
  `email` VARCHAR(255) NOT NULL,
  `billing` JSON NOT NULL,
  `shipping` JSON NULL,
  `subtotal_cents` INT NOT NULL DEFAULT 0,
  `tax_cents` INT NOT NULL DEFAULT 0,
  `shipping_cents` INT NOT NULL DEFAULT 0,
  `discount_cents` INT NOT NULL DEFAULT 0,
  `total_cents` INT NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL,
  `state` ENUM('cart','pending','paid','fulfilled','refunded','canceled') NOT NULL DEFAULT 'cart',
  `payment_method` VARCHAR(32) NULL,
  `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
  `review_reason` VARCHAR(255) NULL,
  `status_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locale` VARCHAR(10) NOT NULL DEFAULT 'en-GB',
  `carrier` VARCHAR(50) NULL,
  `tracking_number` VARCHAR(64) NULL,
  `tracking_url` VARCHAR(255) NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `fulfillment_events` JSON NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_orders_order_no` (`order_no`),
  UNIQUE KEY `idx_nxp_orders_public_token` (`public_token`),
  KEY `idx_nxp_orders_user` (`user_id`),
  KEY `idx_nxp_orders_state` (`state`),
  KEY `idx_nxp_orders_payment_method` (`payment_method`),
  KEY `idx_nxp_orders_needs_review` (`needs_review`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `variant_id` INT UNSIGNED NULL,
  `sku` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `qty` INT NOT NULL DEFAULT 1,
  `unit_price_cents` INT NOT NULL,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `total_cents` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_order_items_order` (`order_id`),
  CONSTRAINT `fk_nxp_order_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `#__nxp_easycart_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `gateway` VARCHAR(64) NOT NULL,
  `ext_id` VARCHAR(128) NULL,
  `status` VARCHAR(32) NOT NULL,
  `amount_cents` INT NOT NULL,
  `payload` JSON NULL,
  `event_idempotency_key` VARCHAR(128) NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_transactions_order` (`order_id`),
  UNIQUE KEY `idx_nxp_transactions_external` (`gateway`, `ext_id`),
  UNIQUE KEY `idx_nxp_transactions_idempotency` (`gateway`, `event_idempotency_key`),
  CONSTRAINT `fk_nxp_transactions_order`
    FOREIGN KEY (`order_id`) REFERENCES `#__nxp_easycart_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_total_cents` INT NULL,
  `start` DATETIME NULL,
  `end` DATETIME NULL,
  `max_uses` INT NULL,
  `times_used` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `context` JSON NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_audit_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_tax_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country` CHAR(2) NOT NULL,
  `region` VARCHAR(32) NULL,
  `rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `inclusive` TINYINT(1) NOT NULL DEFAULT 0,
  `priority` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_tax_rates_country_region` (`country`, `region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_shipping_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `type` ENUM('flat','free_over') NOT NULL DEFAULT 'flat',
  `price_cents` INT NOT NULL DEFAULT 0,
  `threshold_cents` INT NULL,
  `regions` JSON NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_nxp_shipping_rules_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_settings` (
  `key` VARCHAR(190) NOT NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__nxp_easycart_carts` (
  `id` CHAR(36) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `session_id` VARCHAR(128) NULL,
  `data` JSON NULL,
  `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_nxp_carts_session` (`session_id`),
  KEY `idx_nxp_carts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
