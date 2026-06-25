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

// 2. PROCESS NEW PRODUCT FORM (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $sku = filter_input(INPUT_POST, 'sku', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);   // selling price
    $buying = filter_input(INPUT_POST, 'buying_price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    if ($buying === false || $buying === null) $buying = 0.0;

    if ($sku && $name && $price !== false && $stock !== false) {
        try {
            $pdo->beginTransaction();
            // Create the product with its cached batch prices.
            $stmt = $pdo->prepare(
                "INSERT INTO products (sku, name, category, price, buying_price, selling_price, stock)
                 VALUES (:sku, :name, :category, :price, :buying, :price, :stock)"
            );
            $stmt->execute([
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'buying' => $buying,
                'stock' => $stock
            ]);
            $new_pid = (int)$pdo->lastInsertId();

            // Seed an opening batch so FIFO + profit tracking work from day one.
            if ($stock > 0) {
                log_restock($pdo, $new_pid, $stock, (float)$buying, (float)$price, null,
                    'Opening stock (new product)', (int)$_SESSION['user_id']);
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

    if ($edit_id && $edit_price !== false && $edit_stock !== false) {
        $stmt = $pdo->prepare("UPDATE products SET price = :price, stock = :stock WHERE id = :id");
        $stmt->execute([
            'price' => $edit_price,
            'stock' => $edit_stock,
            'id' => $edit_id
        ]);
        $success_msg = "Product metrics synchronized successfully.";
    } else {
        $error_msg = "Invalid data format for update.";
    }
}

// 5. FETCH LIVE INVENTORY (READ)
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .inventory-grid { display: grid; grid-template-columns: 360px 1fr; gap: 20px; }
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

        /* --- MOBILE MENU ELEMENTS --- */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        /* --- TABLET BREAKPOINT --- */
        @media (max-width: 1100px) { 
            .inventory-grid { grid-template-columns: 1fr; } 
        }

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
            .inventory-grid { gap: 32px; }

            /* Sidebar Controls */
            .sidebar, aside { position: fixed !important; top: 0; left: -300px !important; width: 280px !important; height: 100vh !important; z-index: 1000 !important; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.5); }
            body.sidebar-open .sidebar, body.sidebar-open aside { left: 0 !important; }
            .sidebar-overlay { display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(3px); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
            body.sidebar-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

            /* Header Adjustments */
            .page-header-container { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; background: var(--kami-surface-3); border-radius: var(--kami-radius-sm); width: 44px; height: 44px; color: var(--kami-text); cursor: pointer; flex-shrink: 0; border: none; }
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
    </style>

        <h1 class="kami-page-title">Inventory Directory</h1>
        <p class="kami-page-sub">Add and track Ozone Liquor catalog items in the local database.</p>

        <div class="inventory-grid animate-fade-in">
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-plus-circle"></i> Provision Product</h3>
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
                    
                    <button type="submit" name="add_product" class="btn btn-primary btn-block" style="margin-top: 8px;">
                        <i class="ph-bold ph-floppy-disk"></i> <span>Save Product</span>
                    </button>
                </form>
            </div>

            <div class="card glass live-stock-list">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-database"></i> Live Database</h3>
                    <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($products) ?> Premium Listings</div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SKU (Serial Key)</th>
                                <th>Product Designation</th>
                                <th>Category</th>
                                <th>Buying</th>
                                <th>Selling</th>
                                <th>Margin</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" data-label="Status" style="text-align: center; padding: 48px; color: var(--kami-text-dim); display: flex; flex-direction: column; justify-content: center;">
                                        <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px;"></i>
                                        <p>No products logged in database server.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product):
                                    $p_buy = (float)($product['buying_price'] ?? 0);
                                    $p_sell = (float)($product['selling_price'] ?? 0);
                                    if ($p_sell <= 0) $p_sell = (float)$product['price'];
                                    $p_margin = margin_percent($p_buy, $p_sell);
                                ?>
                                    <tr class="hover-scale">
                                        <td data-label="SKU" style="color: var(--kami-text-muted); font-family: monospace; font-size: 13px; font-weight: 700;">
                                            <?= htmlspecialchars($product['sku']) ?>
                                        </td>
                                        <td data-label="Name" style="font-weight: 700; font-size: 15px; color: var(--kami-text);">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </td>
                                        <td data-label="Category">
                                            <span class="badge badge-info" style="font-size: 10px;"><?= htmlspecialchars($product['category']) ?></span>
                                        </td>
                                        <td data-label="Buying" style="color: var(--kami-text-muted);">
                                            $<?= number_format($p_buy, 2) ?>
                                        </td>
                                        <td data-label="Selling" style="font-weight: 700; color: var(--kami-accent);">
                                            $<?= number_format($p_sell, 2) ?>
                                        </td>
                                        <td data-label="Margin">
                                            <?php if ($p_margin === null): ?>
                                                <span style="color: var(--kami-text-dim); font-weight:700;">n/a</span>
                                            <?php else: ?>
                                                <span style="font-weight:800; color: <?= $p_margin >= 0 ? '#10b981' : '#ef4444' ?>;">
                                                    <?= ($p_margin >= 0 ? '+' : '') . number_format($p_margin, 1) ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php if ($product['stock'] <= 5): ?>
                                                <span class="badge badge-danger"><?= htmlspecialchars((string)$product['stock']) ?> Low Units</span>
                                            <?php else: ?>
                                                <span class="badge badge-success"><?= htmlspecialchars((string)$product['stock']) ?> Stocked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-btns">
                                                <button type="button" class="btn-icon" title="Restock (Kurungura)" onclick='openRestockModal(<?= $product["id"] ?>, <?= htmlspecialchars(json_encode($product["name"]), ENT_QUOTES, "UTF-8") ?>, <?= $p_sell ?>)'>
                                                    <i class="ph ph-shopping-cart-simple"></i>
                                                </button>
                                                <button type="button" class="btn-icon" title="Edit Metrics" onclick='openEditModal(<?= $product["id"] ?>, <?= htmlspecialchars(json_encode($product["name"]), ENT_QUOTES, "UTF-8") ?>, <?= $product["price"] ?>, <?= $product["stock"] ?>)'>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <div class="modal-overlay-ui" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Adjust Metrics</h3>
                <button class="modal-close" onclick="closeEditModal()"><i class="ph ph-x"></i></button>
            </div>
            <p id="editProductName" style="color: var(--kami-text-muted); margin-bottom: 20px; font-weight: 600;"></p>
            
            <form action="inventory.php" method="POST">
                <input type="hidden" name="edit_id" id="editIdInput">
                
                <div class="form-group">
                    <label class="form-label">Update Price ($)</label>
                    <input type="number" step="0.01" name="edit_price" id="editPriceInput" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Adjust Stock</label>
                    <input type="number" name="edit_stock" id="editStockInput" class="form-input" required>
                </div>
                
                <button type="submit" name="update_product" class="btn btn-primary btn-block" style="margin-top: 16px;">
                    Confirm Adjustment
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
                <div class="form-row-grid">
                    <div class="form-group">
                        <label class="form-label">Quantity Bought</label>
                        <input type="number" min="1" name="quantity" id="rsModalQty" class="form-input" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Buying Price ($)</label>
                        <input type="number" step="0.01" min="0" name="buying_price" id="rsModalBuy" class="form-input" required placeholder="0.00">
                    </div>
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
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }

        /* ---- Quick restock modal (AJAX -> process_restock.php) ---- */
        function openRestockModal(id, name, sellingPrice) {
            document.getElementById('rsModalPid').value = id;
            document.getElementById('restockProductName').innerText = name;
            document.getElementById('rsModalQty').value = '';
            document.getElementById('rsModalBuy').value = '';
            document.getElementById('rsModalSell').value = (Number(sellingPrice) > 0) ? Number(sellingPrice).toFixed(2) : '';
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

        function openEditModal(id, name, price, stock) {
            document.getElementById('editIdInput').value = id;
            document.getElementById('editProductName').innerText = name;
            document.getElementById('editPriceInput').value = price;
            document.getElementById('editStockInput').value = stock;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        <?php if ($success_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Catalog Synchronized', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        <?php endif; ?>
        <?php if ($error_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Filing Violation', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>