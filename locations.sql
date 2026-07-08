-- ============================================================================
-- OZONE · Stock Locations, Transfers, QR-Order Branch Tagging — MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database, after
-- branches.sql. Same idempotent style: safe to re-run.
--
-- What this does:
--   1. Creates `stock_locations` — named sub-locations within a branch's
--      inventory (e.g. Main Branch: Big Stock / Hanging / Fridge). A branch
--      with zero rows here simply has no location dimension — everything
--      about it (restock, POS, inventory) keeps working exactly as before.
--   2. Adds `location_id` to stock_batches (which location a batch lives in)
--      and sale_items (which location a line item was sold from). Both
--      nullable — NULL means "untracked", which is every existing row and
--      every batch/sale for a branch with no locations.
--   3. Creates `stock_transfers`, an audit log of moves between locations.
--   4. Adds `qr_orders.branch_id`, defaulting every existing + future QR
--      order to Main Branch (id=1) — the QR/table-ordering feature is only
--      ever used there.
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. NEW TABLE: stock_locations
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_locations` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `branch_id`  INT NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_locations_branch` (`branch_id`),
  CONSTRAINT `fk_locations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 1, 'Big Stock' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 1 AND `name` = 'Big Stock');
INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 1, 'Hanging' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 1 AND `name` = 'Hanging');
INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 1, 'Fridge' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 1 AND `name` = 'Fridge');

-- ----------------------------------------------------------------------------
-- 2. stock_batches / sale_items: nullable location tag
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_batches`
  ADD COLUMN IF NOT EXISTS `location_id` INT NULL AFTER `branch_id`;
ALTER TABLE `stock_batches`
  ADD KEY IF NOT EXISTS `idx_batches_location` (`location_id`);
ALTER TABLE `stock_batches` DROP FOREIGN KEY IF EXISTS `fk_batches_location`;
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `fk_batches_location` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`);

ALTER TABLE `sale_items`
  ADD COLUMN IF NOT EXISTS `location_id` INT NULL AFTER `batch_id`;
ALTER TABLE `sale_items`
  ADD KEY IF NOT EXISTS `idx_saleitems_location` (`location_id`);
ALTER TABLE `sale_items` DROP FOREIGN KEY IF EXISTS `fk_saleitems_location`;
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_saleitems_location` FOREIGN KEY (`location_id`) REFERENCES `stock_locations` (`id`);

-- ----------------------------------------------------------------------------
-- 3. NEW TABLE: stock_transfers (audit log of moves between locations)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `branch_id`        INT NOT NULL,
  `product_id`       INT NOT NULL,
  `from_location_id` INT NOT NULL,
  `to_location_id`   INT NOT NULL,
  `quantity`         INT NOT NULL,
  `transferred_by`   INT NOT NULL,
  `transferred_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`            VARCHAR(255) DEFAULT NULL,
  KEY `idx_transfers_branch`  (`branch_id`),
  KEY `idx_transfers_product` (`product_id`),
  CONSTRAINT `fk_transfers_branch`  FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`),
  CONSTRAINT `fk_transfers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_transfers_from`    FOREIGN KEY (`from_location_id`) REFERENCES `stock_locations` (`id`),
  CONSTRAINT `fk_transfers_to`      FOREIGN KEY (`to_location_id`)   REFERENCES `stock_locations` (`id`),
  CONSTRAINT `fk_transfers_user`    FOREIGN KEY (`transferred_by`)   REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 4. qr_orders: tag every order to Main Branch (the only branch that uses
--    the QR/table-ordering feature)
-- ----------------------------------------------------------------------------
ALTER TABLE `qr_orders`
  ADD COLUMN IF NOT EXISTS `branch_id` INT NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `qr_orders`
  ADD KEY IF NOT EXISTS `idx_qrorders_branch` (`branch_id`);
ALTER TABLE `qr_orders` DROP FOREIGN KEY IF EXISTS `fk_qrorders_branch`;
ALTER TABLE `qr_orders`
  ADD CONSTRAINT `fk_qrorders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM stock_locations;
--   SHOW COLUMNS FROM stock_batches;
--   SHOW COLUMNS FROM sale_items;
--   SHOW COLUMNS FROM qr_orders;
-- ============================================================================
