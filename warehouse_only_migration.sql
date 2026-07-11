-- ============================================================================
-- OZONE · Warehouse-Only Rule — DATA MIGRATION / VERIFICATION
-- ----------------------------------------------------------------------------
-- Run this in phpMyAdmin (SQL tab) against the `kami` database, after the
-- "Big Stock is the only door" rule change to transfer_stock() and the
-- restock lockdown. Idempotent: safe to re-run.
--
-- What this does:
--   1. Snapshots total tracked stock per product BEFORE anything else runs,
--      so you can compare it to the snapshot at the end.
--   2. Finds any `pending` transfer whose destination is NOT an arrivals
--      location (`is_arrival = 1`) — this would only exist if a pending
--      transfer was created under the old rule with a destination that the
--      new rule wouldn't allow. None exist in the current data (checked),
--      but if one ever does, this reroutes ONLY the pending transfer's
--      destination pointer to the correct Shop Arrivals location for that
--      same branch. No stock_batches row exists yet for a pending transfer
--      (that only gets created on receive), so this cannot lose, gain, or
--      duplicate a single unit — it only fixes where the eventual batch
--      will land once someone confirms receipt.
--   3. Reports (read-only, changes nothing) any restock-looking batch
--      sitting outside Big Stock — i.e. stock that was restocked directly
--      into a shop under the old, unrestricted location dropdown, bypassing
--      the warehouse entirely. None exist in the current data. If any ever
--      show up, they are NOT relocated by this script: that stock is
--      already sitting in a real, valid, sellable (or holding) location —
--      moving it would rewrite FIFO purchase history for no operational
--      benefit and would risk exactly the kind of quantity mistake this
--      migration is supposed to prevent. It's just flagged for your own
--      awareness.
--   4. Snapshots total tracked stock per product AFTER, for you to diff
--      against the BEFORE snapshot — every row should match exactly.
-- ============================================================================

USE `kami`;

-- ----------------------------------------------------------------------------
-- STEP 1: BEFORE snapshot — total units per product, tracked across every
-- location (run this, note the numbers, then compare to STEP 4 after).
-- ----------------------------------------------------------------------------
SELECT p.id, p.name, p.stock AS cached_stock,
       COALESCE(SUM(sb.quantity_remaining), 0) AS tracked_across_locations
  FROM products p
  LEFT JOIN stock_batches sb ON sb.product_id = p.id AND sb.quantity_remaining > 0
 GROUP BY p.id, p.name, p.stock
 ORDER BY p.id;

-- ----------------------------------------------------------------------------
-- STEP 2: find any pending transfer whose destination isn't a valid arrivals
-- location under the new rule. (Expect 0 rows in the current data.)
-- ----------------------------------------------------------------------------
SELECT t.id, t.product_id, t.quantity, fl.name AS from_loc, tl.name AS to_loc, tl.branch_id AS to_branch_id
  FROM stock_transfers t
  JOIN stock_locations tl ON t.to_location_id = tl.id
  JOIN stock_locations fl ON t.from_location_id = fl.id
 WHERE t.status = 'pending' AND tl.is_arrival = 0;

-- ----------------------------------------------------------------------------
-- STEP 3: reroute any such transfer (found in STEP 2) to the correct Shop
-- Arrivals location for the SAME destination branch. Only touches the
-- transfer's pointer — zero stock quantity is created, moved, or removed by
-- this statement, since a pending transfer has no batch yet.
-- ----------------------------------------------------------------------------
UPDATE stock_transfers t
  JOIN stock_locations bad_dest ON t.to_location_id = bad_dest.id AND bad_dest.is_arrival = 0
  JOIN stock_locations correct_arrivals ON correct_arrivals.branch_id = bad_dest.branch_id AND correct_arrivals.is_arrival = 1
   SET t.to_location_id = correct_arrivals.id
 WHERE t.status = 'pending';

-- ----------------------------------------------------------------------------
-- STEP 4: report (read-only) any restock-looking batch sitting outside Big
-- Stock — stock that bypassed the warehouse under the old, unrestricted
-- restock location dropdown. Nothing is changed; this is for your awareness
-- only. (Expect 0 rows in the current data.)
-- ----------------------------------------------------------------------------
SELECT sb.id, sb.product_id, p.name AS product_name, sl.name AS location_name,
       sb.quantity_remaining, sb.notes, sb.purchased_at
  FROM stock_batches sb
  JOIN stock_locations sl ON sb.location_id = sl.id
  JOIN products p ON p.id = sb.product_id
 WHERE sl.is_warehouse = 0
   AND (sb.notes IS NULL OR sb.notes NOT LIKE 'Transferred in%')
   AND sb.notes NOT IN ('Refill received')
   AND (sb.notes IS NULL OR sb.notes NOT LIKE 'Classified from%')
 ORDER BY sb.purchased_at;

-- ----------------------------------------------------------------------------
-- STEP 5: AFTER snapshot — compare row-for-row against STEP 1. Every number
-- must match exactly; nothing in this migration touches quantity_remaining,
-- quantity_bought, or products.stock.
-- ----------------------------------------------------------------------------
SELECT p.id, p.name, p.stock AS cached_stock,
       COALESCE(SUM(sb.quantity_remaining), 0) AS tracked_across_locations
  FROM products p
  LEFT JOIN stock_batches sb ON sb.product_id = p.id AND sb.quantity_remaining > 0
 GROUP BY p.id, p.name, p.stock
 ORDER BY p.id;

-- ============================================================================
-- DONE. STEP 1 and STEP 5 should be identical. STEP 2 and STEP 4 should both
-- return 0 rows in the current data — if they don't, STEP 3 already fixed
-- the pending-transfer case, and STEP 4's rows are informational only.
-- ============================================================================
