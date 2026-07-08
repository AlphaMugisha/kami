-- ============================================================================
-- OZONE · Shop Arrivals Holding Locations — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database, after
-- refill_receiving.sql. Same idempotent style: safe to re-run.
--
-- What this does:
--   1. Adds `stock_locations.is_arrival` — marks a location as a holding
--      area that is never sellable (mirrors how `is_warehouse` marks Big
--      Stock). The POS / checkout must never sell from a location flagged
--      this way.
--   2. Second Branch: adds "Shop Arrivals" (holding) and "Fridge" (sellable)
--      alongside its existing single sellable location (currently named
--      "Hanging"). Any stock already sitting in that pre-existing location
--      is moved into the new "Shop Arrivals" so the cashier re-classifies
--      it into Hanging/Fridge, exactly like every future refill will.
--   3. Main Branch: adds its own "Shop Arrivals" holding location alongside
--      the existing Big Stock / Hanging / Fridge, which are untouched.
--      Going forward, admin moves from Big Stock land in Shop Arrivals
--      first, same flow as Second Branch.
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. stock_locations: is_arrival flag
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_locations`
  ADD COLUMN IF NOT EXISTS `is_arrival` TINYINT(1) NOT NULL DEFAULT 0;

-- ----------------------------------------------------------------------------
-- 2. Second Branch: add "Shop Arrivals" + "Fridge" alongside "Hanging".
-- ----------------------------------------------------------------------------
INSERT INTO `stock_locations` (`branch_id`, `name`, `is_arrival`)
  SELECT 2, 'Shop Arrivals', 1 FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 2 AND `name` = 'Shop Arrivals');
INSERT INTO `stock_locations` (`branch_id`, `name`)
  SELECT 2, 'Fridge' FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 2 AND `name` = 'Fridge');

-- Move whatever stock is already sitting in Second Branch's pre-existing
-- sellable location ("Hanging") into the new "Shop Arrivals" holding area,
-- so the cashier classifies it into Hanging/Fridge just like a fresh
-- refill would. Guarded by the join itself: once moved, "Hanging" has no
-- more batches to move, so re-running this is a no-op.
UPDATE `stock_batches` b
  JOIN `stock_locations` old_loc ON old_loc.id = b.location_id AND old_loc.branch_id = 2 AND old_loc.name = 'Hanging'
  JOIN `stock_locations` arrivals ON arrivals.branch_id = 2 AND arrivals.name = 'Shop Arrivals'
   SET b.location_id = arrivals.id
 WHERE old_loc.id <> arrivals.id;

-- ----------------------------------------------------------------------------
-- 3. Main Branch: add "Shop Arrivals" holding location.
-- ----------------------------------------------------------------------------
INSERT INTO `stock_locations` (`branch_id`, `name`, `is_arrival`)
  SELECT 1, 'Shop Arrivals', 1 FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM `stock_locations` WHERE `branch_id` = 1 AND `name` = 'Shop Arrivals');

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM stock_locations ORDER BY branch_id, id;
--   SELECT location_id, COUNT(*), SUM(quantity_remaining)
--     FROM stock_batches WHERE branch_id = 2 GROUP BY location_id;
-- ============================================================================
