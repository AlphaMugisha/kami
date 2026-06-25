-- ============================================================================
-- OZONE LIQUOR · Batch Restock Tracking ("Kurungura") — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database.
-- It is ADDITIVE and IDEMPOTENT: every step is guarded with IF NOT EXISTS /
-- existence checks, so re-running it is safe and will NOT throw
-- "#1060 Duplicate column" or "#1050 Table already exists" errors.
-- Your existing data (products, sales, sale_items, users) is kept.
-- Requires MariaDB 10.0+ (XAMPP ships 10.4 — fine).
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. NEW TABLE: stock_batches  (one row per purchase/restock batch)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_batches` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`         INT NOT NULL,
  `quantity_bought`    INT NOT NULL,
  `quantity_remaining` INT NOT NULL,
  `buying_price`       DECIMAL(10,2) NOT NULL,
  `selling_price`      DECIMAL(10,2) NOT NULL,
  `purchased_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  `notes`              VARCHAR(255) DEFAULT NULL,
  `created_by`         INT NOT NULL,
  KEY `idx_product`      (`product_id`),
  KEY `idx_purchased_at` (`purchased_at`),
  CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_batch_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 2. products: add cached latest-batch prices (POS fast-read)
--    `price` stays and is kept in sync with selling_price by the app.
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `buying_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `price`;
ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `buying_price`;

-- ----------------------------------------------------------------------------
-- 3. sale_items: add batch traceability for per-batch profit
-- ----------------------------------------------------------------------------
ALTER TABLE `sale_items`
  ADD COLUMN IF NOT EXISTS `batch_id`     INT DEFAULT NULL AFTER `price`;
ALTER TABLE `sale_items`
  ADD COLUMN IF NOT EXISTS `buying_price` DECIMAL(10,2) DEFAULT NULL AFTER `batch_id`;
ALTER TABLE `sale_items`
  ADD KEY IF NOT EXISTS `idx_batch` (`batch_id`);

-- (Re)create the FK only after the column/key exist. DROP IF EXISTS keeps it re-runnable.
ALTER TABLE `sale_items` DROP FOREIGN KEY IF EXISTS `fk_saleitem_batch`;
ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_saleitem_batch` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`);

-- ----------------------------------------------------------------------------
-- 4. BACKFILL: seed cached prices + an opening batch for existing stock,
--    so FIFO deduction and profit reporting work immediately for current
--    inventory. Legacy cost is unknown, so buying_price = old retail price
--    (margin shows 0% — honest "unknown", never a fake profit).
--    Guarded so re-running does NOT create duplicate opening batches.
-- ----------------------------------------------------------------------------
UPDATE `products`
   SET `selling_price` = `price`,
       `buying_price`  = `price`
 WHERE `selling_price` = 0.00;

INSERT INTO `stock_batches`
  (`product_id`, `quantity_bought`, `quantity_remaining`, `buying_price`, `selling_price`, `purchased_at`, `notes`, `created_by`)
SELECT
  p.`id`, p.`stock`, p.`stock`, p.`price`, p.`price`, p.`created_at`,
  'Opening stock (auto-seeded by migration)',
  (SELECT `id` FROM `users` WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1)
FROM `products` p
WHERE p.`stock` > 0
  AND NOT EXISTS (SELECT 1 FROM `stock_batches` sb WHERE sb.`product_id` = p.`id`);

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM stock_batches;
--   SHOW COLUMNS FROM products;
--   SHOW COLUMNS FROM sale_items;
-- ============================================================================
