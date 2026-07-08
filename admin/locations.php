<?php
/* ================================================================
   OZONE · locations.php  ("Where's my stock?" — grid + transfers)
   ----------------------------------------------------------------
   One shared catalog, one shared stock pool, spread across every
   named location at every branch (Main Branch's warehouse + shelves,
   Second Branch's counter). This page shows the whole thing as one
   Product × Location grid, and makes moving stock between ANY two
   locations — including "refilling" a branch from the warehouse —
   as few clicks as possible.
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
$can_transfer = count($all_locations) >= 2;

$locationsByBranch = [];
foreach ($all_locations as $l) {
    $locationsByBranch[(int)$l['branch_id']]['name'] = $l['branch_name'];
    $locationsByBranch[(int)$l['branch_id']]['locations'][] = $l;
}

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
               fl.name AS from_name, fb.name AS from_branch,
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

        #gridTransferModal .modal-content { max-width: 600px; }
        .gt-rows { display: flex; flex-direction: column; gap: 8px; margin-bottom: 10px; }
        .gt-row { display: grid; grid-template-columns: 1fr 80px 90px 32px; gap: 8px; align-items: start; }
        .gt-row .gt-row-hint { grid-column: 1 / 2; font-size: 11.5px; color: var(--kami-text-dim); margin-top: 4px; }
        .gt-row-del { background: none; border: none; color: var(--kami-text-dim); cursor: pointer; font-size: 18px; height: 44px; display: inline-flex; align-items: center; justify-content: center; }
        .gt-row-del:hover { color: #ef4444; }
        .gt-add-row-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; margin-bottom: 6px;
            background: transparent; border: 1px dashed var(--kami-border-strong); border-radius: var(--kami-radius-full);
            font-size: 13px; font-weight: 700; color: var(--kami-text-muted); cursor: pointer;
        }
        .gt-add-row-btn:hover { color: var(--kami-accent); border-color: var(--kami-accent-border); }

        .gt-avail-panel {
            max-height: 160px; overflow-y: auto; margin-bottom: 16px;
            border: 1px solid var(--kami-border); border-radius: var(--kami-radius-md);
            background: var(--kami-surface-2);
        }
        .gt-avail-row {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 8px 12px; border-bottom: 1px solid var(--kami-border);
        }
        .gt-avail-row:last-child { border-bottom: none; }
        .gt-avail-row .gt-avail-name { font-size: 13px; font-weight: 600; color: var(--kami-text); }
        .gt-avail-row .gt-avail-qty { font-size: 12px; color: var(--kami-text-muted); margin-left: 8px; }
        .gt-avail-add-btn {
            background: var(--kami-accent-bg); border: 1px solid var(--kami-accent-border); color: var(--kami-accent);
            border-radius: var(--kami-radius-sm); padding: 5px 10px; font-size: 12px; font-weight: 700; cursor: pointer;
        }
        .gt-avail-add-btn:hover { filter: brightness(1.1); }
        .gt-avail-add-btn.added { background: var(--kami-surface-3); border-color: var(--kami-border); color: var(--kami-text-dim); }
        .gt-avail-empty { padding: 16px 12px; font-size: 12.5px; color: var(--kami-text-dim); text-align: center; }

        @media (max-width: 768px) {
            .gt-row { grid-template-columns: 1fr; }
            .gt-row-del { justify-self: end; height: auto; }
        }

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
                <h3><i class="ph-bold ph-grid-four"></i> Stock By Location</h3>
                <?php if ($can_transfer): ?>
                <button type="button" class="btn btn-primary" onclick="openGridTransfer()">
                    <i class="ph-bold ph-arrows-left-right"></i> New Transfer / Refill
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
                            <th class="col-product" rowspan="2">Product</th>
                            <?php foreach ($locationsByBranch as $bid => $group): ?>
                                <th class="branch-group-head" colspan="<?= count($group['locations']) ?>"><?= htmlspecialchars($group['name']) ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2">Total</th>
                        </tr>
                        <tr>
                            <?php foreach ($all_locations as $loc): ?>
                                <th><?= htmlspecialchars($loc['name']) ?><?= $loc['is_warehouse'] ? ' <i class="ph-fill ph-warehouse" title="Warehouse"></i>' : ($loc['is_arrival'] ? ' <i class="ph-fill ph-truck" title="Holding — not sellable"></i>' : '') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?= count($all_locations) + 2 ?>" style="text-align:center; padding:40px; color: var(--kami-text-dim);">
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
                            ?>
                                <tr class="grid-row" data-search="<?= htmlspecialchars(mb_strtolower($product['name'] . ' ' . $product['sku'])) ?>">
                                    <td class="col-product">
                                        <div class="prod-name"><?= htmlspecialchars($product['name']) ?></div>
                                        <div class="prod-sku"><?= htmlspecialchars($product['sku']) ?></div>
                                    </td>
                                    <?php foreach ($all_locations as $loc):
                                        $lid = (int)$loc['id'];
                                        $qty = $rowMap[$lid] ?? 0;
                                    ?>
                                        <td>
                                            <?php if ($qty > 0 && $can_transfer): ?>
                                                <button type="button" class="loc-qty-btn <?= $qty <= 3 ? 'qty-low' : '' ?>"
                                                    onclick="openGridTransfer(<?= $pid ?>, <?= $lid ?>)">
                                                    <?= $qty ?>
                                                </button>
                                            <?php elseif ($qty > 0): ?>
                                                <span class="loc-qty-btn <?= $qty <= 3 ? 'qty-low' : '' ?>" style="cursor:default;"><?= $qty ?></span>
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
                                $is_cross_branch = $t['from_branch'] !== $t['to_branch'];
                            ?>
                                <tr class="hover-scale" id="transfer-row-<?= (int)$t['id'] ?>">
                                    <td data-label="When" style="color: var(--kami-text-muted); white-space:nowrap;"><?= date('M j, g:i A', strtotime($t['transferred_at'])) ?></td>
                                    <td data-label="Product" style="font-weight:700;"><?= htmlspecialchars($t['product_name']) ?></td>
                                    <td data-label="Move">
                                        <?= htmlspecialchars($t['from_name']) ?> <i class="ph-bold ph-arrow-right" style="color:var(--kami-text-dim);"></i> <?= htmlspecialchars($t['to_name']) ?>
                                        <?php if ($is_cross_branch): ?>
                                            <span class="badge badge-info" style="font-size:9px; margin-left:6px;">Refill: <?= htmlspecialchars($t['from_branch']) ?> → <?= htmlspecialchars($t['to_branch']) ?></span>
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

    <?php if (!empty($all_locations) && $can_transfer): ?>
    <!-- ===================== TRANSFER MODAL ===================== -->
    <div class="modal-overlay-ui" id="gridTransferModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-arrows-left-right"></i> Move Stock</h3>
                <button class="modal-close" onclick="closeGridTransfer()"><i class="ph ph-x"></i></button>
            </div>

            <form id="gridTransferForm" onsubmit="return submitGridTransfer(event)">
                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">From</label>
                        <select id="gtFrom" class="form-select" required onchange="updateAvailHints()">
                            <?php foreach ($locationsByBranch as $bid => $group): ?>
                                <optgroup label="<?= htmlspecialchars($group['name']) ?>">
                                    <?php foreach ($group['locations'] as $loc): ?>
                                        <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <select id="gtTo" class="form-select" required>
                            <?php foreach ($locationsByBranch as $bid => $group): ?>
                                <optgroup label="<?= htmlspecialchars($group['name']) ?>">
                                    <?php foreach ($group['locations'] as $loc): ?>
                                        <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label class="form-label">Available at <span id="gtAvailFromName">this location</span></label>
                <div id="gtAvailablePanel" class="gt-avail-panel"></div>

                <label class="form-label">Products To Move</label>
                <div id="gtRows" class="gt-rows"></div>
                <button type="button" class="gt-add-row-btn" onclick="addTransferRow()">
                    <i class="ph-bold ph-plus"></i> Add another product
                </button>

                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-label">Notes <span style="color:var(--kami-text-dim);font-weight:400;">(optional)</span></label>
                    <input type="text" id="gtNotes" class="form-input" placeholder="Optional" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="gtSubmitBtn" style="margin-top: 12px;">
                    <i class="ph-bold ph-arrows-left-right"></i> <span>Move Stock</span>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        /* productId -> {locationId: qty} */
        const STOCK = <?= json_encode($locationStockMap, JSON_FORCE_OBJECT) ?>;
        const LOCATIONS = <?= json_encode(array_map(fn($l) => ['id' => (int)$l['id'], 'name' => $l['name']], $all_locations)) ?>;
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

        <?php if (!empty($all_locations) && $can_transfer): ?>
        /* ---- Smart transfer modal: click a populated grid cell to pre-fill product + source ---- */
        function availableQty(productId, locationId) {
            const forProduct = STOCK[productId] || {};
            return forProduct[locationId] || 0;
        }

        function pickDefaultTo(fromId) {
            const other = LOCATIONS.find(function (l) { return l.id !== fromId; });
            return other ? other.id : fromId;
        }

        /* ---- Product rows: each row is its own Product select + Qty input ---- */
        let gtRowSeq = 0;

        function productOptionsHtml(selectedId) {
            return PRODUCTS.map(function (p) {
                return '<option value="' + p.id + '"' + (p.id === selectedId ? ' selected' : '') + '>' + p.name.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</option>';
            }).join('');
        }

        function productById(pid) {
            return PRODUCTS.find(function (p) { return p.id === pid; });
        }

        function addTransferRow(productId) {
            const id = 'gtRow' + (++gtRowSeq);
            const row = document.createElement('div');
            row.className = 'gt-row';
            row.id = id;
            row.innerHTML =
                '<select class="form-select gt-row-product" onchange="updateRowHint(\'' + id + '\'); renderAvailablePanel();">' + productOptionsHtml(productId) + '</select>' +
                '<input type="number" min="1" class="form-input gt-row-qty" placeholder="Qty">' +
                '<select class="form-select gt-row-type" onchange="updateRowHint(\'' + id + '\')"><option value="unit">Units</option><option value="box">Boxes</option></select>' +
                '<button type="button" class="gt-row-del" title="Remove" onclick="removeTransferRow(\'' + id + '\')"><i class="ph ph-x"></i></button>' +
                '<div class="gt-row-hint"></div>';
            document.getElementById('gtRows').appendChild(row);
            row.querySelector('.gt-row-qty').addEventListener('input', function () { updateRowHint(id); });
            updateRowHint(id);
            return row;
        }

        function removeTransferRow(rowId) {
            const rows = document.getElementById('gtRows');
            const row = document.getElementById(rowId);
            if (row) row.remove();
            if (!rows.children.length) addTransferRow();
            renderAvailablePanel();
        }

        function updateRowHint(rowId) {
            const row = document.getElementById(rowId);
            if (!row) return;
            const pid = parseInt(row.querySelector('.gt-row-product').value, 10);
            const lid = parseInt(document.getElementById('gtFrom').value, 10);
            const type = row.querySelector('.gt-row-type').value;
            const product = productById(pid);
            const qty = availableQty(pid, lid);

            let hint = qty + ' available at this location';
            if (qty > 0) hint += ' — <button type="button" onclick="useRowMaxQty(\'' + rowId + '\')">use max</button>';
            if (type === 'box') {
                if (product && product.unitsPerBox) {
                    const enteredQty = parseFloat(row.querySelector('.gt-row-qty').value) || 0;
                    hint += enteredQty > 0 ? (' &middot; = ' + (enteredQty * product.unitsPerBox) + ' units') : (' &middot; 1 box = ' + product.unitsPerBox + ' units');
                } else {
                    hint += ' &middot; <span style="color:var(--kami-warning);">no box size set for this product</span>';
                }
            }
            row.querySelector('.gt-row-hint').innerHTML = hint;
        }

        function useRowMaxQty(rowId) {
            const row = document.getElementById(rowId);
            if (!row) return;
            const pid = parseInt(row.querySelector('.gt-row-product').value, 10);
            const lid = parseInt(document.getElementById('gtFrom').value, 10);
            const type = row.querySelector('.gt-row-type').value;
            const product = productById(pid);
            const available = availableQty(pid, lid);
            if (type === 'box' && product && product.unitsPerBox) {
                row.querySelector('.gt-row-qty').value = Math.floor(available / product.unitsPerBox);
            } else {
                row.querySelector('.gt-row-qty').value = available;
            }
            updateRowHint(rowId);
        }

        function updateAvailHints() {
            document.querySelectorAll('#gtRows .gt-row').forEach(function (row) { updateRowHint(row.id); });
            renderAvailablePanel();
        }

        /* ---- "Available at this location" panel: see everything you could
           send before picking, instead of guessing names from a dropdown ---- */
        function rowProductIdsInUse() {
            return Array.prototype.map.call(
                document.querySelectorAll('#gtRows .gt-row-product'),
                function (sel) { return parseInt(sel.value, 10); }
            );
        }

        function renderAvailablePanel() {
            const lid = parseInt(document.getElementById('gtFrom').value, 10);
            const fromLoc = LOCATIONS.find(function (l) { return l.id === lid; });
            document.getElementById('gtAvailFromName').textContent = fromLoc ? fromLoc.name : 'this location';

            const panel = document.getElementById('gtAvailablePanel');
            const inUse = rowProductIdsInUse();
            const items = PRODUCTS
                .map(function (p) { return { product: p, qty: availableQty(p.id, lid) }; })
                .filter(function (x) { return x.qty > 0; })
                .sort(function (a, b) { return b.qty - a.qty; });

            if (!items.length) {
                panel.innerHTML = '<div class="gt-avail-empty">Nothing available at this location.</div>';
                return;
            }

            panel.innerHTML = items.map(function (x) {
                const already = inUse.indexOf(x.product.id) !== -1;
                return '<div class="gt-avail-row">' +
                    '<span><span class="gt-avail-name">' + x.product.name.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>' +
                    '<span class="gt-avail-qty">' + x.qty + ' available</span></span>' +
                    '<button type="button" class="gt-avail-add-btn' + (already ? ' added' : '') + '" onclick="addOrFocusRow(' + x.product.id + ')">' +
                    (already ? 'Added' : '+ Add') + '</button>' +
                    '</div>';
            }).join('');
        }

        function addOrFocusRow(productId) {
            const existingSel = Array.prototype.find.call(
                document.querySelectorAll('#gtRows .gt-row-product'),
                function (sel) { return parseInt(sel.value, 10) === productId; }
            );
            if (existingSel) {
                const row = existingSel.closest('.gt-row');
                row.querySelector('.gt-row-qty').focus();
                return;
            }
            // Reuse the first row if it's still empty/untouched, instead of piling up blank rows.
            const rows = document.querySelectorAll('#gtRows .gt-row');
            if (rows.length === 1 && !rows[0].querySelector('.gt-row-qty').value) {
                rows[0].querySelector('.gt-row-product').value = productId;
                updateRowHint(rows[0].id);
                rows[0].querySelector('.gt-row-qty').focus();
            } else {
                const row = addTransferRow(productId);
                row.querySelector('.gt-row-qty').focus();
            }
            renderAvailablePanel();
        }

        function openGridTransfer(productId, fromLocationId) {
            const fromSel = document.getElementById('gtFrom');
            const toSel = document.getElementById('gtTo');

            if (fromLocationId !== undefined) fromSel.value = fromLocationId;
            const from = parseInt(fromSel.value, 10);
            toSel.value = pickDefaultTo(from);

            document.getElementById('gtRows').innerHTML = '';
            gtRowSeq = 0;
            addTransferRow(productId);
            renderAvailablePanel();

            document.getElementById('gtNotes').value = '';
            document.getElementById('gridTransferModal').classList.add('active');
        }
        function closeGridTransfer() { document.getElementById('gridTransferModal').classList.remove('active'); }
        document.getElementById('gridTransferModal').addEventListener('click', function (e) { if (e.target === this) closeGridTransfer(); });

        async function submitGridTransfer(e) {
            e.preventDefault();
            const from = document.getElementById('gtFrom').value;
            const to = document.getElementById('gtTo').value;
            if (from === to) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Invalid Move', 'Source and destination must be different locations.', 'error');
                return false;
            }

            const rows = [];
            for (const rowEl of document.querySelectorAll('#gtRows .gt-row')) {
                const pid = parseInt(rowEl.querySelector('.gt-row-product').value, 10);
                const qty = parseInt(rowEl.querySelector('.gt-row-qty').value, 10);
                const unitType = rowEl.querySelector('.gt-row-type').value;
                if (!qty || qty <= 0) continue;
                rows.push({ product_id: pid, quantity: qty, unit_type: unitType });
            }
            if (!rows.length) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Nothing To Move', 'Enter a quantity for at least one product.', 'error');
                return false;
            }

            const btn = document.getElementById('gtSubmitBtn');
            btn.disabled = true;
            try {
                const res = await fetch('../api/transfer_stock_bulk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        from_location_id: parseInt(from, 10),
                        to_location_id: parseInt(to, 10),
                        notes: document.getElementById('gtNotes').value,
                        rows: rows,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    closeGridTransfer();
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Stock Moved', data.message, 'success');
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
