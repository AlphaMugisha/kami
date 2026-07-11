<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/stock_functions.php';

$success_msg = '';
$error_msg = '';

/* ---- branches (for the Availability picker) + all locations (for opening-stock target) ---- */
$branches = $pdo->query("SELECT id, name, is_main FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$main_branch_id = (int)($branches[0]['id'] ?? 1);
foreach ($branches as $b) { if ($b['is_main']) { $main_branch_id = (int)$b['id']; break; } }

// Big Stock is the only door into the system — every restock (new product's
// opening stock, or the quick-restock modal) lands there, full stop.
$warehouse_location_id = (int)($pdo->query("SELECT id FROM stock_locations WHERE is_warehouse = 1 LIMIT 1")->fetchColumn() ?: 0);

/* helper: turn the "availability" form field into [shared, branch_id] */
function parse_availability(string $availability, int $default_branch_id): array
{
    if ($availability === 'shared') {
        return [1, $default_branch_id];
    }
    $bid = (int)str_replace('branch_', '', $availability);
    return [0, $bid ?: $default_branch_id];
}

/* helper: human label for a product's availability */
function availability_label(array $product, array $branches): string
{
    if ((int)$product['shared']) return 'Shared — both branches';
    foreach ($branches as $b) {
        if ((int)$b['id'] === (int)$product['branch_id']) {
            return htmlspecialchars($b['name']) . ' only';
        }
    }
    return 'Exclusive';
}

// 2. PROCESS NEW PRODUCT FORM (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $sku = filter_input(INPUT_POST, 'sku', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);   // selling price
    $buying = filter_input(INPUT_POST, 'buying_price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $availability = (string)($_POST['availability'] ?? 'shared');
    if ($buying === false || $buying === null) $buying = 0.0;
    $units_per_box = filter_input(INPUT_POST, 'units_per_box', FILTER_VALIDATE_INT);
    if ($units_per_box !== false && $units_per_box <= 0) $units_per_box = null;
    if ($units_per_box === false) $units_per_box = null;

    [$shared, $product_branch_id] = parse_availability($availability, $main_branch_id);

    if ($sku && $name && $price !== false && $stock !== false) {
        try {
            $pdo->beginTransaction();
            // Create the product with its cached batch prices.
            $stmt = $pdo->prepare(
                "INSERT INTO products (branch_id, shared, sku, name, category, price, buying_price, selling_price, stock, units_per_box)
                 VALUES (:bid, :shared, :sku, :name, :category, :price, :buying, :price2, :stock, :upb)"
            );
            $stmt->execute([
                'bid' => $product_branch_id,
                'shared' => $shared,
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'buying' => $buying,
                'price2' => $price,
                'stock' => $stock,
                'upb' => $units_per_box,
            ]);
            $new_pid = (int)$pdo->lastInsertId();

            // Seed an opening batch so FIFO + profit tracking work from day one.
            // Big Stock is the only door into the system — opening stock lands
            // there too, same as every other restock.
            if ($stock > 0 && $warehouse_location_id) {
                log_restock($pdo, get_location_branch($pdo, $warehouse_location_id), $new_pid, $stock, (float)$buying, (float)$price, null,
                    'Opening stock (new product)', (int)$_SESSION['user_id'], $warehouse_location_id);
                // log_restock also adds to stock; undo the double-count from the INSERT above.
                $fix = $pdo->prepare("UPDATE products SET stock = :stock WHERE id = :id");
                $fix->execute(['stock' => $stock, 'id' => $new_pid]);
            }

            $pdo->commit();
            $success_msg = "Product '$name' added successfully.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error_msg = "A product with SKU '$sku' already exists.";
            } else {
                $error_msg = "Database error: " . $e->getMessage();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Could not add product: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please fill all fields correctly.";
    }
}

// 3. PROCESS DELETE PRODUCT (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute(['id' => $delete_id]);
            $success_msg = "Product securely removed from catalog.";
        } catch (PDOException $e) {
            $error_msg = "Cannot delete product. It may be linked to existing sales records.";
        }
    }
}

