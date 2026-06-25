<?php
// Capture the table number from the QR code URL (e.g., menu.php?table=4)
$table_number = isset($_GET['table']) ? htmlspecialchars($_GET['table']) : 'Lounge';

// Mocking the database fetch for the menu to get you started instantly
$menu_items = [
    ['id' => 1, 'name' => 'Highland Reserve 18', 'category' => 'Single Malt', 'price' => 320.00, 'desc' => 'Notes of dark chocolate, dried fig, and toasted oak. Pour: 50ml.'],
    ['id' => 2, 'name' => 'Ozone Cask Strength', 'category' => 'Bourbon', 'price' => 185.00, 'desc' => 'Deep vanilla, caramel ribbon, and a smoky finish. Pour: 50ml.'],
    ['id' => 3, 'name' => 'Kyoto Harmony Blend', 'category' => 'Japanese', 'price' => 450.00, 'desc' => 'Delicate floral, honeyed pear, and subtle sandalwood. Pour: 50ml.'],
    ['id' => 4, 'name' => 'Artisan Ice Sphere', 'category' => 'Accompaniment', 'price' => 15.00, 'desc' => 'Hand-carved crystal clear ice sphere for slow dilution.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OZONE | Private Tasting Menu</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --bg-alabaster: #FAF9F6;
            --text-charcoal: #121212;
            --text-secondary: #5A5A5A;
            --accent-gold: #B8860B;
            --border-fine: rgba(18, 18, 18, 0.1);
            --font-serif: 'Cormorant Garamond', serif;
            --font-sans: 'Montserrat', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        body { background: var(--bg-alabaster); color: var(--text-charcoal); font-family: var(--font-sans); padding-bottom: 100px; }

        /* Header */
        header { text-align: center; padding: 40px 20px; border-bottom: 1px solid var(--border-fine); background: #fff; position: sticky; top: 0; z-index: 10; }
        .brand { font-family: var(--font-serif); font-size: 28px; letter-spacing: 0.15em; margin-bottom: 8px; }
        .table-indicator { font-size: 10px; text-transform: uppercase; letter-spacing: 0.2em; color: var(--text-secondary); }

        /* Menu List */
        .menu-container { max-width: 600px; margin: 0 auto; padding: 40px 24px; }
        .menu-item { border-bottom: 1px solid var(--border-fine); padding: 24px 0; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .item-details { flex: 1; }
        .item-category { font-size: 9px; text-transform: uppercase; letter-spacing: 0.2em; color: var(--text-secondary); margin-bottom: 4px; display: block; }
        .item-name { font-family: var(--font-serif); font-size: 22px; margin-bottom: 8px; }
        .item-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; font-style: italic; font-family: var(--font-serif); }
        .item-price { font-size: 16px; font-weight: 500; margin-top: 12px; }

        /* Add Button */
        .btn-add { background: transparent; border: 1px solid var(--text-charcoal); color: var(--text-charcoal); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; }
        .btn-add:hover, .btn-add:active { background: var(--text-charcoal); color: #fff; }

        /* Floating Cart Panel */
        .cart-panel { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--text-charcoal); color: #fff; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); z-index: 100; box-shadow: 0 -10px 40px rgba(0,0,0,0.2); }
        .cart-panel.active { transform: translateY(0); }
        .cart-total { font-family: var(--font-serif); font-size: 24px; }
        .cart-items-count { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #888; }
        .btn-checkout { background: #fff; color: #000; border: none; padding: 14px 32px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 600; cursor: pointer; }

        /* Checkout Modal */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; display: flex; align-items: flex-end; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal.active { opacity: 1; pointer-events: auto; }
        .modal-content { background: var(--bg-alabaster); width: 100%; border-radius: 24px 24px 0 0; padding: 40px 24px; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal.active .modal-content { transform: translateY(0); }
        
        .modal-header { font-family: var(--font-serif); font-size: 28px; margin-bottom: 24px; text-align: center; }
        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
        .pay-btn { padding: 20px; border: 1px solid var(--border-fine); background: transparent; text-align: center; cursor: pointer; transition: 0.3s; }
        .pay-btn.selected { border-color: var(--accent-gold); background: rgba(184, 134, 11, 0.05); }
        .pay-btn i { font-size: 24px; margin-bottom: 8px; color: var(--text-charcoal); }
        .pay-btn span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
        
        .btn-confirm { width: 100%; background: var(--text-charcoal); color: #fff; border: none; padding: 18px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

    <header>
        <div class="brand">OZONE</div>
        <div class="table-indicator">Seated at: Table <?= $table_number ?></div>
    </header>

    <main class="menu-container">
        <?php foreach ($menu_items as $item): ?>
            <div class="menu-item">
                <div class="item-details">
                    <span class="item-category"><?= $item['category'] ?></span>
                    <h3 class="item-name"><?= $item['name'] ?></h3>
                    <p class="item-desc"><?= $item['desc'] ?></p>
                    <div class="item-price">$<?= number_format($item['price'], 2) ?></div>
                </div>
                <button class="btn-add" onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
                    <i class="ph-bold ph-plus"></i>
                </button>
            </div>
        <?php endforeach; ?>
    </main>

    <div class="cart-panel" id="cartPanel">
        <div>
            <div class="cart-items-count" id="cartCount">0 Items</div>
            <div class="cart-total" id="cartTotal">$0.00</div>
        </div>
        <button class="btn-checkout" onclick="openCheckout()">Review & Order</button>
    </div>

    <div class="modal" id="checkoutModal">
        <div class="modal-content">
            <h2 class="modal-header">Complete Order</h2>
            <p style="text-align: center; font-size: 13px; color: var(--text-secondary); margin-bottom: 32px;">Your sommelier will bring the order to Table <?= $table_number ?>.</p>
            
            <span class="item-category" style="margin-bottom: 12px; display: block;">Select Payment Method</span>
            <div class="payment-options">
                <div class="pay-btn selected" onclick="selectPayment('momo')" id="btn-momo">
                    <i class="ph-fill ph-device-mobile"></i>
                    <span>Mobile Money</span>
                </div>
                <div class="pay-btn" onclick="selectPayment('cash')" id="btn-cash">
                    <i class="ph-fill ph-money"></i>
                    <span>Cash / Card at Table</span>
                </div>
            </div>

            <button class="btn-confirm" onclick="submitOrder()">Confirm Order &mdash; <span id="modalTotal">$0.00</span></button>
            <button onclick="closeCheckout()" style="width: 100%; background: transparent; border: none; padding: 16px; margin-top: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-secondary); cursor: pointer;">Cancel</button>
        </div>
    </div>

    <script>
        // Vanilla JS Cart Engine
        let cart = [];
        let paymentMethod = 'momo';
        const tableNumber = '<?= $table_number ?>';

        function addToCart(id, name, price) {
            const existingItem = cart.find(item => item.id === id);
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({ id, name, price, quantity: 1, preference: '' });
            }
            updateCartUI();
        }

        function updateCartUI() {
            const cartPanel = document.getElementById('cartPanel');
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);

            document.getElementById('cartTotal').innerText = '$' + total.toFixed(2);
            document.getElementById('modalTotal').innerText = '$' + total.toFixed(2);
            document.getElementById('cartCount').innerText = count + (count === 1 ? ' Item' : ' Items');

            if (count > 0) {
                cartPanel.classList.add('active');
            } else {
                cartPanel.classList.remove('active');
            }
        }

        function openCheckout() {
            document.getElementById('checkoutModal').classList.add('active');
        }

        function closeCheckout() {
            document.getElementById('checkoutModal').classList.remove('active');
        }

        function selectPayment(method) {
            paymentMethod = method;
            document.getElementById('btn-momo').classList.remove('selected');
            document.getElementById('btn-cash').classList.remove('selected');
            document.getElementById('btn-' + method).classList.add('selected');
        }

        function submitOrder() {
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            const payload = {
                table: tableNumber,
                total: total,
                method: paymentMethod,
                items: cart
            };

            // We will build process_order.php in the next step!
            fetch('process_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Order received. A sommelier will be with you shortly.');
                    cart = [];
                    updateCartUI();
                    closeCheckout();
                } else {
                    alert('Error processing order. Please notify a staff member.');
                }
            });
        }
    </script>
    <script src="../assets/js/public-fx.js" defer></script>
</body>
</html>