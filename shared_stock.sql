-- ============================================================================
-- OZONE · Shared Stock / Warehouse Model — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database, after
-- branches.sql and locations.sql. Same idempotent style: safe to re-run.
--
-- What this does:
--   1. Products become ONE SHARED CATALOG instead of a separate list per
--      branch. Adds `products.shared` (1 = sellable at either branch, the
--      default; 0 = exclusive to just the one branch in `products.branch_id`).
--      Also loosens the SKU uniqueness back to a single global constraint,
--      since there is now only one catalog.
--   2. Marks Main Branch's "Big Stock" location as the shared WAREHOUSE
--      (`stock_locations.is_warehouse`) that can refill either branch's
--      shop floor.
--   3. Seeds Second Branch with its own 2 locations: "Shop Floor" (where its
--      cashier actually sells from) and "Back Store" (a small landing spot
--      for stock that's just been refilled but not yet out for sale).
--   4. Adds `branches.is_open` so each branch can be opened/closed for the
--      day independently, and a `shop_day_logs` table auditing every
--      open/close event (who, when).
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. products: shared catalog
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `shared` TINYINT(1) NOT NULL DEFAULT 1 AFTER `branch_id`;

-- Was unique per-branch (branch_id, sku) under the old separate-catalogs
-- model; back to one global unique SKU now that there's a single catalog.
ALTER TABLE `products` DROP INDEX IF EXISTS `uq_products_branch_sku`;
ALTER TABLE `products`
  ADD UNIQUE KEY IF NOT EXISTS `uq_products_sku` (`sku`);

-- ----------------------------------------------------------------------------
-- 2. stock_locations: mark the warehouse + seed Second Branch's 2 locations
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_locations`
  ADD COLUMN IF NOT EXISTS `is_warehouse` TINYINT(1) NOT NULL DEFAULT 0;

UPDATE `stock_locations` SET `is_warehouse` = 1 WHERE `branch_id` = 1 AND `name` = 'Big Stock';

INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 2, 'Hanging' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 2 AND `name` = 'Hanging');
INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 2, 'Back Store' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 2 AND `name` = 'Back Store');

-- ----------------------------------------------------------------------------
-- 3. branches: independent open/close-for-the-day gate + audit log
-- ----------------------------------------------------------------------------
ALTER TABLE `branches`
  ADD COLUMN IF NOT EXISTS `is_open` TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `shop_day_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `branch_id`  INT NOT NULL,
  `action`     ENUM('open','close') NOT NULL,
  `acted_by`   INT NOT NULL,
  `acted_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`      VARCHAR(255) DEFAULT NULL,
  KEY `idx_shopdaylogs_branch` (`branch_id`),
  CONSTRAINT `fk_shopdaylogs_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_shopdaylogs_user`   FOREIGN KEY (`acted_by`)  REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- DONE. Verify with:
--   SHOW COLUMNS FROM products;
--   SELECT * FROM stock_locations;
--   SHOW COLUMNS FROM branches;
--   SELECT * FROM shop_day_logs;
-- ============================================================================
