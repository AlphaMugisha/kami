<?php
// ENGINE ROUTER — authenticated staff skip the marketing site.
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
$nav_active = 'features';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | The Experience</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/boutique.css">
</head>
<body>

    <?php include 'includes/public_nav.php'; ?>

    <main>
        <!-- 1 · HERO -->
        <section class="page-hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1470337458703-46ad1756a187?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Cocktails arranged on a dark bar">
            </div>
            <div class="hero-content">
                <span class="eyebrow on-dark fade-up">What We Offer</span>
                <h1 class="fade-up" style="transition-delay: 0.1s;">The Experience</h1>
                <p class="fade-up" style="transition-delay: 0.2s;">More than a bar. A complete tasting house — from the moment you scan the menu to the last lingering sip.</p>
            </div>
        </section>

        <!-- 2 · POS & ORDERING -->
        <section class="editorial-split">
            <div class="editorial-text">
                <span class="eyebrow fade-up">Order Your Way</span>
                <h2 class="fade-up">Scan, sip, repeat — no waiting.</h2>
                <p class="fade-up">Settle into your seat and let the menu come to you. Every table at Ozone carries a QR code that opens our full collection on your phone — browse tasting notes, build your order, and send it straight to the bar without flagging anyone down.</p>
                <p class="fade-up">Pay how it suits you: tap to settle by Mobile Money (MoMo), card, or cash. Your round arrives while you stay exactly where you are — deep in conversation, never in a queue.</p>
                <ul class="feature-list fade-up">
                    <li><i class="ph-fill ph-qr-code"></i> Scan-to-order from any table</li>
                    <li><i class="ph-fill ph-device-mobile"></i> MoMo, card &amp; cash accepted</li>
                    <li><i class="ph-fill ph-lightning"></i> Orders reach the bar instantly</li>
                </ul>
                <a href="menu/index.php" class="btn btn-outline fade-up">Try The Menu</a>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1551024601-bec78aea704b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Guest ordering from a phone at a bar">
            </div>
        </section>

        <!-- 3 · CURATED SELECTION -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">The Collection</span>
                <h2>A cellar worth exploring</h2>
                <p>Six shelves of carefully chosen character — whether you know exactly what you want or you're here to be surprised.</p>
            </div>
            <div class="value-grid cols-3 container">
                <div class="value-card fade-up">
                    <div class="value-icon"><i class="ph ph-flask"></i></div>
                    <h3>Single Malts</h3>
                    <p>Rare expressions from the Highlands, Islay and beyond — aged in silence, poured with reverence.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.08s;">
                    <div class="value-icon"><i class="ph ph-barrel"></i></div>
                    <h3>Rare Bourbons</h3>
                    <p>Cask-strength Kentucky icons and small-batch finds, rich with vanilla, caramel and spice.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.16s;">
                    <div class="value-icon"><i class="ph ph-martini"></i></div>
                    <h3>Craft Cocktails</h3>
                    <p>Signature serves built by our mixologists — smoked, stirred and finished tableside.</p>
                </div>
                <div class="value-card fade-up">
                    <div class="value-icon"><i class="ph ph-wine"></i></div>
                    <h3>Fine Wines</h3>
                    <p>A tight, considered list of Old- and New-World bottles, chosen to pair and to please.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.08s;">
                    <div class="value-icon"><i class="ph ph-drop"></i></div>
                    <h3>Premium Gin &amp; Vodka</h3>
                    <p>Botanical-forward gins and glass-clean vodkas for the purists and the spritz lovers alike.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.16s;">
                    <div class="value-icon"><i class="ph ph-leaf"></i></div>
                    <h3>Zero-Proof</h3>
                    <p>Thoughtful non-alcoholic cocktails so every guest at the table has something worth toasting.</p>
                </div>
            </div>
        </section>

        <!-- 4 · PRIVATE EVENTS -->
        <section class="editorial-split reverse">
            <div class="editorial-text">
                <span class="eyebrow fade-up">Gather With Us</span>
                <h2 class="fade-up">Private tastings &amp; celebrations.</h2>
                <p class="fade-up">Reserve the room and make the evening entirely your own. From intimate guided tastings to corporate gatherings and milestone celebrations, our team designs each event around you — bespoke flights, dedicated hosts, and a setting that does the impressing for you.</p>
                <ul class="feature-list fade-up">
                    <li><i class="ph-fill ph-check-circle"></i> Guided whisky &amp; cocktail tastings</li>
                    <li><i class="ph-fill ph-check-circle"></i> Corporate evenings &amp; team socials</li>
                    <li><i class="ph-fill ph-check-circle"></i> Birthdays, engagements &amp; milestones</li>
                    <li><i class="ph-fill ph-check-circle"></i> Custom menus &amp; dedicated service</li>
                </ul>
                <a href="contact.php" class="btn btn-outline fade-up">Enquire About Events</a>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Friends toasting glasses at a private event">
            </div>
        </section>

        <!-- 5 · MEMBERSHIP / LOYALTY -->
        <section class="section">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">Coming Soon</span>
                <h2>The Reserve Club</h2>
                <p>Our membership programme for the regulars who've made Ozone their second home. Joining opens soon — here's a taste of what's ahead.</p>
            </div>
            <div class="value-grid container">
                <div class="value-card fade-up">
                    <div class="value-num">I</div>
                    <h3>Exclusive Tastings</h3>
                    <p>First access to members-only tasting nights, distiller visits, and the rarest pours before they reach the open menu.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.1s;">
                    <div class="value-num">II</div>
                    <h3>Member Rewards</h3>
                    <p>Earn on every visit and unlock standing discounts, complimentary pours, and a bottle reserved with your name on it.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.2s;">
                    <div class="value-num">III</div>
                    <h3>Priority Seating</h3>
                    <p>Skip the wait on busy nights with reserved seating and early booking windows for every event we host.</p>
                </div>
            </div>
        </section>

        <!-- 6 · TECHNOLOGY -->
        <section class="section ink">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow on-dark">Quietly Modern</span>
                <h2>Hospitality, beautifully engineered</h2>
                <p>The technology stays out of sight, so the experience stays effortless.</p>
            </div>
            <div class="value-grid container">
                <div class="value-card fade-up" style="background:rgba(255,255,255,0.03); border-color:rgba(255,255,255,0.12);">
                    <div class="value-icon"><i class="ph ph-qr-code"></i></div>
                    <h3 style="color:#fff;">Contactless Ordering</h3>
                    <p style="color:rgba(255,255,255,0.65);">Scan, browse and order from your seat. No apps to download, no queues to join.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay:0.1s; background:rgba(255,255,255,0.03); border-color:rgba(255,255,255,0.12);">
                    <div class="value-icon"><i class="ph ph-package"></i></div>
                    <h3 style="color:#fff;">Real-Time Inventory</h3>
                    <p style="color:rgba(255,255,255,0.65);">Our shelves and your screen stay in sync, so you only ever see what's truly in stock tonight.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay:0.2s; background:rgba(255,255,255,0.03); border-color:rgba(255,255,255,0.12);">
                    <div class="value-icon"><i class="ph ph-receipt"></i></div>
                    <h3 style="color:#fff;">Digital Receipts</h3>
                    <p style="color:rgba(255,255,255,0.65);">Settle up and get a tidy digital receipt — no paper, no fuss, easy to expense.</p>
                </div>
            </div>
        </section>

        <!-- 7 · TESTIMONIALS -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">In Good Company</span>
                <h2>What our guests say</h2>
            </div>
            <div class="testimonial-grid container">
                <div class="testimonial fade-up">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"The single malt selection is unreal for Kigali. Scanning the menu and ordering from my seat felt effortless — I never wanted to leave."</p>
                    <div class="author">
                        <div class="author-avatar">C</div>
                        <div>
                            <div class="author-name">Claudine N.</div>
                            <div class="author-meta">Regular since 2022</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial fade-up" style="transition-delay:0.1s;">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"We booked the room for a corporate evening and it was flawless. Custom cocktail flight, attentive hosts, zero stress on my end."</p>
                    <div class="author">
                        <div class="author-avatar">E</div>
                        <div>
                            <div class="author-name">Eric M.</div>
                            <div class="author-meta">Private event host</div>
                        </div>
                    </div>
                </div>
                <div class="testimonial fade-up" style="transition-delay:0.2s;">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"David's smoked old fashioned is the best cocktail I've had in the city. The whole place just feels considered, top to bottom."</p>
                    <div class="author">
                        <div class="author-avatar">S</div>
                        <div>
                            <div class="author-name">Sandrine K.</div>
                            <div class="author-meta">Cocktail enthusiast</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 8 · CTA -->
        <section class="section">
            <div class="cta-band fade-up">
                <span class="eyebrow">One Pour Away</span>
                <h2>Ready to experience Ozone?</h2>
                <p>Browse the collection from anywhere, or come find your seat at the bar. The first round is the start of something.</p>
                <a href="menu/index.php" class="btn btn-solid">View The Menu</a>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>
