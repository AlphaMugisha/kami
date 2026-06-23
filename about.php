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
    <title>OZONE | Our Heritage & Craft</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        /* LUXURY E-COMMERCE TOKENS (Matching Index) */
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
        p { font-size: 16px; line-height: 1.8; font-weight: 300; color: var(--text-secondary); }
        .eyebrow { font-family: var(--font-sans); font-size: 10px; text-transform: uppercase; letter-spacing: 0.3em; font-weight: 500; color: var(--text-secondary); margin-bottom: 20px; display: block; }

        /* HERO */
        .hero { height: 70vh; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #000; }
        .hero-bg { position: absolute; inset: 0; z-index: 0; }
        .hero-bg img { width: 100%; height: 100%; object-fit: cover; opacity: 0.5; transform: scale(1.05); animation: slowZoom 25s linear infinite alternate; }
        .hero-content { position: relative; z-index: 1; text-align: center; color: #fff; max-width: 800px; padding: 0 20px; margin-top: 60px; }
        .hero h1 { font-size: clamp(48px, 6vw, 80px); color: #fff; margin-bottom: 24px; font-style: italic; }
        
        /* EDITORIAL SPLIT */
        .editorial-split { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; padding: 120px 40px; max-width: 1400px; margin: 0 auto; }
        .editorial-text h2 { font-size: 48px; margin-bottom: 32px; color: var(--text-charcoal); }
        .image-reveal { position: relative; overflow: hidden; background: var(--bg-paper); padding: 20px; }
        .image-reveal img { width: 100%; height: auto; display: block; object-fit: cover; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.1)); }

        /* PRINCIPLES GRID (Replacing Bento) */
        .principles-section { background: var(--bg-paper); padding: 120px 0; border-top: 1px solid var(--border-fine); border-bottom: 1px solid var(--border-fine); }
        .principles-header { text-align: center; max-width: 600px; margin: 0 auto 80px; }
        .principles-header h2 { font-size: 48px; margin-bottom: 24px; }
        
        .principles-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 60px; max-width: 1400px; margin: 0 auto; padding: 0 40px; }
        .principle-card { text-align: center; }
        .principle-number { font-family: var(--font-serif); font-size: 64px; color: var(--accent-gold); opacity: 0.3; margin-bottom: 24px; font-style: italic; }
        .principle-card h3 { font-size: 28px; margin-bottom: 16px; }

        /* CTA */
        .cta-section { padding: 120px 40px; text-align: center; max-width: 800px; margin: 0 auto; }
        .cta-section h2 { font-size: 48px; margin-bottom: 24px; }
        .btn { display: inline-block; padding: 16px 40px; font-family: var(--font-sans); font-size: 11px; text-transform: uppercase; letter-spacing: 0.2em; font-weight: 500; text-decoration: none; transition: all 0.3s ease; cursor: pointer; margin-top: 32px; }
        .btn-solid { background: var(--text-charcoal); color: var(--bg-alabaster); border: 1px solid var(--text-charcoal); }
        .btn-solid:hover { background: transparent; color: var(--text-charcoal); }

        /* ANIMATIONS */
        @keyframes slowZoom { from { transform: scale(1); } to { transform: scale(1.1); } }
        .fade-up { opacity: 0; transform: translateY(30px); transition: all 1s cubic-bezier(0.16, 1, 0.3, 1); }
        .fade-up.visible { opacity: 1; transform: translateY(0); }

        @media (max-width: 900px) {
            .editorial-split, .principles-grid { grid-template-columns: 1fr; }
            .editorial-split { gap: 60px; }
        }
    </style>
</head>
<body>

    <?php include 'includes/public_nav.php'; ?>

    <main>
        <section class="hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1596956272304-adcb751f787e?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Distillery Barrels">
            </div>
            
            <div class="hero-content">
                <span class="eyebrow fade-up" style="color: #fff;">Our Heritage</span>
                <h1 class="fade-up" style="transition-delay: 0.2s;">A Century in the Making.</h1>
                <p class="fade-up" style="color: rgba(255,255,255,0.8); transition-delay: 0.4s;">Ozone was established with a singular obsession: to unearth and preserve the world's most extraordinary spirits. We do not rush perfection.</p>
            </div>
        </section>

        <section class="editorial-split">
            <div class="editorial-text">
                <span class="eyebrow fade-up">The Philosophy</span>
                <h2 class="fade-up">The uncompromising pursuit of rarity.</h2>
                <p class="fade-up">We believe that true character cannot be mass-produced. Our curators travel to the most secluded distilleries on earth, securing casks that have rested in absolute silence for decades.</p>
                <p class="fade-up" style="margin-top: 16px;">By obsessing over provenance—from the forest that birthed the oak barrel to the master blender who signed the seal—we ensure our clientele experiences only the pinnacle of liquid history.</p>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1595963503565-df0ea6df28b5?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Master Craftsman Pouring Whiskey">
            </div>
        </section>

        <section class="principles-section">
            <div class="principles-header fade-up">
                <span class="eyebrow">The Standards</span>
                <h2>The Principles We Pour By.</h2>
                <p>These tenets dictate every bottle we acquire and every experience we curate.</p>
            </div>
            
            <div class="principles-grid">
                <div class="principle-card fade-up">
                    <div class="principle-number">I</div>
                    <h3>The Luxury of Time</h3>
                    <p>Patience is our primary ingredient. We reject the expedited and embrace the decades-long quiet alchemy that occurs only within the cask.</p>
                </div>
                <div class="principle-card fade-up" style="transition-delay: 0.2s;">
                    <div class="principle-number">II</div>
                    <h3>Absolute Provenance</h3>
                    <p>Authenticity is non-negotiable. From the Scottish Highlands to rural Kentucky, every drop in our reserve is verified back to its source.</p>
                </div>
                <div class="principle-card fade-up" style="transition-delay: 0.4s;">
                    <div class="principle-number">III</div>
                    <h3>The Art of the Pour</h3>
                    <p>We believe the presentation is as important as the spirit itself. We craft environments and tasting experiences that honor the liquid within.</p>
                </div>
            </div>
        </section>
        
        <section class="cta-section fade-up">
            <span class="eyebrow">The Next Step</span>
            <h2>Experience the Reserve.</h2>
            <p>Ready to acquire a piece of history? Explore our curated boutique or arrange a private viewing with our sommeliers.</p>
            <a href="index.php#shop" class="btn btn-solid">Explore The Boutique</a>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
</body>
</html>