<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// Branch context: cashiers picked one at login; an admin browsing this area
// falls back to whichever branch they're currently managing.
$branch_id = (int)($_SESSION['branch_id'] ?? $_SESSION['admin_branch_id'] ?? 0);
if (!$branch_id) {
    $mainBranch = $pdo->query("SELECT id FROM branches WHERE is_main = 1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    $branch_id = $mainBranch ? (int)$mainBranch : 1;
}

// This branch's fixed locations.
$stmt = $pdo->prepare("SELECT id, name, is_arrival FROM stock_locations WHERE branch_id = :bid AND name IN ('Shop Arrivals', 'Hanging', 'Fridge')");
$stmt->execute(['bid' => $branch_id]);
$locsByName = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
    $locsByName[$l['name']] = $l;
}
$arrivals_id = isset($locsByName['Shop Arrivals']) ? (int)$locsByName['Shop Arrivals']['id'] : null;

// Everything sitting in Shop Arrivals right now, aggregated per product.
$arrivals = [];
if ($arrivals_id) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.sku, p.name, p.category, SUM(b.quantity_remaining) AS qty
          FROM stock_batches b
          JOIN products p ON p.id = b.product_id
         WHERE b.location_id = :lid AND b.quantity_remaining > 0
         GROUP BY p.id, p.sku, p.name, p.category
         HAVING SUM(b.quantity_remaining) > 0
         ORDER BY p.name ASC
    ");
    $stmt->execute(['lid' => $arrivals_id]);
    $arrivals = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$staff_area = 'cashier';
$page_title = 'Classify Stock';
require '../includes/staff_header.php';
?>
    <style>
        .classify-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 18px; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 640px; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; white-space: nowrap; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; }

        .classify-qty-input { width: 80px; box-sizing: border-box; padding: 8px 10px; text-align: right; }
        .classify-row-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .classify-row-actions label { font-size: 12px; color: var(--kami-text-muted); font-weight: 600; }
        .classify-field { display: flex; align-items: center; gap: 6px; }

        .empty-state-card { text-align: center; padding: 48px 24px; color: var(--kami-text-dim); }
        .empty-state-card i { font-size: 40px; margin-bottom: 12px; display: block; }

        @media (max-width: 768px) {
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table { min-width: 0; }
            .data-table tr { margin-bottom: 16px; background: var(--kami-surface-2); border: 1px solid var(--kami-border) !important; border-radius: var(--kami-radius-md); padding: 16px; }
            .data-table td { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; text-align: right; }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
            .data-table td::before { content: attr(data-label); font-weight: 600; color: var(--kami-text-muted); font-size: 13px; text-align: left; padding-right: 16px; }
        }
    </style>

    <h1 class="kami-page-title">What We Have In The Shop</h1>
    <p class="kami-page-sub">Everything sitting in Shop Arrivals is not sellable yet. Decide how much goes to Hanging and how much goes to the Fridge — you can split one product across both.</p>

    <div class="card glass animate-fade-in">
        <div class="card-header" style="border:none; padding:0; margin-bottom: 18px;">
            <h3><i class="ph-bold ph-truck"></i> Shop Arrivals</h3>
            <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($arrivals) ?> Product<?= count($arrivals) === 1 ? '' : 's' ?> Awaiting Classification</div>
        </div>

        <?php if (!$arrivals_id): ?>
            <div class="empty-state-card">
                <i class="ph ph-warning-circle"></i>
                <p>This branch has no Shop Arrivals location set up yet. Ask an admin to check Stock Locations.</p>
            </div>
        <?php elseif (empty($arrivals)): ?>
            <div class="empty-state-card">
                <i class="ph ph-check-circle"></i>
                <p>Nothing waiting to be classified right now. Refills you receive will show up here.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="classifyTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>In Arrivals</th>
                            <th>Send to Shelves</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arrivals as $row):
                            $pid = (int)$row['id'];
                            $qty = (int)$row['qty'];
                        ?>
                            <tr class="hover-scale" id="arrival-row-<?= $pid ?>" data-qty="<?= $qty ?>">
                                <td data-label="Product">
                                    <div style="font-weight:700; color: var(--kami-text);"><?= htmlspecialchars($row['name']) ?></div>
                                    <div style="font-size:11px; color: var(--kami-text-dim); font-family: monospace;"><?= htmlspecialchars($row['sku']) ?></div>
                                </td>
                                <td data-label="In Arrivals" class="qty-cell"><span class="badge badge-success"><?= $qty ?> units</span></td>
                                <td data-label="Send to Shelves">
                                    <div class="classify-row-actions">
                                        <div class="classify-field">
                                            <label for="hang-<?= $pid ?>">Hanging</label>
                                            <input type="number" min="0" step="1" class="form-input classify-qty-input" id="hang-<?= $pid ?>" placeholder="0">
                                        </div>
                                        <div class="classify-field">
                                            <label for="fridge-<?= $pid ?>">Fridge</label>
                                            <input type="number" min="0" step="1" class="form-input classify-qty-input" id="fridge-<?= $pid ?>" placeholder="0">
                                        </div>
                                    </div>
                                </td>
                                <td data-label="">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="classifyProduct(<?= $pid ?>, <?= $qty ?>)">
                                        <i class="ph-bold ph-arrows-left-right"></i> Move
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function classifyProduct(productId, availableQty) {
            const hangInput = document.getElementById('hang-' + productId);
            const fridgeInput = document.getElementById('fridge-' + productId);
            const qtyHanging = parseInt(hangInput.value, 10) || 0;
            const qtyFridge = parseInt(fridgeInput.value, 10) || 0;

            if (qtyHanging < 0 || qtyFridge < 0) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Invalid Quantity', 'Quantities cannot be negative.', 'error');
                return;
            }
            if (qtyHanging + qtyFridge <= 0) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Nothing To Move', 'Enter a quantity for Hanging and/or Fridge.', 'error');
                return;
            }
            if (qtyHanging + qtyFridge > availableQty) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Too Many Units', `Only ${availableQty} unit(s) available in Shop Arrivals.`, 'error');
                return;
            }

            try {
                const fd = new FormData();
                fd.append('product_id', productId);
                fd.append('qty_hanging', qtyHanging);
                fd.append('qty_fridge', qtyFridge);
                const res = await fetch('../api/classify_stock.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Stock Classified', data.message, 'success');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Could Not Classify', data.message || 'Unknown error', 'error');
                }
            } catch (err) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Network Error', 'Could not reach the server.', 'error');
            }
        }
    </script>
<?php require '../includes/staff_footer.php'; ?>