// 4. PROCESS UPDATE PRODUCT (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
    $edit_price = filter_input(INPUT_POST, 'edit_price', FILTER_VALIDATE_FLOAT);
    $edit_stock = filter_input(INPUT_POST, 'edit_stock', FILTER_VALIDATE_INT);
    $edit_availability = (string)($_POST['edit_availability'] ?? 'shared');
    $edit_units_per_box = filter_input(INPUT_POST, 'edit_units_per_box', FILTER_VALIDATE_INT);
    if ($edit_units_per_box !== false && $edit_units_per_box <= 0) $edit_units_per_box = null;
    if ($edit_units_per_box === false) $edit_units_per_box = null;

    if ($edit_id && $edit_price !== false && $edit_stock !== false) {
        [$shared, $product_branch_id] = parse_availability($edit_availability, $main_branch_id);
        $stmt = $pdo->prepare("UPDATE products SET price = :price, stock = :stock, shared = :shared, branch_id = :bid, units_per_box = :upb WHERE id = :id");
        $stmt->execute([
            'price' => $edit_price,
            'stock' => $edit_stock,
            'shared' => $shared,
            'bid' => $product_branch_id,
            'upb' => $edit_units_per_box,
            'id' => $edit_id,
        ]);
        $success_msg = "Product metrics synchronized successfully.";
    } else {
        $error_msg = "Invalid data format for update.";
    }
}

