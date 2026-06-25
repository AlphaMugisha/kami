<?php
// We no longer rely on the URL parameter. The user types it in.
$menu_categories = [
    'Single Malt' => [
        ['id' => 101, 'name' => 'Highland Reserve 18', 'meta' => '50ml Pour • 43% ABV', 'price' => 320.00, 'desc' => 'Notes of dark chocolate, dried fig, and toasted oak.'],
        ['id' => 102, 'name' => 'The Macallan 25', 'meta' => '50ml Pour • 43% ABV', 'price' => 1250.00, 'desc' => 'Rich sherry oak, dried citrus, and a whisper of wood smoke.']
    ],
    'Rare Bourbon' => [
        ['id' => 201, 'name' => 'Ozone Cask Strength', 'meta' => '50ml Pour • 55% ABV', 'price' => 185.00, 'desc' => 'Deep vanilla, caramel ribbon, and a powerful finish.'],
        ['id' => 202, 'name' => 'Kentucky Heritage', 'meta' => '50ml Pour • 47% ABV', 'price' => 95.00, 'desc' => 'Spiced honey, toasted almond, and baked apple.']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OZONE | Private Tasting Menu</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,400&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        :root {
            --color-ink: #0a0a0a;
            --color-paper: #F9F8F6;
            --color-stone: #4A4A4A;
            --color-gold: #B8860B;
            --border-fine: rgba(0, 0, 0, 0.1);
            --font-display: 'Cormorant Garamond', serif;
            --font-sans: 'Montserrat', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }
        body { background: var(--color-paper); color: var(--color-ink); font-family: var(--font-sans); padding-bottom: 120px; }

        .film-grain { position: fixed; inset: 0; background-image: url('data:image/svg+xml,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noiseFilter"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noiseFilter)"/%3E%3C/svg%3E'); opacity: 0.04; z-index: 9999; pointer-events: none; }

        header { position: sticky; top: 0; background: rgba(249, 248, 246, 0.95); backdrop-filter: blur(10px); padding: 24px; border-bottom: 1px solid var(--border-fine); z-index: 10; text-align: center; }
        .brand { font-family: var(--font-display); font-size: 24px; letter-spacing: 0.15em; }

        .menu-banner { height: 35vh; background: #000; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .menu-banner img { position: absolute; width: 100%; height: 100%; object-fit: cover; opacity: 0.5; }
        .menu-banner h1 { position: relative; color: #fff; font-family: var(--font-display); font-size: 42px; font-style: italic; z-index: 1; text-align: center; }

        .menu-container { max-width: 800px; margin: 0 auto; padding: 40px 24px; }
        .category-title { font-family: var(--font-display); font-size: 32px; margin: 60px 0 24px; border-bottom: 1px solid var(--border-fine); padding-bottom: 16px; }
        
        .menu-item { display: grid; grid-template-columns: 1fr auto; gap: 24px; padding: 32px 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .item-meta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.15em; color: var(--color-stone); margin-bottom: 8px; display: block; }
        .item-name { font-family: var(--font-display); font-size: 24px; margin-bottom: 8px; color: var(--color-ink); }
        .item-desc { font-size: 13px; color: var(--color-stone); line-height: 1.6; max-width: 90%; }
        
        .item-action { display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; }
        .item-price { font-family: var(--font-display); font-size: 20px; }
        .btn-add { background: transparent; border: 1px solid var(--color-ink); color: var(--color-ink); font-family: var(--font-sans); font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; padding: 8px 16px; cursor: pointer; transition: 0.3s; margin-top: 12px; }
        .btn-add:active { background: var(--color-ink); color: #fff; }

        .cart-trigger { position: fixed; bottom: 32px; left: 50%; transform: translateX(-50%); background: var(--color-ink); color: #fff; padding: 16px 32px; border-radius: 100px; display: flex; gap: 16px; align-items: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); cursor: pointer; z-index: 90; opacity: 0; pointer-events: none; transition: 0.4s ease; font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; }
        .cart-trigger.active { opacity: 1; pointer-events: auto; }
        .cart-badge { background: var(--color-gold); color: #fff; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600; font-size: 10px; }

        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 100; opacity: 0; pointer-events: none; transition: 0.4s ease; }
        .drawer-overlay.active { opacity: 1; pointer-events: auto; }
        
        .cart-drawer { position: fixed; bottom: 0; left: 0; width: 100%; height: 85vh; background: var(--color-paper); border-radius: 24px 24px 0 0; z-index: 101; transform: translateY(100%); transition: 0.5s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
        .cart-drawer.active { transform: translateY(0); }
        
        .drawer-header { padding: 32px 24px 24px; border-bottom: 1px solid var(--border-fine); display: flex; justify-content: space-between; align-items: center; }
        .drawer-header h2 { font-family: var(--font-display); font-size: 32px; }
        .btn-close { background: transparent; border: none; font-size: 24px; cursor: pointer; color: var(--color-stone); }

        .drawer-body { flex: 1; overflow-y: auto; padding: 24px; }
        .cart-item { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 14px; }
        .cart-item-name { font-weight: 500; margin-bottom: 4px; }

        .drawer-footer { padding: 32px 24px; background: #fff; border-top: 1px solid var(--border-fine); }
        
        /* New Table Input Style */
        .table-input-group { margin-bottom: 24px; }
        .table-input-group label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--color-stone); margin-bottom: 8px; display: block; }
        .table-input { width: 100%; padding: 16px; border: 1px solid var(--border-fine); background: transparent; font-family: var(--font-sans); font-size: 16px; color: var(--color-ink); outline: none; transition: 0.3s; }
        .table-input:focus { border-color: var(--color-ink); }

        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
        .pay-option { border: 1px solid var(--border-fine); padding: 16px; text-align: center; cursor: pointer; transition: 0.3s; }
        .pay-option.selected { border-color: var(--color-gold); background: rgba(184, 134, 11, 0.05); }
        .pay-option i { font-size: 24px; margin-bottom: 8px; display: block; }
        .pay-option span { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 500; }
        
        .btn-checkout { width: 100%; background: var(--color-ink); color: #fff; padding: 20px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 500; border: none; cursor: pointer; }
    </style>
</head>
<body>

    <div class="film-grain"></div>

    <header>
        <div class="brand">OZONE</div>
    </header>

    <div class="menu-banner">
        <img src="https://images.unsplash.com/photo-1595963503565-df0ea6df28b5?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Master Pour">
        <h1>The Curated Pours</h1>
    </div>

    <main class="menu-container">
        <?php foreach ($menu_categories as $category => $items): ?>
            <h2 class="category-title"><?= $category ?></h2>
            <?php foreach ($items as $item): ?>
                <div class="menu-item">
                    <div>
                        <span class="item-meta"><?= $item['meta'] ?></span>
                        <h3 class="item-name"><?= $item['name'] ?></h3>
                        <p class="item-desc"><?= $item['desc'] ?></p>
                    </div>
                    <div class="item-action">
                        <div class="item-price">$<?= number_format($item['price'], 2) ?></div>
                        <button class="btn-add" onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">Add</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </main>

    <div class="cart-trigger" id="cartTrigger" onclick="toggleDrawer()">
        <span>Review Order</span>
        <div class="cart-badge" id="cartBadge">0</div>
    </div>

    <div class="drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>

    <div class="cart-drawer" id="cartDrawer">
        <div class="drawer-header">
            <h2>Your Selection</h2>
            <button class="btn-close" onclick="toggleDrawer()"><i class="ph ph-x"></i></button>
        </div>
        
        <div class="drawer-body" id="cartBody">
            <p style="text-align: center; color: var(--color-stone); font-style: italic; margin-top: 40px;">Your portfolio is empty.</p>
        </div>

        <div class="drawer-footer">
            <div class="table-input-group">
                <label>Where are you seated?</label>
                <input type="text" id="clientTable" class="table-input" placeholder="e.g., Table 4, VIP Room, Patio">
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 24px; font-family: var(--font-display); font-size: 24px;">
                <span>Total</span>
                <span id="cartTotal">$0.00</span>
            </div>

            <div class="payment-grid">
                <div class="pay-option selected" id="pay-momo" onclick="selectPayment('momo')">
                    <i class="ph-light ph-device-mobile"></i>
                    <span>Mobile Money</span>
                </div>
                <div class="pay-option" id="pay-cash" onclick="selectPayment('cash')">
                    <i class="ph-light ph-money"></i>
                    <span>Card / Cash</span>
                </div>
            </div>

            <button class="btn-checkout" onclick="submitOrder()">Submit to Sommelier</button>
        </div>
    </div>

    <script>
        let cart = [];
        let paymentMethod = 'momo';

        function addToCart(id, name, price) {
            const existing = cart.find(item => item.id === id);
            if (existing) existing.quantity += 1;
            else cart.push({ id, name, price, quantity: 1 });
            
            updateCartUI();
            
            const trigger = document.getElementById('cartTrigger');
            trigger.style.transform = 'translateX(-50%) scale(1.05)';
            setTimeout(() => trigger.style.transform = 'translateX(-50%) scale(1)', 200);
        }

        function updateCartUI() {
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            document.getElementById('cartBadge').innerText = count;
            document.getElementById('cartTotal').innerText = '$' + total.toFixed(2);
            
            const trigger = document.getElementById('cartTrigger');
            if (count > 0) trigger.classList.add('active');
            else trigger.classList.remove('active');

            renderCartItems();
        }

        function renderCartItems() {
            const body = document.getElementById('cartBody');
            if (cart.length === 0) {
                body.innerHTML = '<p style="text-align: center; color: var(--color-stone); font-style: italic; margin-top: 40px;">Your portfolio is empty.</p>';
                return;
            }

            let html = '';
            cart.forEach(item => {
                html += `
                <div class="cart-item">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div style="color: var(--color-stone); font-size: 12px;">Quantity: ${item.quantity}</div>
                    </div>
                    <div style="font-family: var(--font-display); font-size: 18px;">$${(item.price * item.quantity).toFixed(2)}</div>
                </div>`;
            });
            body.innerHTML = html;
        }

        function toggleDrawer() {
            document.getElementById('cartDrawer').classList.toggle('active');
            document.getElementById('drawerOverlay').classList.toggle('active');
        }

        function selectPayment(method) {
            paymentMethod = method;
            document.getElementById('pay-momo').classList.remove('selected');
            document.getElementById('pay-cash').classList.remove('selected');
            document.getElementById('pay-' + method).classList.add('selected');
        }

        function submitOrder() {
            if (cart.length === 0) return alert('Please select a beverage first.');
            
            const tableValue = document.getElementById('clientTable').value.trim();
            if (tableValue === '') {
                alert('Please enter where you are seated so we can find you.');
                document.getElementById('clientTable').focus();
                return;
            }

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            const payload = {
                table: tableValue,
                total: total,
                method: paymentMethod,
                items: cart
            };

            const btn = document.querySelector('.btn-checkout');
            btn.innerText = 'Transmitting...';
            btn.style.opacity = '0.7';

            fetch('process_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Order received. Your sommelier is preparing your selection.');
                    cart = [];
                    document.getElementById('clientTable').value = '';
                    updateCartUI();
                    toggleDrawer();
                } else {
                    alert('An error occurred. Please catch the attention of a staff member.');
                }
                btn.innerText = 'Submit to Sommelier';
                btn.style.opacity = '1';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection error. Please try again.');
                btn.innerText = 'Submit to Sommelier';
                btn.style.opacity = '1';
            });
        }
    </script>
    <script src="../assets/js/public-fx.js" defer></script>
</body>
</html>