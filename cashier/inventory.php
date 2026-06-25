<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// 2. FETCH LIVE INVENTORY (read-only for cashiers)
$products = $pdo->query("SELECT id, sku, name, category, selling_price, price, stock FROM products ORDER BY name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);

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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="5" data-label="Status" style="text-align:center; padding:48px; color: var(--kami-text-dim);">
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <script>
        function filterInventory() {
            var q = document.getElementById('invSearch').value.toLowerCase();
            document.querySelectorAll('#invTable tbody tr.inv-row').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
            });
        }
    </script>
<?php require '../includes/staff_footer.php'; ?>