// 5. FETCH LIVE INVENTORY (READ) — the whole shared catalog
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$userName = $_SESSION['full_name'] ?? 'Admin';
$userRole = ucfirst($_SESSION['role'] ?? 'Admin');
?>
<?php
$staff_area = 'admin';
$page_title = 'Inventory';
require '../includes/staff_header.php';
?>
    <style>
        /* --- DESKTOP LAYOUT --- */
        .live-stock-list { min-height: 400px; }

        /* Grid for form rows */
        .form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Actions styling */
        .action-btns { display: flex; gap: 8px; }
        .btn-icon { background: rgba(255,255,255,0.05); border: 1px solid var(--kami-border); color: var(--kami-text); padding: 8px; border-radius: 6px; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon:hover { background: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .btn-icon.danger:hover { background: rgba(239, 68, 68, 0.2); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }

        /* Edit Modal Styling */
        .modal-overlay-ui { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay-ui.active { display: flex; opacity: 1; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); border-radius: 16px; padding: 32px; width: 100%; max-width: 400px; transform: translateY(20px); transition: transform 0.3s ease; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .modal-overlay-ui.active .modal-content { transform: translateY(0); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; color: var(--kami-text-muted); font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: var(--kami-text); }

        /* Desktop Table Styling */
        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        /* --- MOBILE UX BREAKPOINT (THE MAGIC HAPPENS HERE) --- */
        @media (max-width: 768px) {

            /* TRUE EDGE-TO-EDGE MOBILE FULLSCREEN */
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; margin: 0 !important; }

            /* Remove the card-like floating effect from main content */
            .main-content {
                margin: 0 !important;
                padding: 16px !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                min-height: 100vh;
            }

            .card, .glass { border: none !important; background: transparent; padding: 0;}
            .card-header { padding: 0 0 16px 0 !important; }

            /* Form Grid fixes for mobile */
            .form-row-grid { grid-template-columns: 1fr; } /* Stack price and stock */

            /* Header Adjustments */
            .page-header-container { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
            .page-header { margin-bottom: 0 !important; }
            .page-header p { display: none; }

            /* Better Touch Targets */
            .form-input, .form-select { padding: 16px 14px; font-size: 16px; background: var(--kami-surface-2); border: 1px solid var(--kami-border); }
            .btn { padding: 16px 20px; font-size: 15px; }

            /* =========================================
                TRUE MOBILE TABLE REWRITE (CARDS)
                ========================================= */

            /* Hide the Desktop Header completely */
            .data-table thead { display: none; }

            /* Convert table elements to block to break the grid */
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }

            /* Turn every Row into a standalone Card */
            .data-table tr {
                margin-bottom: 16px;
                background: var(--kami-surface-2);
                border: 1px solid var(--kami-border) !important;
                border-radius: var(--kami-radius-md);
                padding: 16px;
            }

            /* Style every Cell using Flexbox */
            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0 !important;
                border-bottom: 1px solid rgba(255,255,255,0.04) !important;
                text-align: right;
            }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }

            /* Inject the Column Title from HTML data-label */
            .data-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--kami-text-muted);
                font-size: 13px;
                text-align: left;
                padding-right: 16px;
            }

            /* Push action buttons to the right */
            .action-btns { width: auto; }

            /* Modal mobile fixes */
            .modal-content { padding: 24px; width: 90%; }
        }

        .avail-note { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        .avail-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; padding: 3px 8px; border-radius: var(--kami-radius-full); }
        .avail-badge.shared { background: var(--kami-info-bg); color: var(--kami-info); border: 1px solid var(--kami-info-border); }
        .avail-badge.exclusive { background: var(--kami-warning-bg); color: var(--kami-warning); border: 1px solid var(--kami-warning-border); }
    </style>

        <h1 class="kami-page-title">Products</h1>
        <p class="kami-page-sub">Every product in the catalog — mark one Shared to sell it at either branch, or exclusive to just one.</p>

        <div class="avail-note animate-fade-in">
            <span class="badge badge-info"><?= count($products) ?> Products</span>
            <a href="locations.php" class="btn" style="background:var(--kami-surface-3); color:var(--kami-text);">
                <i class="ph-bold ph-grid-four"></i> See stock by location →
            </a>
        </div>

        <div class="card glass live-stock-list animate-fade-in">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3><i class="ph-bold ph-package"></i> All Products</h3>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="ph-bold ph-plus"></i> Add Product
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Selling Price</th>
                                <th>Stock</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Availability</th>
                                <th>Buying Price</th>
                                <th>Margin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 48px; color: var(--kami-text-dim); display: flex; flex-direction: column; justify-content: center;">
                                        <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px;"></i>
                                        <p>No products yet.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product):
                                    $p_buy = (float)($product['buying_price'] ?? 0);
                                    $p_sell = (float)($product['selling_price'] ?? 0);
                                    if ($p_sell <= 0) $p_sell = (float)$product['price'];
                                    $p_margin = margin_percent($p_buy, $p_sell);
                                    $availability_value = $product['shared'] ? 'shared' : ('branch_' . (int)$product['branch_id']);
                                ?>
                                    <tr class="hover-scale">
                                        <td data-label="Product" style="font-weight: 700; font-size: 15px; color: var(--kami-text);">
                                            <?= htmlspecialchars($product['name']) ?>
                                            <?php if (!empty($product['units_per_box'])): ?>
                                                <span class="badge badge-info" style="font-size: 9px; margin-left: 6px; font-weight: 700;" title="Units per box"><?= (int)$product['units_per_box'] ?>/box</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Selling Price" style="font-weight: 700; color: var(--kami-accent);">
                                            $<?= number_format($p_sell, 2) ?>
                                        </td>
                                        <td data-label="Stock">
                                            <?php if ($product['stock'] <= 5): ?>
                                                <span class="badge badge-danger"><?= htmlspecialchars((string)$product['stock']) ?> Low Units</span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><?= htmlspecialchars((string)$product['stock']) ?> Stocked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="SKU" class="row-secondary" style="color: var(--kami-text-muted); font-family: monospace; font-size: 13px; font-weight: 700;">
                                            <?= htmlspecialchars($product['sku']) ?>
                                        </td>
                                        <td data-label="Category" class="row-secondary">
                                            <span class="badge badge-info" style="font-size: 10px;"><?= htmlspecialchars($product['category']) ?></span>
                                        </td>
                                        <td data-label="Availability" class="row-secondary">
                                            <span class="avail-badge <?= $product['shared'] ? 'shared' : 'exclusive' ?>"><?= availability_label($product, $branches) ?></span>
                                        </td>
                                        <td data-label="Buying Price" class="row-secondary" style="color: var(--kami-text-muted);">
                                            $<?= number_format($p_buy, 2) ?>
                                        </td>
                                        <td data-label="Margin" class="row-secondary">
                                            <?php if ($p_margin === null): ?>
                                                <span style="color: var(--kami-text-dim); font-weight:700;">n/a</span>
                                            <?php else: ?>
                                                <span style="font-weight:800; color: <?= $p_margin >= 0 ? '#10b981' : '#ef4444' ?>;">
                                                    <?= ($p_margin >= 0 ? '+' : '') . number_format($p_margin, 1) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-btns">
                                                <button type="button" class="btn-icon" title="Restock (Kurungura)" onclick='openRestockModal(<?= $product["id"] ?>, <?= htmlspecialchars(json_encode($product["name"]), ENT_QUOTES, "UTF-8") ?>, <?= $p_sell ?>, <?= $product["units_per_box"] !== null ? (int)$product["units_per_box"] : "null" ?>)'>
                                                    <i class="ph ph-shopping-cart-simple"></i>
                                                </button>
                                                <button type="button" class="btn-icon" title="Edit Product" onclick='openEditModal(<?= $product["id"] ?>, <?= htmlspecialchars(json_encode($product["name"]), ENT_QUOTES, "UTF-8") ?>, <?= $product["price"] ?>, <?= $product["stock"] ?>, <?= htmlspecialchars(json_encode($availability_value), ENT_QUOTES, "UTF-8") ?>, <?= $product["units_per_box"] !== null ? (int)$product["units_per_box"] : "null" ?>)'>
                                                    <i class="ph ph-pencil-simple"></i>
                                                </button>
                                                <form action="inventory.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="delete_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="delete_product" class="btn-icon danger" title="Delete Product">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="row-expand-cell">
                                            <button type="button" class="row-expand-btn"><i class="ph-bold ph-caret-down"></i> Details</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </div>

    <div class="modal-overlay-ui" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-plus-circle"></i> Add Product</h3>
                <button class="modal-close" onclick="closeAddProductModal()"><i class="ph ph-x"></i></button>
            </div>

            <form action="inventory.php" method="POST">
                <div class="form-group">
                    <label class="form-label">SKU (Barcode Identifier)</label>
                    <input type="text" name="sku" class="form-input" required placeholder="e.g. HEN-VSOP-750" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-input" required placeholder="Hennessy VSOP 750ml">
                </div>
                <div class="form-group">
                    <label class="form-label">Category Group</label>
                    <select name="category" class="form-select" required>
                        <option value="Cognac">Cognac</option>
                        <option value="Whiskey">Whiskey</option>
                        <option value="Vodka">Vodka</option>
                        <option value="Wine">Wine</option>
                        <option value="Beer">Beer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Availability</label>
                    <select name="availability" class="form-select" required>
                        <option value="shared">Shared — sellable at either branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="branch_<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?> only</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">Buying Price ($)</label>
                        <input type="number" step="0.01" min="0" name="buying_price" class="form-input" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selling Price ($)</label>
                        <input type="number" step="0.01" name="price" class="form-input" required placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Initial Stock</label>
                    <input type="number" name="stock" class="form-input" required placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Units Per Box <span style="color:var(--kami-text-dim);font-weight:400;">(optional — leave blank if never sold/sent by the box)</span></label>
                    <input type="number" min="1" step="1" name="units_per_box" class="form-input" placeholder="e.g. 24">
                </div>
                <p style="font-size: 12.5px; color: var(--kami-text-dim); margin: -8px 0 4px;">Opening stock lands in Big Stock, same as every restock.</p>

                <button type="submit" name="add_product" class="btn btn-primary btn-block" style="margin-top: 8px;">
                    <i class="ph-bold ph-floppy-disk"></i> <span>Save Product</span>
                </button>
            </form>
        </div>
    </div>

    <div class="modal-overlay-ui" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="modal-close" onclick="closeEditModal()"><i class="ph ph-x"></i></button>
            </div>
            <p id="editProductName" style="color: var(--kami-text-muted); margin-bottom: 20px; font-weight: 600;"></p>

            <form action="inventory.php" method="POST">
                <input type="hidden" name="edit_id" id="editIdInput">

                <div class="form-group">
                    <label class="form-label">Selling Price ($)</label>
                    <input type="number" step="0.01" name="edit_price" id="editPriceInput" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stock (total across both branches)</label>
                    <input type="number" name="edit_stock" id="editStockInput" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Units Per Box <span style="color:var(--kami-text-dim);font-weight:400;">(optional)</span></label>
                    <input type="number" min="1" step="1" name="edit_units_per_box" id="editUnitsPerBoxInput" class="form-input" placeholder="e.g. 24">
                </div>
                <div class="form-group">
                    <label class="form-label">Availability</label>
                    <select name="edit_availability" id="editAvailabilityInput" class="form-select" required>
                        <option value="shared">Shared — sellable at either branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="branch_<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?> only</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="update_product" class="btn btn-primary btn-block" style="margin-top: 16px;">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <div class="modal-overlay-ui" id="restockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Restock · Kurungura</h3>
                <button class="modal-close" onclick="closeRestockModal()"><i class="ph ph-x"></i></button>
            </div>
            <p id="restockProductName" style="color: var(--kami-text-muted); margin-bottom: 20px; font-weight: 600;"></p>

            <form id="quickRestockForm" onsubmit="return submitQuickRestock(event)">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="product_id" id="rsModalPid">
                <input type="hidden" name="location_id" value="<?= $warehouse_location_id ?>">
                <p style="font-size: 12.5px; color: var(--kami-text-dim); margin: -4px 0 16px;">Restocking into <strong style="color:var(--kami-text);">Big Stock</strong>.</p>
                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">Quantity Bought</label>
                        <input type="number" min="1" name="quantity" id="rsModalQty" class="form-input" required placeholder="0">
                    </div>
                    <div class="form-group" id="rsModalUnitTypeGroup" style="display:none;">
                        <label class="form-label">Sent As</label>
                        <select name="unit_type" id="rsModalUnitType" class="form-select">
                            <option value="unit">Units</option>
                            <option value="box">Boxes</option>
                        </select>
                        <p class="avail-hint" id="rsModalBoxHint" style="margin: 6px 0 0;"></p>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Buying Price ($ per unit)</label>
                    <input type="number" step="0.01" min="0" name="buying_price" id="rsModalBuy" class="form-input" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Selling Price ($)</label>
                    <input type="number" step="0.01" min="0" name="selling_price" id="rsModalSell" class="form-input" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes <span style="color:var(--kami-text-dim);font-weight:400;">(supplier, invoice…)</span></label>
                    <input type="text" name="notes" class="form-input" placeholder="Optional" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="quickRestockBtn" style="margin-top: 12px;">
                    <i class="ph-bold ph-plus-circle"></i> <span>Log Restock</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAddProductModal() {
            document.getElementById('addProductModal').classList.add('active');
        }
        function closeAddProductModal() {
            document.getElementById('addProductModal').classList.remove('active');
        }
        document.getElementById('addProductModal').addEventListener('click', function (e) {
            if (e.target === this) closeAddProductModal();
        });

        /* ---- Quick restock modal (AJAX -> process_restock.php) ---- */
        function openRestockModal(id, name, sellingPrice, unitsPerBox) {
            document.getElementById('rsModalPid').value = id;
            document.getElementById('restockProductName').innerText = name;
            document.getElementById('rsModalQty').value = '';
            document.getElementById('rsModalBuy').value = '';
            document.getElementById('rsModalSell').value = (Number(sellingPrice) > 0) ? Number(sellingPrice).toFixed(2) : '';

            const unitTypeGroup = document.getElementById('rsModalUnitTypeGroup');
            const unitTypeSelect = document.getElementById('rsModalUnitType');
            const boxHint = document.getElementById('rsModalBoxHint');
            unitTypeSelect.value = 'unit';
            if (unitsPerBox) {
                unitTypeGroup.style.display = '';
                boxHint.textContent = '1 box = ' + unitsPerBox + ' units';
            } else {
                unitTypeGroup.style.display = 'none';
            }

            document.getElementById('restockModal').classList.add('active');
        }
        function closeRestockModal() {
            document.getElementById('restockModal').classList.remove('active');
        }
        async function submitQuickRestock(e) {
            e.preventDefault();
            var btn = document.getElementById('quickRestockBtn');
            btn.disabled = true;
            try {
                var fd = new FormData(document.getElementById('quickRestockForm'));
                var res = await fetch('process_restock.php', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) {
                    closeRestockModal();
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Stock Replenished', data.message, 'success');
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    if (window.triggerDynamicIsland) window.triggerDynamicIsland('Restock Failed', data.message || 'Unknown error', 'error');
                    btn.disabled = false;
                }
            } catch (err) {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Network Error', 'Could not reach the server.', 'error');
                btn.disabled = false;
            }
            return false;
        }
        document.getElementById('restockModal').addEventListener('click', function (e) {
            if (e.target === this) closeRestockModal();
        });

        function openEditModal(id, name, price, stock, availability, unitsPerBox) {
            document.getElementById('editIdInput').value = id;
            document.getElementById('editProductName').innerText = name;
            document.getElementById('editPriceInput').value = price;
            document.getElementById('editStockInput').value = stock;
            document.getElementById('editAvailabilityInput').value = availability;
            document.getElementById('editUnitsPerBoxInput').value = unitsPerBox || '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        <?php if ($success_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Saved', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        <?php endif; ?>
        <?php if ($error_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Error', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>