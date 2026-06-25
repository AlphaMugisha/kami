<?php
// 1. ENGINE ROUTER
declare(strict_types=1);
session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: cashier/index.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | Premium Liquor Boutique</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        /* LUXURY E-COMMERCE TOKENS */
        :root {
            --bg-alabaster: #FAF9F6;
            --bg-paper: #F2EFE9;
            --text-charcoal: #121212;
            --text-secondary: #5A5A5A;
            --accent-gold: #B8860B;
            --border-fine: rgba(18, 18, 18, 0.1);
            
            --font-serif: 'Cormorant Garamond', serif;
            --font-sans: 'Montserrat', sans-serif;
            
            --spacing-section: 160px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body { background-color: var(--bg-alabaster); color: var(--text-charcoal); font-family: var(--font-sans); overflow-x: hidden; }

        /* TYPOGRAPHY */
        h1, h2, h3, h4 { font-family: var(--font-serif); font-weight: 400; color: var(--text-charcoal); line-height: 1.1; }
        p { font-size: 15px; line-height: 1.8; font-weight: 300; }
        .eyebrow { font-family: var(--font-sans); font-size: 10px; text-transform: uppercase; letter-spacing: 0.3em; font-weight: 500; color: var(--text-secondary); margin-bottom: 20px; display: block; }

        /* STORE NAVIGATION */
        nav { position: fixed; top: 0; left: 0; width: 100%; padding: 30px 60px; display: flex; justify-content: space-between; align-items: center; z-index: 100; transition: all 0.4s ease; mix-blend-mode: difference; color: #fff; }
        nav.scrolled { padding: 20px 60px; background: rgba(250, 249, 246, 0.98); backdrop-filter: blur(10px); mix-blend-mode: normal; color: var(--text-charcoal); border-bottom: 1px solid var(--border-fine); }
        
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { text-decoration: none; color: inherit; font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 500; position: relative; }
        .nav-links a::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0%; height: 1px; background: currentColor; transition: width 0.3s ease; }
        .nav-links a:hover::after { width: 100%; }
        .brand-logo { font-family: var(--font-serif); font-size: 32px; font-weight: 500; letter-spacing: 0.1em; text-decoration: none; color: inherit; }
        
        .cart-icon { display: flex; align-items: center; gap: 8px; }
        .cart-count { background: var(--accent-gold); color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 100px; mix-blend-mode: normal; }

        /* HERO */
        .hero { height: 90vh; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; z-index: -1; background: #000; }
        .hero-bg img { width: 100%; height: 100%; object-fit: cover; opacity: 0.7; transform: scale(1.05); animation: slowZoom 20s linear infinite alternate; }
        .hero-content { position: relative; z-index: 1; text-align: center; color: #fff; max-width: 800px; padding: 0 20px; }
        .hero h1 { font-size: clamp(50px, 8vw, 100px); color: #fff; margin-bottom: 24px; font-style: italic; }
        
        /* BUTTONS */
        .btn { display: inline-block; padding: 16px 40px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 500; text-decoration: none; transition: all 0.3s ease; cursor: pointer; }
        .btn-solid { background: var(--text-charcoal); color: var(--bg-alabaster); border: 1px solid var(--text-charcoal); }
        .btn-solid:hover { background: transparent; color: var(--text-charcoal); }
        .btn-light { background: #fff; color: #000; border: 1px solid #fff; }
        .btn-light:hover { background: transparent; color: #fff; }

        /* STORE CATALOG SECTION */
        .collection-section { background: var(--bg-alabaster); padding: 120px 0; }
        .collection-header { text-align: center; margin-bottom: 60px; }
        .collection-header h2 { font-size: 56px; margin-bottom: 32px; }
        
        /* Category Filters */
        .shop-filters { display: flex; justify-content: center; gap: 40px; list-style: none; margin-bottom: 60px; border-bottom: 1px solid var(--border-fine); padding-bottom: 20px; }
        .shop-filters li { font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; color: var(--text-secondary); cursor: pointer; transition: color 0.3s; position: relative; }
        .shop-filters li.active { color: var(--text-charcoal); font-weight: 600; }
        .shop-filters li.active::after { content: ''; position: absolute; bottom: -21px; left: 0; width: 100%; height: 1px; background: var(--text-charcoal); }
        .shop-filters li:hover { color: var(--text-charcoal); }

        /* E-COMMERCE GRID */
        .collection-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; max-width: 1400px; margin: 0 auto; padding: 0 40px; }
        .product-card { text-align: center; group; position: relative; }
        
        .product-image { height: 480px; margin-bottom: 24px; padding: 40px; background: var(--bg-paper); display: flex; align-items: center; justify-content: center; transition: all 0.4s ease; position: relative; overflow: hidden; }
        .product-image img { max-height: 100%; width: auto; object-fit: contain; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.1)); transition: transform 0.6s ease; }
        
        /* Add to Bag Hover Action */
        .add-to-bag { position: absolute; bottom: 0; left: 0; width: 100%; background: var(--text-charcoal); color: #fff; padding: 16px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 500; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer; border: none; }
        .product-card:hover .product-image { background: #fff; box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .product-card:hover .product-image img { transform: translateY(-10px) scale(1.03); }
        .product-card:hover .add-to-bag { transform: translateY(0); }
        .add-to-bag:hover { background: var(--accent-gold); }

        /* Product Details */
        .product-meta { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .product-info h3 { font-size: 24px; margin-bottom: 12px; }
        .product-price { font-family: var(--font-serif); font-size: 22px; color: var(--text-charcoal); margin-bottom: 12px; }

        /* SPLIT EDITORIAL */
        .editorial-split { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; padding: 120px 40px; max-width: 1400px; margin: 0 auto; }
        .editorial-text h2 { font-size: 48px; margin-bottom: 32px; }
        .image-reveal img { width: 100%; height: auto; display: block; object-fit: cover; }

        /* FOOTER */
        footer { padding: 80px 60px 40px; border-top: 1px solid var(--border-fine); background: #121212; color: #fff; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 60px; max-width: 1400px; margin: 0 auto 80px; }
        .footer-brand h2 { font-size: 32px; margin-bottom: 20px; color: #fff; }
        .footer-links h4 { font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; margin-bottom: 24px; color: #fff; }
        .footer-links ul { list-style: none; }
        .footer-links li { margin-bottom: 12px; }
        .footer-links a { color: #888; text-decoration: none; font-size: 12px; transition: color 0.3s; }
        .footer-links a:hover { color: #fff; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 32px; display: flex; justify-content: space-between; font-size: 11px; color: #666; max-width: 1400px; margin: 0 auto; text-transform: uppercase; letter-spacing: 0.1em; }

        @keyframes slowZoom { from { transform: scale(1); } to { transform: scale(1.1); } }
        .fade-up { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .fade-up.visible { opacity: 1; transform: translateY(0); }

        @media (max-width: 900px) {
            .collection-grid, .editorial-split, .footer-grid { grid-template-columns: 1fr; }
            .shop-filters { display: none; } /* Hide filters on mobile for simplicity */
            nav { padding: 20px; }
        }
    </style>
</head>
<body>

    <nav id="navbar">
        <div class="nav-links">
            <a href="#shop">Shop</a>
            <a href="#heritage">Heritage</a>
        </div>
        <a href="index.php" class="brand-logo">OZONE</a>
        <div class="nav-links">
            <a href="login.php">Staff Login</a>
            <a href="cart.php" class="cart-icon">
                <i class="ph ph-shopping-bag" style="font-size: 18px;"></i>
                <span class="cart-count">0</span>
            </a>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Liquor Pour">
            </div>
            <div class="hero-content">
                <h1 class="fade-up">Mozambique Nature Reserve</h1>
                <p class="fade-up" style="transition-delay: 0.2s;">Curating the world's most distinguished spirits. Available for immediate acquisition or private tasting.</p>
                <div class="fade-up" style="transition-delay: 0.4s;">
                    <a href="./menu/index.php" class="btn btn-light">Take your order today</a>
                </div>
            </div>
        </section>

        <section id="shop" class="collection-section">
            <div class="collection-header fade-up">
                <h2>The Boutique</h2>
                <ul class="shop-filters">
                    <li class="active">All Spirits</li>
                    <li>Single Malt</li>
                    <li>Bourbon</li>
                    <li>Cognac</li>
                    <li>Rare Vintages</li>
                </ul>
            </div>
            
            <div class="collection-grid">
                <div class="product-card fade-up">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1527281400683-1aae777175f8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Aged Single Malt">
                        <button class="add-to-bag">Acquire Bottle</button>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">750ML &nbsp;|&nbsp; 43% ABV</div>
                        <h3>Highland Reserve 18</h3>
                        <div class="product-price">$320.00</div>
                    </div>
                </div>

                <div class="product-card fade-up" style="transition-delay: 0.1s;">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1582226224220-410a56f26df8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Rare Bourbon">
                        <button class="add-to-bag">Acquire Bottle</button>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">750ML &nbsp;|&nbsp; 55% ABV</div>
                        <h3>Ozone Cask Strength</h3>
                        <div class="product-price">$185.00</div>
                    </div>
                </div>

                <div class="product-card fade-up" style="transition-delay: 0.2s;">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1597075687490-8f673c6c17f6?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Japanese Whisky">
                        <button class="add-to-bag">Acquire Bottle</button>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">700ML &nbsp;|&nbsp; 46% ABV</div>
                        <h3>Kyoto Harmony</h3>
                        <div class="product-price">$450.00</div>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 60px;" class="fade-up">
                <a href="#" class="btn btn-outline">View Full Catalog</a>
            </div>
        </section>

        <section id="heritage" class="editorial-split">
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1569529465841-dfecdab7503b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Oak Whiskey Barrels">
            </div>
            <div class="editorial-text">
                <span class="eyebrow fade-up">Our Sourcing</span>
                <h2 class="fade-up">Uncompromising Craft.</h2>
                <p class="fade-up">We do not merely stock spirits; we curate historical moments captured in glass. Sourced directly from the world's most secretive distilleries, every bottle in the Ozone Reserve is verified for authenticity and provenance.</p>
                <a href="about.php" class="btn btn-solid fade-up" style="margin-top: 24px;">Read Our Story</a>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-grid">
            <div>
                <h2 style="font-family: var(--font-serif); font-size: 24px; margin-bottom: 16px;">OZONE LIQUOR</h2>
                <p style="color: #888; font-size: 13px; max-width: 300px; line-height: 1.6;">Purveyors of distinguished spirits. Online retail and private boutique located in Kigali.</p>
            </div>
            <div class="footer-links">
                <h4>Shop</h4>
                <ul>
                    <li><a href="#shop">All Spirits</a></li>
                    <li><a href="#">Rare & Vintage</a></li>
                    <li><a href="#">Gift Cards</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Shipping & Returns</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="login.php">Staff Terminal</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> Ozone Liquor. All rights reserved.</div>
            <div>Please drink responsibly.</div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navbar = document.getElementById('navbar');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            });

            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
        });
    </script>
    <script src="assets/js/public-fx.js" defer></script>
</body>
</html> 