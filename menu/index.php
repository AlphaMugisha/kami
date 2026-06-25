<?php
// Public-facing browse + order menu. The cart submits to process_order.php
// (untouched). Categories below drive both the browsable grid and the pill filter.
$menu_categories = [
    'Single Malt' => [
        ['id' => 101, 'name' => 'Highland Reserve 18', 'meta' => '50ml Pour', 'abv' => '43% ABV', 'price' => 320.00, 'desc' => 'Dark chocolate, dried fig and toasted oak, with a long, warming finish.'],
        ['id' => 102, 'name' => 'The Macallan 25', 'meta' => '50ml Pour', 'abv' => '43% ABV', 'price' => 1250.00, 'desc' => 'Rich sherry oak, dried citrus and a whisper of wood smoke. A rare indulgence.'],
        ['id' => 103, 'name' => 'Islay Smoke No.7', 'meta' => '50ml Pour', 'abv' => '46% ABV', 'price' => 240.00, 'desc' => 'Bold maritime peat, sea salt and crackling campfire embers.'],
    ],
    'Rare Bourbon' => [
        ['id' => 201, 'name' => 'Ozone Cask Strength', 'meta' => '50ml Pour', 'abv' => '55% ABV', 'price' => 185.00, 'desc' => 'Deep vanilla, a caramel ribbon and a powerful, lingering finish.'],
        ['id' => 202, 'name' => 'Kentucky Heritage', 'meta' => '50ml Pour', 'abv' => '47% ABV', 'price' => 95.00, 'desc' => 'Spiced honey, toasted almond and baked apple. An easy, generous sipper.'],
    ],
    'Vodka' => [
        ['id' => 301, 'name' => 'Glacier Crystal', 'meta' => '50ml Pour', 'abv' => '40% ABV', 'price' => 70.00, 'desc' => 'Whisper-clean and silky, distilled six times for a flawless finish.'],
        ['id' => 302, 'name' => 'Black Wheat Reserve', 'meta' => '50ml Pour', 'abv' => '42% ABV', 'price' => 110.00, 'desc' => 'Subtle white pepper and cream over a soft, rounded body.'],
    ],
    'Gin' => [
        ['id' => 401, 'name' => 'Botanist Wild', 'meta' => '50ml Pour', 'abv' => '46% ABV', 'price' => 85.00, 'desc' => 'Twenty-two foraged botanicals — juniper, citrus peel and meadow herbs.'],
        ['id' => 402, 'name' => 'Kigali Hibiscus Gin', 'meta' => '50ml Pour', 'abv' => '43% ABV', 'price' => 90.00, 'desc' => 'A local favourite: bright hibiscus, rosella and a touch of African pepper.'],
    ],
    'Wine' => [
        ['id' => 501, 'name' => 'Stellenbosch Cabernet', 'meta' => '175ml Glass', 'abv' => '14% ABV', 'price' => 60.00, 'desc' => 'Blackcurrant, cedar and soft tannin from South Africa\'s finest slopes.'],
        ['id' => 502, 'name' => 'Loire Sauvignon Blanc', 'meta' => '175ml Glass', 'abv' => '12.5% ABV', 'price' => 55.00, 'desc' => 'Crisp gooseberry and citrus with a clean, mineral edge.'],
    ],
    'Cocktails' => [
        ['id' => 601, 'name' => 'Smoked Old Fashioned', 'meta' => 'Signature Serve', 'abv' => 'Bourbon', 'price' => 75.00, 'desc' => 'Cask bourbon, demerara and bitters, finished under an oak-smoke cloche.'],
        ['id' => 602, 'name' => 'Kivu Sour', 'meta' => 'House Original', 'abv' => 'Gin', 'price' => 68.00, 'desc' => 'Hibiscus gin, fresh lime and silky egg white. Tart, bright, unforgettable.'],
    ],
    'Non-Alcoholic' => [
        ['id' => 701, 'name' => 'Garden Spritz (0%)', 'meta' => 'Zero-Proof', 'abv' => 'Alcohol-Free', 'price' => 40.00, 'desc' => 'Cucumber, elderflower and tonic over crushed ice. Crisp and reviving.'],
        ['id' => 702, 'name' => 'Spiced Tamarind Fizz', 'meta' => 'Zero-Proof', 'abv' => 'Alcohol-Free', 'price' => 42.00, 'desc' => 'Tangy tamarind, ginger and soda with a gentle warming spice.'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | The Collection</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/boutique.css">
    <style>
        /* Page-local extras layered on top of the shared boutique system. */
        body { padding-bottom: 120px; }
        .film-grain { position: fixed; inset: 0; background-image: url('data:image/svg+xml,%3Csvg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"%3E%3Cfilter id="noiseFilter"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="100%25" height="100%25" filter="url(%23noiseFilter)"/%3E%3C/svg%3E'); opacity: 0.04; z-index: 9999; pointer-events: none; }

        /* Floating order bar + drawer (boutique-styled) */
        .cart-trigger { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: var(--text-charcoal); color: #fff; padding: 16px 32px; border-radius: 100px; display: flex; gap: 16px; align-items: center; box-shadow: 0 20px 40px rgba(0,0,0,0.25); cursor: pointer; z-index: 95; opacity: 0; pointer-events: none; transition: 0.4s ease; font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; }
        .cart-trigger.active { opacity: 1; pointer-events: auto; }
        .cart-badge { background: var(--accent-gold); color: #fff; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 600; font-size: 10px; }

        .drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 100; opacity: 0; pointer-events: none; transition: 0.4s ease; }
        .drawer-overlay.active { opacity: 1; pointer-events: auto; }
        .cart-drawer { position: fixed; bottom: 0; left: 0; width: 100%; max-width: 520px; right: 0; margin: 0 auto; height: 85vh; background: var(--bg-alabaster); border-radius: 24px 24px 0 0; z-index: 101; transform: translateY(100%); transition: 0.5s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
        .cart-drawer.active { transform: translateY(0); }
        .drawer-header { padding: 32px 24px 24px; border-bottom: 1px solid var(--border-fine); display: flex; justify-content: space-between; align-items: center; }
        .drawer-header h2 { font-family: var(--font-serif); font-size: 32px; }
        .btn-close { background: transparent; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary); }
        .drawer-body { flex: 1; overflow-y: auto; padding: 24px; }
        .cart-item { display: flex; justify-content: space-between; margin-bottom: 24px; font-size: 14px; }
        .cart-item-name { font-weight: 500; margin-bottom: 4px; color: var(--text-charcoal); }
        .drawer-footer { padding: 28px 24px; background: #fff; border-top: 1px solid var(--border-fine); }
        .table-input-group { margin-bottom: 22px; }
        .table-input-group label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-secondary); margin-bottom: 8px; display: block; }
        .table-input { width: 100%; padding: 15px 16px; border: 1px solid var(--border-fine); background: transparent; font-family: var(--font-sans); font-size: 16px; color: var(--text-charcoal); outline: none; transition: 0.3s; }
        .table-input:focus { border-color: var(--accent-gold); }
        .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; }
        .pay-option { border: 1px solid var(--border-fine); padding: 16px; text-align: center; cursor: pointer; transition: 0.3s; }
        .pay-option.selected { border-color: var(--accent-gold); background: var(--accent-gold-soft); }
        .pay-option i { font-size: 24px; margin-bottom: 8px; display: block; }
        .pay-option span { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 500; }
        .btn-checkout { width: 100%; background: var(--text-charcoal); color: #fff; padding: 18px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 500; border: none; cursor: pointer; transition: background 0.3s; }
        .btn-checkout:hover { background: var(--accent-gold); }

        .cat-block { scroll-margin-top: 120px; }
        .cat-block.is-hidden { display: none; }
        .cat-title { text-align: center; font-size: clamp(30px, 4vw, 44px); margin-bottom: 8px; }
        .cat-sub { text-align: center; font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; color: var(--accent-gold); margin-bottom: 48px; }
    </style>
</head>
<body>

    <div class="film-grain"></div>

    <?php $base = '../'; $nav_active = 'menu'; include '../includes/public_nav.php'; ?>

    <main>
        <!-- 1 · HERO -->
        <section class="page-hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1595963503565-df0ea6df28b5?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Master pour into a tasting glass">
            </div>
            <div class="hero-content">
                <span class="eyebrow on-dark fade-up">Curated &amp; Poured in Kigali</span>
                <h1 class="fade-up" style="transition-delay:0.1s;">The Collection</h1>
                <p class="fade-up" style="transition-delay:0.2s;">Browse the full menu, build your order from your seat, and let the bar bring it to you. Scan, sip, repeat.</p>
            </div>
        </section>

        <!-- 2 · CATEGORY NAV + 3 · MENU GRID -->
        <section class="section">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">Browse</span>
                <h2>Find your pour</h2>
            </div>

            <div class="menu-pills fade-up" id="menuPills">
                <button class="menu-pill active" data-filter="all">All</button>
                <?php foreach (array_keys($menu_categories) as $category): ?>
                    <button class="menu-pill" data-filter="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($menu_categories as $category => $items): ?>
                <div class="cat-block" data-category="<?= htmlspecialchars($category) ?>" id="cat-<?= md5($category) ?>">
                    <h2 class="cat-title fade-up"><?= htmlspecialchars($category) ?></h2>
                    <div class="cat-sub fade-up">Selected Pours</div>
                    <div class="product-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="bottle-card fade-up">
                                <div class="bottle-top">
                                    <span class="bottle-badge"><?= htmlspecialchars($category) ?></span>
                                    <span class="bottle-abv"><?= htmlspecialchars($item['abv']) ?></span>
                                </div>
                                <h3><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="bottle-notes"><?= htmlspecialchars($item['desc']) ?></p>
                                <div class="bottle-foot">
                                    <span class="bottle-price">$<?= number_format($item['price'], 2) ?></span>
                                    <button class="bottle-add"
                                            onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>)">
                                        Add to Order
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- 4 · COCKTAIL SPECIALS -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">From The Mixologist</span>
                <h2>Signature cocktails</h2>
                <p>Built by hand, finished tableside — the serves our regulars come back for.</p>
            </div>
            <div class="special-grid container">
                <div class="special-card fade-up">
                    <div class="special-num">01</div>
                    <h3>Smoked Old Fashioned</h3>
                    <p>Our most-ordered serve. Cask bourbon and demerara under a cloud of oak smoke.</p>
                    <div class="special-ingredients">Bourbon · Demerara · Oak Smoke · Bitters</div>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.1s;">
                    <div class="special-num">02</div>
                    <h3>Kivu Sour</h3>
                    <p>A proudly local original — hibiscus gin, fresh lime and silky egg white.</p>
                    <div class="special-ingredients">Hibiscus Gin · Lime · Egg White</div>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.2s;">
                    <div class="special-num">03</div>
                    <h3>Espresso Martini Noir</h3>
                    <p>Cold-brew, vanilla vodka and a velvet crema. The night's second wind.</p>
                    <div class="special-ingredients">Vodka · Cold Brew · Vanilla</div>
                </div>
            </div>
        </section>

        <!-- 5 · WINE SELECTION -->
        <section class="editorial-split">
            <div class="editorial-text">
                <span class="eyebrow fade-up">The Cellar</span>
                <h2 class="fade-up">A wine list with intent.</h2>
                <p class="fade-up">We keep our wine list short on purpose. Rather than a dozen forgettable bottles, we pour a tight, rotating selection of Old- and New-World wines — each chosen to stand on its own or sit beside our kitchen's small plates.</p>
                <p class="fade-up">Ask our sommelier what's open tonight; there's almost always a by-the-glass surprise worth trying.</p>
                <a href="#cat-<?= md5('Wine') ?>" class="btn btn-outline fade-up">See Wines By The Glass</a>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Glasses of red wine on a bar">
            </div>
        </section>

        <!-- 6 · PAIRING SUGGESTIONS -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">Our Bartender Recommends</span>
                <h2>Perfect pairings</h2>
                <p>A few combinations we'd happily stake our reputation on.</p>
            </div>
            <div class="special-grid container">
                <div class="special-card fade-up">
                    <h3>Highland Reserve 18 &amp; Dark Chocolate</h3>
                    <p>The dried-fig sweetness of the malt meets 70% cocoa — bitterness and warmth in perfect balance.</p>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.1s;">
                    <h3>Kivu Sour &amp; Grilled Brochettes</h3>
                    <p>Bright hibiscus and lime cut clean through smoky, spiced street-style brochettes.</p>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.2s;">
                    <h3>Islay Smoke No.7 &amp; Aged Gouda</h3>
                    <p>Maritime peat and nutty, crystalline cheese — a bold, savoury way to end the night.</p>
                </div>
            </div>
        </section>

        <!-- 7 · CTA -->
        <section class="section">
            <div class="cta-band fade-up">
                <span class="eyebrow">Come Find Your Seat</span>
                <h2>Visit us to explore the full collection</h2>
                <p>What you see here is just the start. Our shelves hold over 500 spirits — come let our team guide you to your next favourite.</p>
                <a href="../contact.php" class="btn btn-solid">Plan Your Visit</a>
            </div>
        </section>
    </main>

    <!-- ORDER BAR + DRAWER (wired to process_order.php) -->
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
            <p style="text-align: center; color: var(--text-secondary); font-style: italic; margin-top: 40px;">Your selection is empty.</p>
        </div>

        <div class="drawer-footer">
            <div class="table-input-group">
                <label>Where are you seated?</label>
                <input type="text" id="clientTable" class="table-input" placeholder="e.g., Table 4, VIP Room, Patio">
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 24px; font-family: var(--font-serif); font-size: 24px;">
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
        /* ---- Category pill filter ---- */
        (function () {
            var pills = document.querySelectorAll('.menu-pill');
            var blocks = document.querySelectorAll('.cat-block');
            pills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    pills.forEach(function (p) { p.classList.remove('active'); });
                    pill.classList.add('active');
                    var f = pill.getAttribute('data-filter');
                    blocks.forEach(function (b) {
                        var show = (f === 'all' || b.getAttribute('data-category') === f);
                        b.classList.toggle('is-hidden', !show);
                    });
                });
            });
        })();

        /* ---- Order cart (submits to process_order.php — unchanged) ---- */
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
                body.innerHTML = '<p style="text-align: center; color: var(--text-secondary); font-style: italic; margin-top: 40px;">Your selection is empty.</p>';
                return;
            }

            let html = '';
            cart.forEach(item => {
                html += `
                <div class="cart-item">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div style="color: var(--text-secondary); font-size: 12px;">Quantity: ${item.quantity}</div>
                    </div>
                    <div style="font-family: var(--font-serif); font-size: 18px;">$${(item.price * item.quantity).toFixed(2)}</div>
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
