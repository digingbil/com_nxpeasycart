-- NXP Easy Cart 0.1.16 - Add indexes for checked_out columns (performance optimization)

-- Index for products checked_out (lock status queries)
ALTER TABLE `#__nxp_easycart_products`
  ADD KEY `idx_nxp_products_checked_out` (`checked_out`);

-- Index for categories checked_out (lock status queries)
ALTER TABLE `#__nxp_easycart_categories`
  ADD KEY `idx_nxp_categories_checked_out` (`checked_out`);

-- Index for orders checked_out (lock status queries)
ALTER TABLE `#__nxp_easycart_orders`
  ADD KEY `idx_nxp_orders_checked_out` (`checked_out`);
