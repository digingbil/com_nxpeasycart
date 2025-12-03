-- NXP Easy Cart 0.1.10 migration
-- Enforce hard idempotency on transactions

-- Deduplicate existing rows on (gateway, ext_id)
DELETE t1 FROM `#__nxp_easycart_transactions` t1
JOIN `#__nxp_easycart_transactions` t2
  ON t1.gateway = t2.gateway
 AND t1.ext_id = t2.ext_id
 AND t1.id > t2.id;

-- Deduplicate existing rows on (gateway, event_idempotency_key) when a key is present
DELETE t1 FROM `#__nxp_easycart_transactions` t1
JOIN `#__nxp_easycart_transactions` t2
  ON t1.gateway = t2.gateway
 AND t1.event_idempotency_key IS NOT NULL
 AND t2.event_idempotency_key IS NOT NULL
 AND t1.event_idempotency_key = t2.event_idempotency_key
 AND t1.id > t2.id;

ALTER TABLE `#__nxp_easycart_transactions`
  DROP INDEX `idx_nxp_transactions_external`,
  ADD UNIQUE KEY `idx_nxp_transactions_external` (`gateway`, `ext_id`),
  ADD UNIQUE KEY `idx_nxp_transactions_idempotency` (`gateway`, `event_idempotency_key`);
