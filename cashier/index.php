<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

// Strict redirect if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// Fetch all available products (including category) to inject into the JavaScript engine
$stmt = $pdo->query("SELECT id, sku, name, category, price, stock FROM products WHERE stock > 0 ORDER BY name ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$staff_area = 'cashier';
$page_title = 'Sales Register';
require '../includes/staff_header.php';
?>
    <style>
        /* POS fills the viewport under the shell topbar */
        .kami-content { max-width: none; padding: 20px; }
        .pos-container {
            flex: 1;
            display: flex;
            gap: 20px;
            z-index: 10;
            overflow: hidden;
            height: calc(100vh - var(--staff-topbar-h) - 40px);
            padding: 0;
        }

        .products-pane { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            padding: 28px;
            border-radius: var(--kami-radius-xl);
        }

        .pos-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        /* 2026 Search Field */
        .pos-search-wrapper {
            position: relative;
            flex: 1;
            max-width: 360px;
        }

        .pos-search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--kami-text-dim);
            font-size: 18px;
        }

        .pos-search-input {
            padding-left: 48px !important;
            height: 44px;
        }

        /* 2026 Category capsules styling */
        .category-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 12px;
            margin-bottom: 4px;
            scrollbar-width: none;
        }
        .category-scroll::-webkit-scrollbar { display: none; }

        .category-capsule {
            padding: 10px 20px;
            background: var(--kami-surface-2);
            border: 1px solid var(--kami-border);
            border-radius: var(--kami-radius-full);
            font-weight: 700;
            font-size: 13px;
            color: var(--kami-text-muted);
            cursor: pointer;
            transition: all var(--kami-transition);
            white-space: nowrap;
        }

        .category-capsule:hover {
            color: var(--kami-text);
            border-color: var(--kami-border-hover);
            transform: translateY(-1px);
        }

        .category-capsule.active {
            background: var(--kami-accent);
            color: #000000;
            border-color: var(--kami-accent-hover);
            box-shadow: var(--kami-glow);
        }

        .product-grid-scroll {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
            padding-top: 12px;
            border-top: 1px solid var(--kami-border);
        }

        /* Cart layout */
        .register-pane { 
            width: 420px; 
            display: flex; 
            flex-direction: column; 
            padding: 28px;
            border-radius: var(--kami-radius-xl);
        }
        
        .cart-items { 
            flex: 1; 
            overflow-y: auto; 
            margin-bottom: 20px; 
            padding-right: 4px; 
        }

        /* 2026 Cart rows styling */
        .cart-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 12px 16px; 
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--kami-border);
            border-radius: var(--kami-radius-md);
            margin-bottom: 10px;
            transition: all var(--kami-transition);
        }

        .cart-item:hover {
            border-color: var(--kami-border-hover);
            background: rgba(255, 255, 255, 0.04);
            transform: translateY(-1px);
        }

        .item-info h5 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--kami-text);
        }

        .item-info span {
            font-size: 13px;
            color: var(--kami-accent);
            font-weight: 700;
        }

        /* Dynamic stepper controls */
        .qty-controls { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background: var(--kami-surface-3); 
            padding: 6px 12px; 
            border-radius: var(--kami-radius-full); 
            border: 1px solid var(--kami-border); 
        }

        .qty-btn { 
            background: none; 
            border: none; 
            color: var(--kami-text-muted); 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 13px;
            width: 20px;
            height: 20px;
            transition: all var(--kami-transition);
        }

        .qty-btn:hover { 
            color: var(--kami-text); 
            transform: scale(1.15);
        }
        
        .qty-val {
            font-size: 14px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }

        .register-totals { 
            border-top: 1px solid var(--kami-border); 
            padding-top: 20px; 
        }

        .total-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px; 
            font-size: 14px; 
            color: var(--kami-text-muted); 
            font-weight: 600;
        }

        .total-row.grand-total { 
            font-size: 28px; 
            font-weight: 700; 
            color: var(--kami-text); 
            margin-bottom: 24px; 
            margin-top: 12px; 
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -0.04em;
        }

        .pos-action-grid {
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 12px; 
        }

        /* Thermal Receipt Print Styles */
        #receipt-canvas { display: none; }
        @media print {
            body * { visibility: hidden; }
            body { background: #fff !important; color: #000 !important; }
            #receipt-canvas, #receipt-canvas * { visibility: visible; }
            #receipt-canvas { display: block; position: absolute; left: 0; top: 0; width: 80mm; padding: 5mm; font-family: monospace; font-size: 12px; }
            .receipt-header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 10px; }
            .receipt-item { display: flex; justify-content: space-between; margin: 5px 0; }
        }

        /* RECALL MODAL STYLES */
        .recall-card { background: var(--kami-surface-2); border: 1px solid var(--kami-border); padding: 16px; border-radius: var(--kami-radius-lg); cursor: pointer; transition: all 0.2s ease; }
        .recall-card:hover { border-color: var(--kami-accent); background: var(--kami-surface-3); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 9999; display: none; align-items: center; justify-content: center; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); width: 450px; border-radius: var(--kami-radius-xl); padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
    </style>

    <div class="pos-container animate-fade-in">
        <div class="products-pane card glass">
            <div class="pos-header-actions">
                <h2 style="font-size: 24px; font-weight: 700; color: var(--kami-text); font-family: 'Space Grotesk';">Sales Register</h2>
                
                <div class="pos-search-wrapper">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" class="form-input pos-search-input" id="productSearch" placeholder="Search product name or SKU...">
                </div>
            </div>

            <div class="category-scroll">
                <div class="category-capsule active" data-category="All" onclick="selectCategory('All')">All Categories</div>
                <div class="category-capsule" data-category="Cognac" onclick="selectCategory('Cognac')">Cognac</div>
                <div class="category-capsule" data-category="Whiskey" onclick="selectCategory('Whiskey')">Whiskey</div>
                <div class="category-capsule" data-category="Vodka" onclick="selectCategory('Vodka')">Vodka</div>
                <div class="category-capsule" data-category="Wine" onclick="selectCategory('Wine')">Wine</div>
                <div class="category-capsule" data-category="Beer" onclick="selectCategory('Beer')">Beer</div>
            </div>

            <div class="product-grid-scroll">
                <div class="product-grid" id="productGrid">
                    </div>
            </div>
        </div>

        <div class="register-pane card glass">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="ph-bold ph-shopping-cart-simple" style="color: var(--kami-accent);"></i> Active Basket</h3>
                <div style="display: flex; gap: 8px;">
                    <button class="btn btn-sm" style="background: rgba(245, 158, 11, 0.1); color: var(--kami-accent); border: 1px solid var(--kami-accent-border);" onclick="openRecallModal()">
                        <i class="ph-bold ph-bell-ringing"></i> Recall Table
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="clearCart()">Clear</button>
                </div>
            </div>
            
            <div class="cart-items" id="cartItems">
                <div style="text-align: center; color: var(--kami-text-dim); margin-top: 100px;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--kami-surface-3); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; border: 1px solid var(--kami-border);">
                        <i class="ph-fill ph-shopping-cart-simple" style="font-size: 24px; color: var(--kami-text-dim);"></i>
                    </div>
                    <p class="fw-600" style="font-size: 14px; color: var(--kami-text);">Register Basket is Empty</p>
                    <p style="font-size: 12px; opacity: 0.7; margin-top: 4px;">Tap stock tiles on the left to add items.</p>
                </div>
            </div>

            <div class="register-totals">
                <div class="total-row">
                    <span>Taxable Subtotal</span>
                    <span id="subtotalAmount">$0.00</span>
                </div>
                <div class="total-row">
                    <span>VAT Surcharge (18%)</span>
                    <span id="taxAmount">$0.00</span>
                </div>
                <div class="total-row grand-total">
                    <span>Grand Total</span>
                    <span id="totalAmount">$0.00</span>
                </div>

                <div class="pos-action-grid">
                    <button class="btn btn-secondary" onclick="printReceipt()">
                        <i class="ph ph-printer"></i> Print Bill
                    </button>
                    <button class="btn btn-primary" onclick="checkout()" id="checkoutBtn">
                        <i class="ph ph-credit-card"></i> Pay Charge
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="recallModal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--kami-border); padding-bottom: 16px; margin-bottom: 16px;">
                <h2 style="font-size: 18px; color: var(--kami-text); font-weight: 700; margin: 0;"><i class="ph-bold ph-bell-ringing" style="color: var(--kami-accent);"></i> Pending Tables</h2>
                <button onclick="closeRecallModal()" style="background: transparent; border: none; color: var(--kami-text-muted); font-size: 20px; cursor: pointer;"><i class="ph-bold ph-x"></i></button>
            </div>
            
            <div id="recallList" style="max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding-right: 4px;">
                <p style="color: var(--kami-text-muted); text-align: center; font-style: italic;">Loading orders...</p>
            </div>
        </div>
    </div>

    <div id="receipt-canvas">
        <div class="receipt-header">
            <h3 style="margin-bottom: 4px;">OZONE LIQUOR</h3>
            <p style="margin-bottom: 4px;">Kigali, Rwanda</p>
            <p>Date: <span id="receiptDate"></span></p>
        </div>
        <div id="receiptItemsBody"></div>
        <div class="receipt-totals">
            <div class="receipt-item">
                <span>Subtotal:</span>
                <span id="receiptSubtotal"></span>
            </div>
            <div class="receipt-item">
                <span>VAT (18%):</span>
                <span id="receiptTax"></span>
            </div>
            <div class="receipt-total-row" style="margin-top: 8px;">
                <span>TOTAL:</span>
                <span id="receiptTotal"></span>
            </div>
        </div>
        <div class="receipt-footer">
            <p>Thank you for choosing Ozone Liquor!</p>
            <p>System Powered by Kami</p>
        </div>
    </div>

    <script>
        const inventory = <?= json_encode($products); ?>;
        let cart = {};
        let activeCategory = 'All';
        
        // NEW: Track if we are cashing out a specific QR order
        let activeQrOrderId = null;
        let pendingTableOrders = [];

        const productGrid = document.getElementById('productGrid');
        const cartItemsContainer = document.getElementById('cartItems');

        function selectCategory(category) {
            activeCategory = category;
            const capsules = document.querySelectorAll('.category-capsule');
            capsules.forEach(capsule => {
                if (capsule.getAttribute('data-category') === category) capsule.classList.add('active');
                else capsule.classList.remove('active');
            });
            renderProducts(document.getElementById('productSearch').value);
        }

        function renderProducts(filter = '') {
            productGrid.innerHTML = '';
            let filtered = inventory;
            
            if (activeCategory !== 'All') filtered = filtered.filter(p => p.category === activeCategory);

            if (filter.trim() !== '') {
                filtered = filtered.filter(p => 
                    p.name.toLowerCase().includes(filter.toLowerCase()) || 
                    p.sku.toLowerCase().includes(filter.toLowerCase())
                );
            }

            if (filtered.length === 0) {
                productGrid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 48px; color: var(--kami-text-dim);">
                        <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px;"></i>
                        <p style="font-size: 15px; font-weight: 700;">No Catalog Items Located</p>
                    </div>`;
                return;
            }

            filtered.forEach(product => {
                const card = document.createElement('div');
                card.className = 'product-card animate-fade-in';
                card.onclick = () => addToCart(product);
                card.innerHTML = `
                    <h4>${product.name}</h4>
                    <p>$${parseFloat(product.price).toFixed(2)}</p>
                    <span class="stock">Units Available: ${product.stock}</span>
                    <span class="badge badge-info" style="position: absolute; right: 12px; top: 12px; font-size: 9px; padding: 3px 6px;">${product.category}</span>
                `;
                productGrid.appendChild(card);
            });
        }

        function addToCart(product, qtyToInject = 1) {
            if (cart[product.id]) {
                if (cart[product.id].qty + qtyToInject <= product.stock) {
                    cart[product.id].qty += qtyToInject;
                } else {
                    cart[product.id].qty = product.stock;
                    if(window.triggerDynamicIsland) window.triggerDynamicIsland('Stock Limit', `Cannot exceed stock ceiling for ${product.name}.`, 'warning');
                }
            } else {
                cart[product.id] = { ...product, qty: (qtyToInject > product.stock ? product.stock : qtyToInject) };
            }
            updateCartUI();
        }

        function adjustQty(id, delta) {
            if (cart[id]) {
                const item = cart[id];
                item.qty += delta;
                if (item.qty <= 0) {
                    delete cart[id];
                } else if (item.qty > item.stock) {
                    item.qty = item.stock;
                    if(window.triggerDynamicIsland) window.triggerDynamicIsland('Stock Limit', `Only ${item.stock} units available.`, 'warning');
                }
            }
            updateCartUI();
        }

        function clearCart() {
            if (Object.keys(cart).length === 0) return;
            cart = {};
            activeQrOrderId = null; // Unlink table order if cleared
            updateCartUI();
            if(window.triggerDynamicIsland) window.triggerDynamicIsland('Basket Cleared', 'All registry basket products wiped.', 'info');
        }

        function updateCartUI() {
            cartItemsContainer.innerHTML = '';
            let subtotal = 0;

            if (Object.keys(cart).length === 0) {
                cartItemsContainer.innerHTML = `
                    <div style="text-align: center; color: var(--kami-text-dim); margin-top: 100px;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--kami-surface-3); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; border: 1px solid var(--kami-border);">
                            <i class="ph-fill ph-shopping-cart-simple" style="font-size: 24px; color: var(--kami-text-dim);"></i>
                        </div>
                        <p class="fw-600" style="font-size: 14px; color: var(--kami-text);">Register Basket is Empty</p>
                    </div>`;
            } else {
                for (let id in cart) {
                    const item = cart[id];
                    const itemTotal = item.price * item.qty;
                    subtotal += itemTotal;

                    cartItemsContainer.innerHTML += `
                        <div class="cart-item">
                            <div class="item-info">
                                <h5>${item.name}</h5>
                                <span>$${parseFloat(item.price).toFixed(2)} each</span>
                            </div>
                            <div class="qty-controls">
                                <button class="qty-btn" onclick="adjustQty(${id}, -1)"><i class="ph-bold ph-minus"></i></button>
                                <span class="qty-val">${item.qty}</span>
                                <button class="qty-btn" onclick="adjustQty(${id}, 1)"><i class="ph-bold ph-plus"></i></button>
                            </div>
                        </div>
                    `;
                }
            }

            const tax = subtotal * 0.18;
            const total = subtotal + tax;

            document.getElementById('subtotalAmount').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('taxAmount').innerText = `$${tax.toFixed(2)}`;
            document.getElementById('totalAmount').innerText = `$${total.toFixed(2)}`;
        }

        document.getElementById('productSearch').addEventListener('input', (e) => {
            renderProducts(e.target.value);
        });

        // ==========================================
        // NEW: RECALL TABLE ORDER SYSTEM
        // ==========================================
        function openRecallModal() {
            document.getElementById('recallModal').style.display = 'flex';
            document.getElementById('recallList').innerHTML = '<p style="color: var(--kami-text-dim); text-align: center;"><i class="ph ph-spinner ph-spin"></i> Fetching Live Orders...</p>';
            
            fetch('live_orders.php?ajax=fetch')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('recallList').innerHTML = '<p style="color: #ef4444; text-align: center;">Connection Error.</p>';
                        return;
                    }
                    
                    pendingTableOrders = data.orders.filter(o => o.status === 'pending' || o.status === 'preparing');
                    let html = '';
                    
                    if (pendingTableOrders.length === 0) {
                        html = '<p style="color: var(--kami-text-dim); text-align: center; padding: 20px;">No tables require checkout.</p>';
                    } else {
                        pendingTableOrders.forEach(order => {
                            html += `
                            <div class="recall-card" onclick="loadOrderIntoBasket(${order.id})">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-weight: 700; color: var(--kami-text); font-size: 16px;">${order.table}</span>
                                    <span style="color: var(--kami-accent); font-weight: 800;">$${parseFloat(order.total).toFixed(2)}</span>
                                </div>
                                <div style="font-size: 12px; color: var(--kami-text-dim); margin-bottom: 8px;">
                                    <i class="ph-bold ph-clock"></i> ${order.time} &nbsp;|&nbsp; <span style="text-transform: uppercase;">${order.method}</span>
                                </div>
                                <div style="font-size: 13px; color: var(--kami-text-muted);">
                                    ${order.items.join('<br>')}
                                </div>
                            </div>`;
                        });
                    }
                    document.getElementById('recallList').innerHTML = html;
                }).catch(err => console.error(err));
        }

        function closeRecallModal() {
            document.getElementById('recallModal').style.display = 'none';
        }

        function loadOrderIntoBasket(orderId) {
            const orderData = pendingTableOrders.find(o => o.id === orderId);
            if (!orderData) return;

            // Clear current POS cart
            cart = {}; 
            let missingItems = [];

            // Parse text string to find objects in POS Inventory array
            orderData.items.forEach(itemString => {
                const match = itemString.match(/^(\d+)x\s+(.+)$/);
                if (match) {
                    const qty = parseInt(match[1]);
                    const name = match[2];
                    
                    const product = inventory.find(p => p.name.toLowerCase() === name.toLowerCase());
                    
                    if (product) {
                        addToCart(product, qty);
                    } else {
                        missingItems.push(name);
                    }
                }
            });

            if (missingItems.length > 0) alert("Notice: These items from the table menu are not in the POS inventory:\n" + missingItems.join("\n"));

            // Mark order as 'preparing' so other cashiers know it's being handled
            fetch('live_orders.php?ajax=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, status: 'preparing' })
            });

            // Store ID so checkout knows to complete it
            activeQrOrderId = orderId; 

            closeRecallModal();
            if(window.triggerDynamicIsland) window.triggerDynamicIsland('Order Recalled', `${orderData.table} loaded into POS.`, 'success');
        }

        // ==========================================
        // ASYNC CHECKOUT (UPDATED TO CLOSE QR ORDERS)
        // ==========================================
        async function checkout() {
            if (Object.keys(cart).length === 0) {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Checkout Denied', 'Registry contains no items.', 'danger');
                return;
            }

            const checkoutBtn = document.getElementById('checkoutBtn');
            const originalBtnHTML = checkoutBtn.innerHTML;
            
            checkoutBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Processing...';
            checkoutBtn.style.pointerEvents = 'none';
            checkoutBtn.style.opacity = '0.7';

            if(window.triggerDynamicIsland) window.triggerDynamicIsland('Processing', 'Connecting to secure server...', 'info');

            try {
                const payload = { items: Object.values(cart) };
                const response = await fetch('../api/checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    // SILENTLY MARK QR ORDER AS COMPLETE IF LINKED
                    if (activeQrOrderId) {
                        await fetch('live_orders.php?ajax=update_status', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: activeQrOrderId, status: 'completed' })
                        });
                        activeQrOrderId = null;
                    }

                    const total = document.getElementById('totalAmount').innerText;
                    if(window.triggerDynamicIsland) window.triggerDynamicIsland('Charge Successful', `Filing cashier payment of ${total}.`, 'success');
                    
                    result.updated_stock.forEach(update => {
                        const prodIndex = inventory.findIndex(p => p.id == update.id);
                        if (prodIndex !== -1) inventory[prodIndex].stock = update.new_stock;
                    });

                    cart = {};
                    updateCartUI();
                    renderProducts(document.getElementById('productSearch').value);

                } else {
                    if(window.triggerDynamicIsland) window.triggerDynamicIsland('Transaction Failed', result.message || 'Unknown server error.', 'danger');
                }

            } catch (error) {
                console.error('Checkout error:', error);
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('System Error', 'Could not reach the database.', 'danger');
            } finally {
                checkoutBtn.innerHTML = originalBtnHTML;
                checkoutBtn.style.pointerEvents = 'auto';
                checkoutBtn.style.opacity = '1';
            }
        }

        renderProducts();
    </script>
<?php require '../includes/staff_footer.php'; ?>