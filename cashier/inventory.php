<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// Branch context: cashiers picked one at login; an admin browsing this read-only
// view falls back to whichever branch they're currently managing.
$branch_id = (int)($_SESSION['branch_id'] ?? $_SESSION['admin_branch_id'] ?? 0);
if (!$branch_id) {
    $mainBranch = $pdo->query("SELECT id FROM branches WHERE is_main = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    $branch_id = $mainBranch ? (int)$mainBranch : 1;
}

// Stock locations for this branch (empty = no location dimension, e.g. Second Branch)
$stmt = $pdo->prepare("SELECT id, name FROM stock_locations WHERE branch_id = :bid ORDER BY id ASC");
$stmt->execute(['bid' => $branch_id]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_locations = count($locations) > 0;

// 2. FETCH LIVE INVENTORY (read-only, except the location Transfer action below).
// Products are one shared catalog now — visible here if marked shared, or
// explicitly exclusive to this branch.
$stmt = $pdo->prepare("SELECT id, sku, name, category, selling_price, price, stock FROM products WHERE (shared = 1 OR branch_id = :bid) ORDER BY name ASC");
$stmt->execute(['bid' => $branch_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$locationStockMap = [];
if ($has_locations) {
    $stmt = $pdo->prepare(
        "SELECT product_id, location_id, SUM(quantity_remaining) AS qty
           FROM stock_batches
          WHERE branch_id = :bid AND location_id IS NOT NULL AND quantity_remaining > 0
          GROUP BY product_id, location_id"
    );
    $stmt->execute(['bid' => $branch_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $locationStockMap[(int)$row['product_id']][(int)$row['location_id']] = (int)$row['qty'];
    }
}

$low_count = 0;
foreach ($products as $p) {
    if ((int)$p['stock'] <= 5) $low_count++;
}

$staff_area = 'cashier';
$page_title = 'Inventory';
require '../includes/staff_header.php';
?>
    <style>
        .inv-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 18px; }
        .inv-search { position: relative; flex: 1; min-width: 220px; max-width: 420px; }
        .inv-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--kami-text-dim); font-size: 18px; }
        .inv-search input { width: 100%; box-sizing: border-box; padding: 12px 14px 12px 42px; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 640px; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; white-space: nowrap; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        .mobile-menu-btn, .sidebar-overlay { display: none; }

        .btn-icon { background: rgba(255,255,255,0.05); border: 1px solid var(--kami-border); color: var(--kami-text); padding: 8px; border-radius: 6px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon:hover { background: rgba(255,255,255,0.1); transform: translateY(-1px); }

        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-overlay-ui { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay-ui.active { display: flex; opacity: 1; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); border-radius: 16px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; color: var(--kami-text-muted); font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: var(--kami-text); }

        @media (max-width: 768px) {
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table { min-width: 0; }
            .data-table tr { margin-bottom: 16px; background: var(--kami-surface-2); border: 1px solid var(--kami-border) !important; border-radius: var(--kami-radius-md); padding: 16px; }
            .data-table td { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; text-align: right; }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
            .data-table td::before { content: attr(data-label); font-weight: 600; color: var(--kami-text-muted); font-size: 13px; text-align: left; padding-right: 16px; }
            .form-row-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 24px; width: 90%; }
        }
    </style>

        <h1 class="kami-page-title">Inventory</h1>
        <p class="kami-page-sub">Live stock levels and shelf prices. Read-only — restocking is handled by management.</p>

        <div class="card glass animate-fade-in">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 18px;">
                <h3><i class="ph-bold ph-package"></i> Stock On Hand</h3>
                <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <span class="badge badge-info"><?= count($products) ?> Products</span>
                    <?php if ($low_count > 0): ?>
                        <span class="badge badge-danger"><?= $low_count ?> Low / Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="inv-toolbar">
                <div class="inv-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" id="invSearch" class="form-input" placeholder="Search product, SKU or category…" autocomplete="off" onkeyup="filterInventory()">
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="invTable">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <?php if ($has_locations): ?><th>By Location</th><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?= $has_locations ? 7 : 5 ?>" data-label="Status" style="text-align:center; padding:48px; color: var(--kami-text-dim);">
                                    <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px; display:block;"></i>
                                    No products in the catalog yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product):
                                $sell = (float)$product['selling_price'];
                                if ($sell <= 0) $sell = (float)$product['price'];
                                $stock = (int)$product['stock'];
                            ?>
                                <tr class="hover-scale inv-row">
                                    <td data-label="SKU" style="color: var(--kami-text-muted); font-family: monospace; font-size: 13px; font-weight: 700;">
                                        <?= htmlspecialchars($product['sku']) ?>
                                    </td>
                                    <td data-label="Product" style="font-weight: 700; font-size: 15px; color: var(--kami-text);">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </td>
                                    <td data-label="Category">
                                        <span class="badge badge-info" style="font-size: 10px;"><?= htmlspecialchars($product['category']) ?></span>
                                    </td>
                                    <td data-label="Price" style="font-weight: 700; color: var(--kami-accent);">
                                        $<?= number_format($sell, 2) ?>
                                    </td>
                                    <td data-label="Stock">
                                        <?php if ($stock <= 0): ?>
                                            <span class="badge badge-danger">Out of stock</span>
                                        <?php elseif ($stock <= 5): ?>
                                            <span class="badge badge-danger"><?= $stock ?> left</span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?= $stock ?> in stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($has_locations): ?>
                                    <td data-label="By Location" style="font-size: 12px; color: var(--kami-text-muted);">
                                        <?php
                                            $parts = [];
                                            foreach ($locations as $loc) {
                                                $q = $locationStockMap[(int)$product['id']][(int)$loc['id']] ?? 0;
                                                $parts[] = htmlspecialchars($loc['name']) . ' ' . $q;
                                            }
                                            echo implode(' &middot; ', $parts);
                                        ?>
                                    </td>
                                    <td data-label="Actions">
                                        <button type="button" class="btn-icon" title="Transfer Stock"
                                            onclick='openTransferModal(<?= (int)$product["id"] ?>, <?= htmlspecialchars(json_encode($product["name"]), ENT_QUOTES, "UTF-8") ?>)'>
                                            <i class="ph ph-arrows-left-right"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php if ($has_locations): ?>
    <div class="modal-overlay-ui" id="transferModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-arrows-left-right"></i> Move Stock</h3>
                <button class="modal-close" onclick="closeTransferModal()"><i class="ph ph-x"></i></button>
            </div>
            <p id="transferProductName" style="color: var(--kami-text-muted); margin-bottom: 20px; font-weight: 600;"></p>

            <form id="transferForm" onsubmit="return submitTransfer(event)">
                <input type="hidden" name="product_id" id="transferPid">
                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">From</label>
                        <select name="from_location_id" id="transferFrom" class="form-select" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <select name="to_location_id" id="transferTo" class="form-select" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" min="1" name="quantity" id="transferQty" class="form-input" required placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes <span style="color:var(--kami-text-dim);font-weight:400;">(optional)</span></label>
                    <input type="text" name="notes" class="form-input" placeholder="Optional" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="transferBtn" style="margin-top: 12px;">
                    <i class="ph-bold ph-arrows-left-right"></i> <span>Move Stock</span>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function filterInventory() {
            var q = document.getElementById('invSearch').value.toLowerCase();
            document.querySelectorAll('#invTable tbody tr.inv-row').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
            });
        }

        <?php if ($has_locations): ?>
        function openTransferModal(id, name) {
            document.getElementById('transferPid').value = id;
            document.getElementById('transferProductName').innerText = name;
            document.getElementById('transferQty').value = '';
            document.querySelector('#transferForm [name="notes"]').value = '';
            document.getElementById('transferModal').classList.add('active');
        }
        function closeTransferModal() {
            document.getElementById('transferModal').classList.remove('active');
        }
        async function submitTransfer(e) {
            e.preventDefault();
            var from = document.getElementById('transferFrom').value;
            var to = document.getElementById('transferTo').value;
            if (from === to) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Invalid Move', 'Source and destination must be different locations.', 'error');
                return false;
            }
            var btn = document.getElementById('transferBtn');
            btn.disabled = true;
            try {
                var fd = new FormData(document.getElementById('transferForm'));
                var res = await fetch('../api/transfer_stock.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    closeTransferModal();
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
        document.getElementById('transferModal').addEventListener('click', function (e) {
            if (e.target === this) closeTransferModal();
        });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>
