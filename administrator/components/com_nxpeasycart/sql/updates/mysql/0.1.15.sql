ALTER TABLE `#__nxp_easycart_products`
    ADD COLUMN `checked_out` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `modified_by`,
    ADD COLUMN `checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`;

ALTER TABLE `#__nxp_easycart_categories`
    ADD COLUMN `checked_out` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `sort`,
    ADD COLUMN `checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`;

ALTER TABLE `#__nxp_easycart_orders`
    ADD COLUMN `checked_out` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `fulfillment_events`,
    ADD COLUMN `checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`;
