-- ============================================================================
-- OZONE LIQUOR · Batch Restock Tracking ("Kurungura") — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this ONCE in phpMyAdmin (SQL tab) against the `kami` database.
-- It is additive: it creates one new table and adds columns to existing
-- tables. Your existing data (products, sales, sale_items, users) is kept.
-- Safe to read top-to-bottom; each step is commented.
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
  ADD COLUMN `buying_price`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `price`,
  ADD COLUMN `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `buying_price`;

-- ----------------------------------------------------------------------------
-- 3. sale_items: add batch traceability for per-batch profit
-- ----------------------------------------------------------------------------
ALTER TABLE `sale_items`
  ADD COLUMN `batch_id`     INT DEFAULT NULL AFTER `price`,
  ADD COLUMN `buying_price` DECIMAL(10,2) DEFAULT NULL AFTER `batch_id`,
  ADD KEY `idx_batch` (`batch_id`),
  ADD CONSTRAINT `fk_saleitem_batch` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`);

-- ----------------------------------------------------------------------------
-- 4. BACKFILL: seed cached prices + an opening batch for existing stock,
--    so FIFO deduction and profit reporting work immediately for current
--    inventory. Legacy cost is unknown, so buying_price = old retail price
--    (margin shows 0% — honest "unknown", never a fake profit).
-- ----------------------------------------------------------------------------
UPDATE `products`
   SET `selling_price` = `price`,
       `buying_price`  = `price`
 WHERE `selling_price` = 0.00;

INSERT INTO `stock_batches`
  (`product_id`, `quantity_bought`, `quantity_remaining`, `buying_price`, `selling_price`, `purchased_at`, `notes`, `created_by`)
SELECT
  `id`, `stock`, `stock`, `price`, `price`, `created_at`,
  'Opening stock (auto-seeded by migration)',
  (SELECT `id` FROM `users` WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1)
FROM `products`
WHERE `stock` > 0;

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM stock_batches;
--   SHOW COLUMNS FROM products;
--   SHOW COLUMNS FROM sale_items;
-- ============================================================================
