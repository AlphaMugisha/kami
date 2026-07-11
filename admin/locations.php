<?php
/* ================================================================
   OZONE · locations.php  ("Where's my stock?" — grid + refills)
   ----------------------------------------------------------------
   Admin's view of stock stops at "how much is in Big Stock, and how
   much has reached each shop" — Product x [Big Stock, Main Branch,
   Second Branch]. Which shelf it's on inside a shop (Hanging, Fridge,
   Shop Arrivals) is the cashier's concern, shown on their own
   inventory/classify pages, not here.
   The only move this page makes is a refill: Big Stock -> a shop.
   Every refill lands in that shop's Shop Arrivals and waits for the
   shop's cashier to confirm it arrived — enforced in transfer_stock().
   ================================================================ */

declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/stock_functions.php';

$success_msg = '';
$error_msg = '';

$branches = $pdo->query("SELECT id, name, is_main FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$default_branch_id = (int)($branches[0]['id'] ?? 1);

/* ================================================================
   LOCATION MANAGEMENT (add / rename / delete) — a location belongs
   to one branch (that's who sells from it / whose ledger it lands
   under), but stock can move freely between ANY two locations.
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $name = trim((string)filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $for_branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT) ?: $default_branch_id;
    if ($name !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO stock_locations (branch_id, name) VALUES (:bid, :name)");
            $stmt->execute(['bid' => $for_branch_id, 'name' => $name]);
            $success_msg = "Location '$name' added.";
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide a location name.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_location'])) {
    $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
    $name = trim((string)filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING));
    if ($edit_id && $name !== '') {
        $stmt = $pdo->prepare("UPDATE stock_locations SET name = :name WHERE id = :id");
        $stmt->execute(['name' => $name, 'id' => $edit_id]);
        $success_msg = "Location renamed successfully.";
    } else {
        $error_msg = "Please provide a valid location name.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_location'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM stock_locations WHERE id = :id");
            $stmt->execute(['id' => $delete_id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Location removed.";
            } else {
                $error_msg = "Location not found.";
            }
        } catch (PDOException $e) {
            $error_msg = "Cannot delete this location — it still has stock or transfer history recorded against it.";
        }
    }
}

/* ================================================================
   GRID DATA — every location, grouped by branch for the column
   headers; every product in the shared catalog as a row.
   ================================================================ */
$all_locations = $pdo->query("
    SELECT l.id, l.name, l.is_warehouse, l.is_arrival, l.branch_id, b.name AS branch_name, b.is_main
      FROM stock_locations l
      JOIN branches b ON b.id = l.branch_id
     ORDER BY b.is_main DESC, l.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$locationsByBranch = [];
foreach ($all_locations as $l) {
    $locationsByBranch[(int)$l['branch_id']]['name'] = $l['branch_name'];
    $locationsByBranch[(int)$l['branch_id']]['locations'][] = $l;
}

// Admin's grid + refill modal work at branch level, not shelf level: one
// "Big Stock" column (the warehouse), and one column per branch summing
// every one of that branch's own locations (Hanging, Fridge, Shop
// Arrivals — whatever it has). Also resolve each branch's Shop Arrivals
// location, since a refill always lands there under the hood.
$warehouseLocationId = 0;
foreach ($all_locations as $l) { if ($l['is_warehouse']) { $warehouseLocationId = (int)$l['id']; break; } }

$branchShopLocationIds = [];   // branch_id => [location ids, excluding the warehouse]
$branchArrivalsLocationId = []; // branch_id => its Shop Arrivals location id
foreach ($all_locations as $l) {
    $bid = (int)$l['branch_id'];
    if (!$l['is_warehouse']) {
        $branchShopLocationIds[$bid][] = (int)$l['id'];
    }
    if ($l['is_arrival']) {
        $branchArrivalsLocationId[$bid] = (int)$l['id'];
    }
}
// Can a refill even happen? Only if there's a warehouse and at least one
// branch has somewhere for a refill to land.
$can_transfer = $warehouseLocationId > 0 && !empty($branchArrivalsLocationId);

$products = $pdo->query("SELECT id, sku, name, stock, units_per_box FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$locationStockMap = [];
$grandTotalUnits = 0;
if ($all_locations) {
    foreach ($pdo->query(
        "SELECT product_id, location_id, SUM(quantity_remaining) AS qty
           FROM stock_batches
          WHERE location_id IS NOT NULL AND quantity_remaining > 0
          GROUP BY product_id, location_id"
    )->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $locationStockMap[(int)$row['product_id']][(int)$row['location_id']] = (int)$row['qty'];
        $grandTotalUnits += (int)$row['qty'];
    }
}

/* ================================================================
   RECENT TRANSFERS (audit trail) — company-wide, newest first.
   ================================================================ */
$recent_transfers = [];
if ($all_locations) {
    $recent_transfers = $pdo->query("
        SELECT t.*, p.name AS product_name,
               fl.name AS from_name, fb.name AS from_branch, fl.is_warehouse AS from_is_warehouse,
               tl.name AS to_name, tb.name AS to_branch,
               u.full_name AS by_name
          FROM stock_transfers t
          JOIN products p ON t.product_id = p.id
          JOIN stock_locations fl ON t.from_location_id = fl.id
          JOIN stock_locations tl ON t.to_location_id = tl.id
          JOIN branches fb ON fl.branch_id = fb.id
          JOIN branches tb ON tl.branch_id = tb.id
          LEFT JOIN users u ON t.transferred_by = u.id
         ORDER BY t.transferred_at DESC, t.id DESC
         LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$staff_area = 'admin';
$page_title = 'Stock Locations';
require '../includes/staff_header.php';
?>
    <style>
        .locs-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 18px; }
        .locs-toolbar .spacer { flex: 1; }

        .branch-loc-group { margin-bottom: 16px; }
        .branch-loc-group h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--kami-text-muted); margin: 0 0 8px; }
        .loc-chip-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .loc-chip {
            display: inline-flex; align-items: center; gap: 8px; padding: 8px 8px 8px 16px;
            background: var(--kami-surface-2); border: 1px solid var(--kami-border); border-radius: var(--kami-radius-full);
            font-size: 13px; font-weight: 700; color: var(--kami-text);
        }
        .loc-chip.warehouse { border-color: var(--kami-accent-border); background: var(--kami-accent-bg); }
        .loc-chip.arrival { border-color: var(--kami-warning-border); background: var(--kami-warning-bg); }
        .loc-chip .chip-ic-btn {
            background: none; border: none; color: var(--kami-text-dim); cursor: pointer; font-size: 15px;
            width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            transition: 0.15s;
        }
        .loc-chip .chip-ic-btn:hover { background: var(--kami-surface-3); color: var(--kami-text); }
        .loc-chip .chip-ic-btn.danger:hover { background: var(--kami-danger-bg); color: var(--kami-danger); }
        .loc-chip-add {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
            background: transparent; border: 1px dashed var(--kami-border-strong); border-radius: var(--kami-radius-full);
            font-size: 13px; font-weight: 700; color: var(--kami-text-muted); cursor: pointer;
        }
        .loc-chip-add:hover { color: var(--kami-accent); border-color: var(--kami-accent-border); }

        .summary-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }

        .inv-search { position: relative; flex: 1; min-width: 220px; max-width: 360px; }
        .inv-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--kami-text-dim); font-size: 18px; }
        .inv-search input { width: 100%; box-sizing: border-box; padding: 12px 14px 12px 42px; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .grid-table { width: 100%; border-collapse: collapse; min-width: 560px; }
        .grid-table th, .grid-table td { padding: 12px 14px; border-bottom: 1px solid var(--kami-border); text-align: center; white-space: nowrap; }
        .grid-table th { color: var(--kami-text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; background: var(--kami-surface-3); }
        .grid-table th.branch-group-head { color: var(--kami-accent); border-bottom: 1px solid var(--kami-border); }
        .grid-table td.col-product, .grid-table th.col-product {
            text-align: left; position: sticky; left: 0; z-index: 2; background: var(--kami-surface-2);
            min-width: 220px; white-space: normal;
        }
        .grid-table th.col-product { background: var(--kami-surface-3); z-index: 3; }
        .grid-table tbody tr:hover td.col-product { background: var(--kami-surface-3); }
        .grid-table .prod-name { font-weight: 700; color: var(--kami-text); font-size: 14px; }
        .grid-table .prod-sku { font-size: 11px; color: var(--kami-text-dim); font-family: monospace; }

        .loc-qty-btn {
            min-width: 44px; padding: 8px 12px; border-radius: var(--kami-radius-sm);
            background: var(--kami-success-bg); border: 1px solid var(--kami-success-border); color: var(--kami-success);
            font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.15s;
        }
        .loc-qty-btn:hover { transform: translateY(-1px); box-shadow: var(--kami-shadow-sm); filter: brightness(1.1); }
        .loc-qty-btn.qty-low { background: var(--kami-warning-bg); border-color: var(--kami-warning-border); color: var(--kami-warning); }
        .loc-qty-zero { color: var(--kami-text-dim); font-weight: 700; }

        .col-total { font-weight: 800; color: var(--kami-text); }
        .drift-flag { color: var(--kami-warning); margin-left: 4px; cursor: help; }

        .modal-overlay-ui { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay-ui.active { display: flex; opacity: 1; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); border-radius: 16px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; color: var(--kami-text-muted); font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: var(--kami-text); }
        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .avail-hint { font-size: 12.5px; color: var(--kami-text-dim); margin: -10px 0 16px; }
        .avail-hint button { background: none; border: none; color: var(--kami-accent); font-weight: 700; cursor: pointer; padding: 0; font-size: 12.5px; }

        #gridTransferModal .modal-content { max-width: 560px; }

        .gt-list {
            max-height: 320px; overflow-y: auto; margin-bottom: 6px;
            border: 1px solid var(--kami-border); border-radius: var(--kami-radius-md);
            background: var(--kami-surface-2);
        }
        .gt-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-bottom: 1px solid var(--kami-border);
        }
        .gt-item:last-child { border-bottom: none; }
        .gt-item.gt-item-active { background: var(--kami-accent-bg); }
        .gt-item-info { flex: 1; min-width: 0; }
        .gt-item-name { font-size: 13.5px; font-weight: 700; color: var(--kami-text); display: block; }
        .gt-item-avail { font-size: 11.5px; color: var(--kami-text-dim); }
        .gt-item-box-note { font-size: 11px; color: var(--kami-accent); margin-left: 6px; }
        .gt-item-qty {
            width: 64px; flex-shrink: 0; padding: 8px 10px; text-align: right;
        }
        .gt-item-box-toggle {
            flex-shrink: 0; width: 34px; height: 34px; border-radius: var(--kami-radius-sm);
            background: var(--kami-surface-3); border: 1px solid var(--kami-border); color: var(--kami-text-dim);
            cursor: pointer; font-size: 15px; display: inline-flex; align-items: center; justify-content: center;
        }
        .gt-item-box-toggle.active { background: var(--kami-accent-bg); border-color: var(--kami-accent-border); color: var(--kami-accent); }
        .gt-item-box-toggle:hover { filter: brightness(1.1); }
        .gt-list-empty { padding: 16px 4px; font-size: 12.5px; color: var(--kami-text-dim); text-align: center; }

        .empty-state-card { text-align: center; padding: 48px 24px; color: var(--kami-text-dim); }
        .empty-state-card i { font-size: 40px; margin-bottom: 12px; display: block; }

        @media (max-width: 768px) {
            .modal-content { padding: 24px; width: 90%; }
            .form-row-grid { grid-template-columns: 1fr; }
        }
    </style>

        <h1 class="kami-page-title">Stock Locations</h1>
        <p class="kami-page-sub">One shared stock, spread across the warehouse and both branches. See what's where, and move it between any two spots in a couple of clicks.</p>

        <!-- ===================== LOCATION MANAGEMENT ===================== -->
        <div class="card glass animate-fade-in" style="margin-bottom:20px;">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 16px;">
                <h3><i class="ph-bold ph-map-pin"></i> Locations</h3>
                <button type="button" class="loc-chip-add" onclick="openAddLocation()">
                    <i class="ph-bold ph-plus"></i> Add Location
                </button>
            </div>
            <?php foreach ($locationsByBranch as $bid => $group): ?>
                <div class="branch-loc-group">
                    <h4><?= htmlspecialchars($group['name']) ?></h4>
                    <div class="loc-chip-row">
                        <?php foreach ($group['locations'] as $loc): ?>
                            <div class="loc-chip <?= $loc['is_warehouse'] ? 'warehouse' : ($loc['is_arrival'] ? 'arrival' : '') ?>">
                                <span><i class="ph-fill <?= $loc['is_warehouse'] ? 'ph-warehouse' : ($loc['is_arrival'] ? 'ph-truck' : 'ph-map-pin') ?>" style="color:var(--kami-accent); margin-right:2px;"></i> <?= htmlspecialchars($loc['name']) ?><?= $loc['is_warehouse'] ? ' (warehouse)' : ($loc['is_arrival'] ? ' (holding)' : '') ?></span>
                                <button type="button" class="chip-ic-btn" title="Rename" onclick='openRenameLocation(<?= (int)$loc["id"] ?>, <?= htmlspecialchars(json_encode($loc["name"]), ENT_QUOTES, "UTF-8") ?>)'>
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form action="locations.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete location &quot;<?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>&quot;? This only works if it has no stock or transfer history.');">
                                    <input type="hidden" name="delete_id" value="<?= (int)$loc['id'] ?>">
                                    <button type="submit" name="delete_location" class="chip-ic-btn danger" title="Delete"><i class="ph ph-x"></i></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($all_locations)): ?>
            <div class="card glass animate-fade-in">
                <div class="empty-state-card">
                    <i class="ph ph-map-trifold"></i>
                    <p>No stock locations exist yet. Add one above to start splitting inventory across the warehouse and each branch's shop floor.</p>
                </div>
            </div>
        <?php else: ?>

        <div class="summary-badges animate-fade-in">
            <span class="badge badge-info"><?= count($products) ?> Products</span>
            <span class="badge badge-info"><?= count($all_locations) ?> Locations</span>
            <span class="badge badge-success"><?= $grandTotalUnits ?> Units Tracked</span>
        </div>

        <div class="card glass animate-fade-in">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
                <h3><i class="ph-bold ph-grid-four"></i> Stock By Branch</h3>
                <?php if ($can_transfer): ?>
                <button type="button" class="btn btn-primary" onclick="openGridTransfer()">
                    <i class="ph-bold ph-truck"></i> Refill A Shop
                </button>
                <?php endif; ?>
            </div>

            <div class="locs-toolbar">
                <div class="inv-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" id="gridSearch" class="form-input" placeholder="Search product or SKU…" autocomplete="off" onkeyup="filterGrid()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="grid-table" id="stockGrid">
                    <thead>
                        <tr>
                            <th class="col-product">Product</th>
                            <th><i class="ph-fill ph-warehouse" title="Warehouse"></i> Big Stock</th>
                            <?php foreach ($branches as $b): ?>
                                <th><?= htmlspecialchars($b['name']) ?></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?= count($branches) + 3 ?>" style="text-align:center; padding:40px; color: var(--kami-text-dim);">
                                    No products in the catalog yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product):
                                $pid = (int)$product['id'];
                                $rowMap = $locationStockMap[$pid] ?? [];
                                $trackedTotal = array_sum($rowMap);
                                $catalogStock = (int)$product['stock'];
                                $drift = $trackedTotal !== $catalogStock;
                                $bigStockQty = $rowMap[$warehouseLocationId] ?? 0;
                            ?>
                                <tr class="grid-row" data-search="<?= htmlspecialchars(mb_strtolower($product['name'] . ' ' . $product['sku'])) ?>">
                                    <td class="col-product">
                                        <div class="prod-name"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="prod-sku"><?= htmlspecialchars($product['sku']) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($bigStockQty > 0 && $can_transfer): ?>
                                            <button type="button" class="loc-qty-btn <?= $bigStockQty <= 3 ? 'qty-low' : '' ?>"
                                                onclick="openGridTransfer(<?= $pid ?>)" title="Refill a shop from Big Stock">
                                                <?= $bigStockQty ?>
                                            </button>
                                        <?php elseif ($bigStockQty > 0): ?>
                                            <span class="loc-qty-btn <?= $bigStockQty <= 3 ? 'qty-low' : '' ?>" style="cursor:default;"><?= $bigStockQty ?></span>
                                        <?php else: ?>
                                            <span class="loc-qty-zero">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($branches as $b):
                                        $bid = (int)$b['id'];
                                        $shopTotal = 0;
                                        foreach (($branchShopLocationIds[$bid] ?? []) as $lid) {
                                            $shopTotal += $rowMap[$lid] ?? 0;
                                        }
                                    ?>
                                        <td>
                                            <?php if ($shopTotal > 0): ?>
                                                <span class="loc-qty-btn <?= $shopTotal <= 3 ? 'qty-low' : '' ?>" style="cursor:default;"><?= $shopTotal ?></span>
                                            <?php else: ?>
                                                <span class="loc-qty-zero">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="col-total">
                                        <?= $trackedTotal ?>
                                        <?php if ($drift): ?>
                                            <i class="ph-fill ph-warning drift-flag" title="Catalog shows <?= $catalogStock ?> units but only <?= $trackedTotal ?> are assigned to a location — the rest is untracked (legacy) stock."></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===================== RECENT TRANSFERS ===================== -->
        <div class="card glass animate-fade-in" style="margin-top:24px;">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                <h3><i class="ph-bold ph-clock-counter-clockwise"></i> Recent Transfers</h3>
                <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($recent_transfers) ?> Logged</div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Product</th>
                            <th>Move</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_transfers)): ?>
                            <tr>
                                <td colspan="7" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
                                    No transfers logged yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transfers as $t):
                                $is_refill = (bool)$t['from_is_warehouse'];
                                $is_cross_branch = $t['from_branch'] !== $t['to_branch'];
                            ?>
                                <tr class="hover-scale" id="transfer-row-<?= (int)$t['id'] ?>">
                                    <td data-label="When" style="color: var(--kami-text-muted); white-space:nowrap;"><?= date('M j, g:i A', strtotime($t['transferred_at'])) ?></td>
                                    <td data-label="Product" style="font-weight:700;"><?= htmlspecialchars($t['product_name']) ?></td>
                                    <td data-label="Move">
                                        <?= htmlspecialchars($t['from_name']) ?> <i class="ph-bold ph-arrow-right" style="color:var(--kami-text-dim);"></i> <?= htmlspecialchars($t['to_name']) ?>
                                        <?php if ($is_refill): ?>
                                            <span class="badge badge-info" style="font-size:9px; margin-left:6px;">
                                                <?= $is_cross_branch
                                                    ? 'Refill: ' . htmlspecialchars($t['from_branch']) . ' → ' . htmlspecialchars($t['to_branch'])
                                                    : 'Refill' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Qty" style="font-weight:800; color: var(--kami-accent);"><?= (int)$t['quantity'] ?></td>
                                    <td data-label="Status">
                                        <?php if ($t['status'] === 'pending'): ?>
                                            <span class="badge badge-danger" style="margin-bottom:6px; display:inline-block;">Awaiting receipt</span><br>
                                            <button type="button" class="btn-icon" title="Mark Received" onclick="adminReceiveTransfer(<?= (int)$t['id'] ?>)">
                                                <i class="ph ph-check-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-success">Received</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="By" class="row-secondary" style="color: var(--kami-text-muted);"><?= htmlspecialchars($t['by_name'] ?? '—') ?></td>
                                    <td data-label="Notes" class="row-secondary" style="color: var(--kami-text-muted);"><?= htmlspecialchars($t['notes'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; // has locations ?>

    <!-- ===================== ADD LOCATION MODAL ===================== -->
    <div class="modal-overlay-ui" id="addLocationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-plus-circle"></i> Add Location</h3>
                <button class="modal-close" onclick="closeAddLocation()"><i class="ph ph-x"></i></button>
            </div>
            <form action="locations.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Belongs to Branch</label>
                    <select name="branch_id" class="form-select" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Location Name</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g. Back Room" autocomplete="off">
                </div>
                <button type="submit" name="add_location" class="btn btn-primary btn-block" style="margin-top: 8px;">
                    <i class="ph-bold ph-floppy-disk"></i> <span>Add Location</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ===================== RENAME LOCATION MODAL ===================== -->
    <div class="modal-overlay-ui" id="renameLocationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rename Location</h3>
                <button class="modal-close" onclick="closeRenameLocation()"><i class="ph ph-x"></i></button>
            </div>
            <form action="locations.php" method="POST">
                <input type="hidden" name="edit_id" id="renameLocIdInput">
                <div class="form-group">
                    <label class="form-label">Location Name</label>
                    <input type="text" name="edit_name" id="renameLocNameInput" class="form-input" required autocomplete="off">
                </div>
                <button type="submit" name="rename_location" class="btn btn-primary btn-block" style="margin-top: 8px;">Save Name</button>
            </form>
        </div>
    </div>

    <?php if ($can_transfer): ?>
    <!-- ===================== REFILL MODAL ===================== -->
    <div class="modal-overlay-ui" id="gridTransferModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-truck"></i> Refill A Shop</h3>
                <button class="modal-close" onclick="closeGridTransfer()"><i class="ph ph-x"></i></button>
            </div>

            <form id="gridTransferForm" onsubmit="return submitGridTransfer(event)">
                <div class="form-group">
                    <label class="form-label">From</label>
                    <div class="form-input" style="background:var(--kami-surface-3); color:var(--kami-text-muted); cursor:default;">
                        <i class="ph-fill ph-warehouse" style="color:var(--kami-accent);"></i> Big Stock
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Send to</label>
                    <select id="gtTo" class="form-select" required>
                        <?php foreach ($branches as $b): if (!isset($branchArrivalsLocationId[(int)$b['id']])) continue; ?>
                            <option value="<?= $branchArrivalsLocationId[(int)$b['id']] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="form-label">What are you sending? <span style="color:var(--kami-text-dim);font-weight:400;">— type a quantity next to anything you want to move</span></label>
                <input type="text" id="gtSearch" class="form-input" placeholder="Search products…" autocomplete="off" oninput="renderTransferList()" style="margin-bottom: 10px;">
                <div id="gtList" class="gt-list"></div>
                <p class="gt-list-empty" id="gtListEmpty" style="display:none;">Nothing available in Big Stock.</p>

                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">Notes <span style="color:var(--kami-text-dim);font-weight:400;">(optional)</span></label>
                    <input type="text" id="gtNotes" class="form-input" placeholder="Optional" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="gtSubmitBtn" style="margin-top: 12px;">
                    <i class="ph-bold ph-truck"></i> <span>Send Refill</span>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        /* productId -> {locationId: qty} */
        const STOCK = <?= json_encode($locationStockMap, JSON_FORCE_OBJECT) ?>;
        const BIG_STOCK_ID = <?= (int)$warehouseLocationId ?>;
        const PRODUCTS = <?= json_encode(array_map(fn($p) => [
            'id' => (int)$p['id'], 'name' => $p['name'],
            'unitsPerBox' => isset($p['units_per_box']) && $p['units_per_box'] !== null ? (int)$p['units_per_box'] : null,
        ], $products)) ?>;

        /* ---- Grid search ---- */
        function filterGrid() {
            const q = document.getElementById('gridSearch').value.trim().toLowerCase();
            document.querySelectorAll('#stockGrid tbody tr.grid-row').forEach(function (row) {
                row.style.display = row.getAttribute('data-search').indexOf(q) >= 0 ? '' : 'none';
            });
        }

        /* ---- Add / rename location modals ---- */
        function openAddLocation() { document.getElementById('addLocationModal').classList.add('active'); }
        function closeAddLocation() { document.getElementById('addLocationModal').classList.remove('active'); }
        document.getElementById('addLocationModal').addEventListener('click', function (e) { if (e.target === this) closeAddLocation(); });

        function openRenameLocation(id, name) {
            document.getElementById('renameLocIdInput').value = id;
            document.getElementById('renameLocNameInput').value = name;
            document.getElementById('renameLocationModal').classList.add('active');
        }
        function closeRenameLocation() { document.getElementById('renameLocationModal').classList.remove('active'); }
        document.getElementById('renameLocationModal').addEventListener('click', function (e) { if (e.target === this) closeRenameLocation(); });

        <?php if ($can_transfer): ?>
        /* ---- Refill modal: always FROM Big Stock, TO a shop ---- */
        function availableQty(productId, locationId) {
            const forProduct = STOCK[productId] || {};
            return forProduct[locationId] || 0;
        }

        /* ---- One simple list: every product you have here, type a quantity
           next to whatever you're sending. No separate "add row" step, no
           picking products from a dropdown — the list IS the picker. ---- */
        let transferState = {}; // productId -> { qty, unitType }

        function productById(pid) {
            return PRODUCTS.find(function (p) { return p.id === pid; });
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
        }

        function ensureTransferState(pid) {
            if (!transferState[pid]) transferState[pid] = { qty: 0, unitType: 'unit' };
            return transferState[pid];
        }

        function itemAvailNote(pid) {
            const product = productById(pid);
            const st = ensureTransferState(pid);
            let note = availableQty(pid, BIG_STOCK_ID) + ' available';
            if (st.unitType === 'box' && product.unitsPerBox && st.qty > 0) {
                note += '<span class="gt-item-box-note">= ' + (st.qty * product.unitsPerBox) + ' units</span>';
            }
            return note;
        }

        function renderTransferList() {
            const query = document.getElementById('gtSearch').value.trim().toLowerCase();
            const listEl = document.getElementById('gtList');
            const emptyEl = document.getElementById('gtListEmpty');

            const items = PRODUCTS
                .map(function (p) { return { product: p, qty: availableQty(p.id, BIG_STOCK_ID) }; })
                .filter(function (x) { return x.qty > 0; })
                .filter(function (x) { return !query || x.product.name.toLowerCase().indexOf(query) !== -1; })
                .sort(function (a, b) { return a.product.name.localeCompare(b.product.name); });

            if (!items.length) {
                listEl.innerHTML = '';
                emptyEl.style.display = '';
                return;
            }
            emptyEl.style.display = 'none';

            listEl.innerHTML = items.map(function (x) {
                const st = ensureTransferState(x.product.id);
                const boxToggle = x.product.unitsPerBox
                    ? '<button type="button" class="gt-item-box-toggle' + (st.unitType === 'box' ? ' active' : '') + '" title="Send as boxes (1 box = ' + x.product.unitsPerBox + ' units)" onclick="toggleTransferBoxType(' + x.product.id + ')"><i class="ph-bold ph-package"></i></button>'
                    : '';
                return '<div class="gt-item' + (st.qty > 0 ? ' gt-item-active' : '') + '" data-pid="' + x.product.id + '">' +
                    '<div class="gt-item-info"><span class="gt-item-name">' + escapeHtml(x.product.name) + '</span>' +
                    '<span class="gt-item-avail">' + itemAvailNote(x.product.id) + '</span></div>' +
                    '<input type="number" min="0" class="form-input gt-item-qty" placeholder="0" value="' + (st.qty || '') + '" oninput="onTransferQtyInput(' + x.product.id + ', this)">' +
                    boxToggle +
                    '</div>';
            }).join('');
        }

        function onTransferQtyInput(pid, input) {
            const st = ensureTransferState(pid);
            st.qty = parseInt(input.value, 10) || 0;
            const item = input.closest('.gt-item');
            item.classList.toggle('gt-item-active', st.qty > 0);
            item.querySelector('.gt-item-avail').innerHTML = itemAvailNote(pid);
        }

        function toggleTransferBoxType(pid) {
            const st = ensureTransferState(pid);
            st.unitType = st.unitType === 'box' ? 'unit' : 'box';
            renderTransferList();
        }

        function openGridTransfer(productId) {
            document.getElementById('gtTo').selectedIndex = 0;

            transferState = {};
            document.getElementById('gtSearch').value = '';
            document.getElementById('gtNotes').value = '';
            renderTransferList();

            if (productId !== undefined) {
                const row = document.querySelector('#gtList .gt-item[data-pid="' + productId + '"]');
                if (row) {
                    const input = row.querySelector('.gt-item-qty');
                    input.focus();
                    input.select();
                }
            }

            document.getElementById('gridTransferModal').classList.add('active');
        }
        function closeGridTransfer() { document.getElementById('gridTransferModal').classList.remove('active'); }
        document.getElementById('gridTransferModal').addEventListener('click', function (e) { if (e.target === this) closeGridTransfer(); });

        async function submitGridTransfer(e) {
            e.preventDefault();
            const to = document.getElementById('gtTo').value;

            const rows = [];
            Object.keys(transferState).forEach(function (pid) {
                const st = transferState[pid];
                if (st.qty > 0) rows.push({ product_id: parseInt(pid, 10), quantity: st.qty, unit_type: st.unitType });
            });
            if (!rows.length) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Nothing To Move', 'Type a quantity next to at least one product.', 'error');
                return false;
            }

            const btn = document.getElementById('gtSubmitBtn');
            btn.disabled = true;
            try {
                const res = await fetch('../api/transfer_stock_bulk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        from_location_id: BIG_STOCK_ID,
                        to_location_id: parseInt(to, 10),
                        notes: document.getElementById('gtNotes').value,
                        rows: rows,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    closeGridTransfer();
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Refill Sent', data.message, 'success');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Transfer Failed', data.message || 'Unknown error', 'error');
                    btn.disabled = false;
                }
            } catch (err) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Network Error', 'Could not reach the server.', 'error');
                btn.disabled = false;
            }
            return false;
        }
        <?php endif; ?>

        /* ---- Admin can also confirm a pending refill directly from here ---- */
        async function adminReceiveTransfer(transferId) {
            try {
                const fd = new FormData();
                fd.append('transfer_id', transferId);
                const res = await fetch('../api/receive_transfer.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Refill Confirmed', data.message, 'success');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Could Not Confirm', data.message || 'Unknown error', 'error');
                }
            } catch (err) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Network Error', 'Could not reach the server.', 'error');
            }
        }

        <?php if ($success_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Locations Updated', <?= json_encode($success_msg) ?>, 'success');
            });
        <?php endif; ?>
        <?php if ($error_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Action Failed', <?= json_encode($error_msg) ?>, 'error');
            });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>
