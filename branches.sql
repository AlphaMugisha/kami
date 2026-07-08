-- ============================================================================
-- OZONE · Multi-Branch Support — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database.
-- It is ADDITIVE and IDEMPOTENT (same style as run.sql): safe to re-run.
--
-- What this does:
--   1. Creates a `branches` table, seeded with "Main Branch" (id=1) and
--      "Second Branch" (id=2) — rename the second one to the real branch
--      name via the new Admin > Branches page once this is applied.
--   2. Adds `branch_id` to products, stock_batches, sales, shifts.
--      Every existing row is backfilled to Main Branch (id=1) automatically
--      via the column default, so nothing currently in the system is lost
--      or reassigned to the wrong place.
--   3. Changes the products SKU uniqueness constraint from global-unique to
--      unique-per-branch, since each branch now manages its own catalog and
--      may independently reuse a SKU pattern.
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. NEW TABLE: branches
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `is_main`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `branches` (`id`, `name`, `is_main`)
  SELECT 1, 'Main Branch', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `branches` WHERE `id` = 1);

INSERT INTO `branches` (`id`, `name`, `is_main`)
  SELECT 2, 'Second Branch', 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `branches` WHERE `id` = 2);

-- ----------------------------------------------------------------------------
-- 2. products: branch-scope + per-branch SKU uniqueness
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `branch_id` INT NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `products`
  ADD KEY IF NOT EXISTS `idx_products_branch` (`branch_id`);

ALTER TABLE `products` DROP FOREIGN KEY IF EXISTS `fk_products_branch`;
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

-- Old constraint was a single global UNIQUE KEY named `sku` on (`sku`) — drop
-- it and replace with a composite unique per branch.
ALTER TABLE `products` DROP INDEX IF EXISTS `sku`;
ALTER TABLE `products`
  ADD UNIQUE KEY IF NOT EXISTS `uq_products_branch_sku` (`branch_id`, `sku`);

-- ----------------------------------------------------------------------------
-- 3. stock_batches: branch-scope (denormalized from product at insert time,
--    so restock history/filtering doesn't need a join back to products)
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_batches`
  ADD COLUMN IF NOT EXISTS `branch_id` INT NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `stock_batches`
  ADD KEY IF NOT EXISTS `idx_batches_branch` (`branch_id`);

ALTER TABLE `stock_batches` DROP FOREIGN KEY IF EXISTS `fk_batches_branch`;
ALTER TABLE `stock_batches`
  ADD CONSTRAINT `fk_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

-- ----------------------------------------------------------------------------
-- 4. sales: branch-scope (which branch the sale happened at)
-- ----------------------------------------------------------------------------
ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `branch_id` INT NOT NULL DEFAULT 1 AFTER `cashier_id`;
ALTER TABLE `sales`
  ADD KEY IF NOT EXISTS `idx_sales_branch` (`branch_id`);

ALTER TABLE `sales` DROP FOREIGN KEY IF EXISTS `fk_sales_branch`;
ALTER TABLE `sales`
  ADD CONSTRAINT `fk_sales_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

-- ----------------------------------------------------------------------------
-- 5. shifts: branch-scope (which branch the cashier clocked in at)
-- ----------------------------------------------------------------------------
ALTER TABLE `shifts`
  ADD COLUMN IF NOT EXISTS `branch_id` INT NOT NULL DEFAULT 1 AFTER `cashier_id`;
ALTER TABLE `shifts`
  ADD KEY IF NOT EXISTS `idx_shifts_branch` (`branch_id`);

ALTER TABLE `shifts` DROP FOREIGN KEY IF EXISTS `fk_shifts_branch`;
ALTER TABLE `shifts`
  ADD CONSTRAINT `fk_shifts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM branches;
--   SHOW COLUMNS FROM products;
--   SELECT branch_id, COUNT(*) FROM products GROUP BY branch_id;
-- ============================================================================
