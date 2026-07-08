-- ============================================================================
-- OZONE · Refill Receiving Handshake — DATABASE MIGRATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database, after
-- shared_stock.sql. Same idempotent style: safe to re-run.
--
-- What this does:
--   1. Simplifies Second Branch to ONE location ("Hanging") instead of
--      two — the old "Back Store" is no longer needed, because a refill
--      now sits in a "pending" state until the branch confirms it arrived,
--      which does the same job a separate holding-shelf used to do.
--   2. Adds a sent -> pending -> received handshake to stock_transfers:
--      a transfer BETWEEN BRANCHES (e.g. Main Branch's warehouse refilling
--      Second Branch) is deducted from the sender immediately but does not
--      become sellable stock at the destination until someone there marks
--      it received. A transfer WITHIN one branch (e.g. Big Stock -> Fridge)
--      is unaffected — those still land instantly, same as before.
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- 1. Simplify Second Branch down to one location.
-- ----------------------------------------------------------------------------
DELETE FROM `stock_locations`
 WHERE `branch_id` = 2 AND `name` = 'Back Store'
   AND NOT EXISTS (SELECT 1 FROM `stock_batches` WHERE `location_id` = `stock_locations`.`id`)
   AND NOT EXISTS (SELECT 1 FROM `sale_items` WHERE `location_id` = `stock_locations`.`id`)
   AND NOT EXISTS (SELECT 1 FROM `stock_transfers` WHERE `from_location_id` = `stock_locations`.`id` OR `to_location_id` = `stock_locations`.`id`);

-- ----------------------------------------------------------------------------
-- 2. stock_transfers: sent -> pending -> received handshake
-- ----------------------------------------------------------------------------
ALTER TABLE `stock_transfers`
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','received') NOT NULL DEFAULT 'received' AFTER `quantity`;
ALTER TABLE `stock_transfers`
  ADD COLUMN IF NOT EXISTS `avg_buying_price` DECIMAL(10,2) NULL AFTER `status`;
ALTER TABLE `stock_transfers`
  ADD COLUMN IF NOT EXISTS `sell_price_at_send` DECIMAL(10,2) NULL AFTER `avg_buying_price`;
ALTER TABLE `stock_transfers`
  ADD COLUMN IF NOT EXISTS `received_at` DATETIME NULL AFTER `transferred_at`;
ALTER TABLE `stock_transfers`
  ADD COLUMN IF NOT EXISTS `received_by` INT NULL AFTER `received_at`;

ALTER TABLE `stock_transfers` DROP FOREIGN KEY IF EXISTS `fk_transfers_receiver`;
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `fk_transfers_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

-- Any transfers already logged before this migration were the old
-- "instant" kind — mark them received-by-the-sender for a clean history.
UPDATE `stock_transfers`
   SET `received_at` = `transferred_at`, `received_by` = `transferred_by`
 WHERE `status` = 'received' AND `received_at` IS NULL;

-- ============================================================================
-- DONE. Verify with:
--   SELECT * FROM stock_locations WHERE branch_id = 2;
--   SHOW COLUMNS FROM stock_transfers;
-- ============================================================================
