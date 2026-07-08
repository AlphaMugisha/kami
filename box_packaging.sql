-- ============================================================================
-- OZONE · Box Packaging — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database. Idempotent:
-- safe to re-run.
--
-- What this does:
--   Adds `products.units_per_box` (nullable) — how many individual units come
--   in one box/case of this product (e.g. a box of Heineken = 24). NULL means
--   "this product has no known box size" — it can only be restocked/
--   transferred as standalone units until an admin sets one.
--
--   Nothing about the unit-based stock ledger changes: stock_batches,
--   FIFO deduction, sales, etc. all keep working in raw units exactly as
--   before. The box size is purely a data-entry convenience — "I'm sending
--   3 boxes" gets converted to units (3 x units_per_box) at the moment it's
--   recorded, server-side, so it can never be spoofed from the browser.
-- ============================================================================

USE `kami`;

ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `units_per_box` INT NULL DEFAULT NULL AFTER `stock`;

-- ============================================================================
-- DONE. Verify with:
--   SHOW COLUMNS FROM products;
-- ============================================================================
