ALTER TABLE `#__nxp_easycart_products`
  ADD COLUMN `primary_category_id` INT UNSIGNED NULL AFTER `active`,
  ADD INDEX `idx_nxp_products_primary_category` (`primary_category_id`);

ALTER TABLE `#__nxp_easycart_products`
  ADD CONSTRAINT `fk_nxp_products_primary_category`
    FOREIGN KEY (`primary_category_id`) REFERENCES `#__nxp_easycart_categories` (`id`)
    ON DELETE SET NULL;
