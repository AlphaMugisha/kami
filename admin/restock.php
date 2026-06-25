<?php
/* ================================================================
   OZONE · restock.php  ("Kurungura" — Stock Purchase Ledger)
   ----------------------------------------------------------------
   Top:    Add New Restock entry form (-> process_restock.php)
   Bottom: Excel-style restock history, filterable + paginated,
           each row editable (admin price correction).
   ================================================================ */

declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/stock_functions.php';

/* ---- products for the dropdown ---- */
$products = $pdo->query(
    "SELECT id, name, sku, stock, buying_price, selling_price
       FROM products ORDER BY name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

/* ---- filters ---- */
$filter_product = filter_input(INPUT_GET, 'f_product', FILTER_VALIDATE_INT) ?: 0;
$filter_from    = trim((string)($_GET['f_from'] ?? ''));
$filter_to      = trim((string)($_GET['f_to'] ?? ''));

$where  = [];
$params = [];
if ($filter_product > 0) {
    $where[] = 'b.product_id = :fp';
    $params['fp'] = $filter_product;
}
if ($filter_from !== '' && strtotime($filter_from) !== false) {
    $where[] = 'b.purchased_at >= :ff';
    $params['ff'] = date('Y-m-d 00:00:00', strtotime($filter_from));
}
if ($filter_to !== '' && strtotime($filter_to) !== false) {
    $where[] = 'b.purchased_at <= :ft';
    $params['ft'] = date('Y-m-d 23:59:59', strtotime($filter_to));
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---- pagination ---- */
$per_page = 20;
$page     = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_batches b $where_sql");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

/* ---- history rows ---- */
$sql = "SELECT b.*, p.name AS product_name, p.sku AS product_sku, u.full_name AS logged_by
          FROM stock_batches b
          JOIN products p ON b.product_id = p.id
          LEFT JOIN users u ON b.created_by = u.id
          $where_sql
      ORDER BY b.purchased_at DESC, b.id DESC
         LIMIT $per_page OFFSET $offset";
$hist_stmt = $pdo->prepare($sql);
$hist_stmt->execute($params);
$history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---- flash from process_restock redirect ---- */
$flash_status = $_GET['status'] ?? '';
$flash_msg    = $_GET['msg'] ?? '';

/* helper to preserve filters in pagination links */
function page_link(int $p): string
{
    $q = $_GET;
    $q['page'] = $p;
    return 'restock.php?' . http_build_query($q);
}

$staff_area = 'admin';
$page_title = 'Restock (Kurungura)';
require '../includes/staff_header.php';
?>
    <style>
        .restock-grid { display: grid; grid-template-columns: 380px 1fr; gap: 20px; align-items: start; }
        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .data-table th { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; white-space: nowrap; }
        .data-table td { padding: 13px 14px; border-bottom: 1px solid rgba(255,255,255,0.02); font-size: 14px; }

        .margin-pos { color: #10b981; font-weight: 800; }
        .margin-neg { color: #ef4444; font-weight: 800; }
        .margin-na  { color: var(--kami-text-dim); font-weight: 700; }

        .filter-bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 20px; }
        .filter-bar .form-group { margin-bottom: 0; }
        .filter-bar .form-input, .filter-bar .form-select { min-width: 150px; }

        .pager { display: flex; gap: 8px; align-items: center; justify-content: flex-end; margin-top: 16px; flex-wrap: wrap; }
        .pager a, .pager span.pager-current {
            padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 700;
            border: 1px solid var(--kami-border); color: var(--kami-text); text-decoration: none;
        }
        .pager a:hover { background: var(--kami-accent-bg); border-color: var(--kami-accent-border); }
        .pager span.pager-current { background: var(--kami-accent-bg); border-color: var(--kami-accent-border); color: var(--kami-accent); }
        .pager span.pager-disabled { padding: 8px 14px; color: var(--kami-text-dim); font-size: 13px; }

        .btn-icon { background: rgba(255,255,255,0.05); border: 1px solid var(--kami-border); color: var(--kami-text); padding: 8px; border-radius: 6px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon:hover { background: var(--kami-accent-bg); border-color: var(--kami-accent-border); transform: translateY(-1px); }

        .modal-overlay-ui { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay-ui.active { display: flex; opacity: 1; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); border-radius: 16px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; color: var(--kami-text-muted); font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: var(--kami-text); }

        .mobile-menu-btn, .sidebar-overlay { display: none; }

        @media (max-width: 1100px) { .restock-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .form-row-grid { grid-template-columns: 1fr; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table { min-width: 0; }
            .data-table tr { margin-bottom: 16px; background: var(--kami-surface-2); border: 1px solid var(--kami-border) !important; border-radius: var(--kami-radius-md); padding: 16px; }
            .data-table td { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; text-align: right; }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
            .data-table td::before { content: attr(data-label); font-weight: 600; color: var(--kami-text-muted); font-size: 13px; text-align: left; padding-right: 16px; }
            .modal-content { width: 90%; padding: 24px; }
        }
    </style>

        <h1 class="kami-page-title">Restock · Kurungura</h1>
        <p class="kami-page-sub">Record every stock purchase as its own batch — cost, retail price and remaining units, kept per batch so you always know your margin.</p>

        <div class="restock-grid animate-fade-in">

            <!-- ===================== ENTRY FORM ===================== -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-shopping-cart-simple"></i> Add New Restock</h3>
                </div>

                <form action="process_restock.php" method="POST" id="restockForm">
                    <div class="form-group">
                        <label class="form-label">Product</label>
                        <select name="product_id" id="rsProduct" class="form-select" required>
                            <option value="">— Select a product —</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"
                                        data-selling="<?= htmlspecialchars((string)$p['selling_price']) ?>">
                                    <?= htmlspecialchars($p['name']) ?> · stock: <?= (int)$p['stock'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row-grid">
                        <div class="form-group">
                            <label class="form-label">Quantity Bought</label>
                            <input type="number" name="quantity" id="rsQty" class="form-input" min="1" required placeholder="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Buying Price ($/unit)</label>
                            <input type="number" step="0.01" min="0" name="buying_price" id="rsBuy" class="form-input" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-row-grid">
                        <div class="form-group">
                            <label class="form-label">Selling Price ($/unit)</label>
                            <input type="number" step="0.01" min="0" name="selling_price" id="rsSell" class="form-input" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Margin</label>
                            <input type="text" id="rsMargin" class="form-input" readonly value="—" style="font-weight:800;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Purchase Date</label>
                        <input type="datetime-local" name="purchased_at" id="rsDate" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes <span style="color:var(--kami-text-dim);font-weight:400;">(supplier, invoice ref…)</span></label>
                        <input type="text" name="notes" class="form-input" placeholder="Optional" autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 8px;">
                        <i class="ph-bold ph-plus-circle"></i> <span>Log Restock</span>
                    </button>
                </form>
            </div>

            <!-- ===================== HISTORY LEDGER ===================== -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-table"></i> Restock History</h3>
                    <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= $total_rows ?> Batches Logged</div>
                </div>

                <form method="GET" action="restock.php" class="filter-bar">
                    <div class="form-group">
                        <label class="form-label">Product</label>
                        <select name="f_product" class="form-select">
                            <option value="0">All products</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $filter_product === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">From</label>
                        <input type="date" name="f_from" class="form-input" value="<?= htmlspecialchars($filter_from) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <input type="date" name="f_to" class="form-input" value="<?= htmlspecialchars($filter_to) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="ph-bold ph-funnel"></i> Filter</button>
                    <a href="restock.php" class="btn" style="background:var(--kami-surface-3);color:var(--kami-text);">Reset</a>
                </form>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Buying</th>
                                <th>Selling</th>
                                <th>Remaining</th>
                                <th>Margin %</th>
                                <th>Logged By</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="9" data-label="Status" style="text-align:center; padding:40px; color: var(--kami-text-dim);">
                                        <i class="ph ph-package" style="font-size: 32px; margin-bottom: 8px; display:block;"></i>
                                        No restock batches match these filters yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $row):
                                    $buy = (float)$row['buying_price'];
                                    $sell = (float)$row['selling_price'];
                                    $margin = margin_percent($buy, $sell);
                                ?>
                                    <tr class="hover-scale">
                                        <td data-label="Date" style="color: var(--kami-text-muted); white-space:nowrap;">
                                            <?= date('M j, Y · g:i A', strtotime($row['purchased_at'])) ?>
                                        </td>
                                        <td data-label="Product" style="font-weight:700;">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </td>
                                        <td data-label="Qty"><?= (int)$row['quantity_bought'] ?></td>
                                        <td data-label="Buying">$<?= number_format($buy, 2) ?></td>
                                        <td data-label="Selling" style="color: var(--kami-accent); font-weight:700;">$<?= number_format($sell, 2) ?></td>
                                        <td data-label="Remaining">
                                            <span class="badge <?= (int)$row['quantity_remaining'] > 0 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= (int)$row['quantity_remaining'] ?> left
                                            </span>
                                        </td>
                                        <td data-label="Margin %">
                                            <?php if ($margin === null): ?>
                                                <span class="margin-na">n/a</span>
                                            <?php else: ?>
                                                <span class="<?= $margin >= 0 ? 'margin-pos' : 'margin-neg' ?>">
                                                    <?= ($margin >= 0 ? '+' : '') . number_format($margin, 1) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Logged By" style="color: var(--kami-text-muted);">
                                            <?= htmlspecialchars($row['logged_by'] ?? '—') ?>
                                        </td>
                                        <td data-label="Edit">
                                            <button type="button" class="btn-icon" title="Correct prices"
                                                onclick='openBatchEdit(<?= (int)$row["id"] ?>, <?= htmlspecialchars(json_encode($row["product_name"]), ENT_QUOTES, "UTF-8") ?>, <?= $buy ?>, <?= $sell ?>)'>
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pager">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(page_link($page - 1)) ?>"><i class="ph ph-caret-left"></i> Prev</a>
                    <?php else: ?>
                        <span class="pager-disabled">Prev</span>
                    <?php endif; ?>
                    <span class="pager-current"><?= $page ?> / <?= $total_pages ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= htmlspecialchars(page_link($page + 1)) ?>">Next <i class="ph ph-caret-right"></i></a>
                    <?php else: ?>
                        <span class="pager-disabled">Next</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- ===================== EDIT BATCH MODAL ===================== -->
    <div class="modal-overlay-ui" id="batchEditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Correct Batch Prices</h3>
                <button class="modal-close" onclick="closeBatchEdit()"><i class="ph ph-x"></i></button>
            </div>
            <p id="batchEditName" style="color: var(--kami-text-muted); margin-bottom: 20px; font-weight: 600;"></p>

            <form action="process_restock.php" method="POST">
                <input type="hidden" name="action" value="edit_batch">
                <input type="hidden" name="batch_id" id="batchEditId">
                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">Buying Price ($)</label>
                        <input type="number" step="0.01" min="0" name="buying_price" id="batchEditBuy" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selling Price ($)</label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="batchEditSell" class="form-input" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 12px;">Save Correction</button>
            </form>
        </div>
    </div>

    <script>
        /* Default purchase date = now (local) */
        (function () {
            var d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            document.getElementById('rsDate').value = d.toISOString().slice(0, 16);
        })();

        /* Pre-fill selling price from product, live margin preview */
        var rsProduct = document.getElementById('rsProduct');
        var rsBuy = document.getElementById('rsBuy');
        var rsSell = document.getElementById('rsSell');
        var rsMargin = document.getElementById('rsMargin');

        function recalcMargin() {
            var b = parseFloat(rsBuy.value);
            var s = parseFloat(rsSell.value);
            if (!b || b <= 0 || isNaN(s)) { rsMargin.value = '—'; rsMargin.style.color = ''; return; }
            var m = ((s - b) / b) * 100;
            rsMargin.value = (m >= 0 ? '+' : '') + m.toFixed(1) + '%';
            rsMargin.style.color = m >= 0 ? '#10b981' : '#ef4444';
        }
        rsProduct.addEventListener('change', function () {
            var opt = rsProduct.options[rsProduct.selectedIndex];
            var sell = opt ? opt.getAttribute('data-selling') : null;
            if (sell && parseFloat(sell) > 0 && !rsSell.value) rsSell.value = parseFloat(sell).toFixed(2);
            recalcMargin();
        });
        rsBuy.addEventListener('input', recalcMargin);
        rsSell.addEventListener('input', recalcMargin);

        /* Batch edit modal */
        function openBatchEdit(id, name, buy, sell) {
            document.getElementById('batchEditId').value = id;
            document.getElementById('batchEditName').innerText = name;
            document.getElementById('batchEditBuy').value = Number(buy).toFixed(2);
            document.getElementById('batchEditSell').value = Number(sell).toFixed(2);
            document.getElementById('batchEditModal').classList.add('active');
        }
        function closeBatchEdit() {
            document.getElementById('batchEditModal').classList.remove('active');
        }
        document.getElementById('batchEditModal').addEventListener('click', function (e) {
            if (e.target === this) closeBatchEdit();
        });

        /* Flash toast from redirect */
        <?php if ($flash_status): ?>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.triggerDynamicIsland) {
                window.triggerDynamicIsland(
                    '<?= $flash_status === 'ok' ? 'Restock Updated' : 'Action Failed' ?>',
                    <?= json_encode($flash_msg) ?>,
                    '<?= $flash_status === 'ok' ? 'success' : 'error' ?>'
                );
            }
        });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>
