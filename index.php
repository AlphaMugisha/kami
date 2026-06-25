<?php
// ENGINE ROUTER — authenticated staff are sent to their terminal.
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
$nav_active = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | Premium Liquor Boutique · Kigali</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/boutique.css">
</head>
<body>

    <?php include 'includes/public_nav.php'; ?>

    <main>
        <!-- HERO -->
        <section class="hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="A perfect pour of whisky">
            </div>
            <div class="hero-content">
                <span class="eyebrow on-dark fade-up">A Boutique Tasting House · Kigali</span>
                <h1 class="fade-up" style="transition-delay:0.1s;">Distinguished Spirits, Poured With Reverence</h1>
                <p class="fade-up" style="transition-delay:0.2s;">From rare single malts to crafted cocktails — a curated collection of the world's finest, served in a room that knows how to slow down.</p>
                <div class="hero-actions fade-up" style="transition-delay:0.3s;">
                    <a href="menu/index.php" class="btn btn-light">Explore The Collection</a>
                    <a href="about.php" class="btn btn-outline" style="color:#fff; border-color:rgba(255,255,255,0.7);">Our Story</a>
                </div>
            </div>
            <a href="#statement" class="scroll-cue">
                <span>Scroll</span>
                <i class="ph ph-arrow-down"></i>
            </a>
        </section>

        <!-- BRAND STATEMENT -->
        <section class="section" id="statement">
            <div class="statement fade-up">
                <span class="eyebrow">Est. 2021</span>
                <p class="lead">We don't merely stock spirits — we curate <em>moments captured in glass</em>. Every bottle on our shelves is tasted, vetted and chosen for character, then poured in a setting designed to honour it.</p>
            </div>
        </section>

        <!-- FEATURED COLLECTION -->
        <section id="shop" class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">The Boutique</span>
                <h2>Featured Pours</h2>
                <p>A glimpse of the collection — handpicked highlights from a shelf of over five hundred.</p>
            </div>

            <ul class="shop-filters fade-up">
                <li class="active">All Spirits</li>
                <li>Single Malt</li>
                <li>Bourbon</li>
                <li>Cocktails</li>
                <li>Rare Vintages</li>
            </ul>

            <div class="collection-grid">
                <div class="product-card fade-up">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1527281400683-1aae777175f8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Highland Reserve 18">
                        <a href="menu/index.php" class="add-to-bag">View on Menu</a>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">50ml Pour &nbsp;|&nbsp; 43% ABV</div>
                        <h3>Highland Reserve 18</h3>
                        <div class="product-price">$320.00</div>
                    </div>
                </div>

                <div class="product-card fade-up" style="transition-delay:0.1s;">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1582226224220-410a56f26df8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Ozone Cask Strength">
                        <a href="menu/index.php" class="add-to-bag">View on Menu</a>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">50ml Pour &nbsp;|&nbsp; 55% ABV</div>
                        <h3>Ozone Cask Strength</h3>
                        <div class="product-price">$185.00</div>
                    </div>
                </div>

                <div class="product-card fade-up" style="transition-delay:0.2s;">
                    <div class="product-image">
                        <img src="https://images.unsplash.com/photo-1597075687490-8f673c6c17f6?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Kyoto Harmony">
                        <a href="menu/index.php" class="add-to-bag">View on Menu</a>
                    </div>
                    <div class="product-info">
                        <div class="product-meta">50ml Pour &nbsp;|&nbsp; 46% ABV</div>
                        <h3>Kyoto Harmony</h3>
                        <div class="product-price">$450.00</div>
                    </div>
                </div>
            </div>

            <div style="text-align:center; margin-top:60px;" class="fade-up">
                <a href="menu/index.php" class="btn btn-solid">View Full Menu</a>
            </div>
        </section>

        <!-- STATS BAND -->
        <section class="section ink">
            <div class="stats-row fade-up">
                <div class="stat">
                    <div class="stat-num" data-count="500+">500+</div>
                    <div class="stat-label">Premium Spirits</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="50+">50+</div>
                    <div class="stat-label">Craft Cocktails</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="10000+">10,000+</div>
                    <div class="stat-label">Happy Guests</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="5">5</div>
                    <div class="stat-label">Years of Excellence</div>
                </div>
            </div>
        </section>

        <!-- HERITAGE EDITORIAL -->
        <section id="heritage" class="editorial-split">
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1569529465841-dfecdab7503b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Oak whisky barrels">
            </div>
            <div class="editorial-text">
                <span class="eyebrow fade-up">Our Sourcing</span>
                <h2 class="fade-up">Uncompromising craft, honest provenance.</h2>
                <p class="fade-up">We travel to the most secluded distilleries on earth and negotiate directly with the makers — securing casks that have rested in silence for decades. From the forest that grew the oak to the blender who signed the seal, every drop is traced to its source.</p>
                <p class="fade-up">It's slower this way. It's also the only way we know how to pour something truly worth remembering.</p>
                <a href="about.php" class="btn btn-outline fade-up">Read Our Story</a>
            </div>
        </section>

        <!-- WHY OZONE -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">Why Ozone</span>
                <h2>Curated. Crafted. Hosted.</h2>
            </div>
            <div class="value-grid container">
                <div class="value-card fade-up">
                    <div class="value-icon"><i class="ph ph-flask"></i></div>
                    <h3>Curated</h3>
                    <p>A collection chosen one bottle at a time — never by trend, always by taste. If it's on our shelf, it earned its place.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay:0.12s;">
                    <div class="value-icon"><i class="ph ph-martini"></i></div>
                    <h3>Crafted</h3>
                    <p>Signature cocktails built by hand and finished tableside. Smoked, stirred and served with a little theatre.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay:0.24s;">
                    <div class="value-icon"><i class="ph ph-armchair"></i></div>
                    <h3>Hosted</h3>
                    <p>Warm light, a knowing welcome, and a team that loves what it serves. The room makes the moment.</p>
                </div>
            </div>
        </section>

        <!-- SIGNATURE COCKTAILS -->
        <section class="section">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">From The Mixologist</span>
                <h2>Signature serves</h2>
                <p>The pours our regulars keep coming back for.</p>
            </div>
            <div class="special-grid container">
                <div class="special-card fade-up">
                    <div class="special-num">01</div>
                    <h3>Smoked Old Fashioned</h3>
                    <p>Cask bourbon and demerara under a cloud of oak smoke.</p>
                    <div class="special-ingredients">Bourbon · Demerara · Oak Smoke</div>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.1s;">
                    <div class="special-num">02</div>
                    <h3>Kivu Sour</h3>
                    <p>A proudly local original — hibiscus gin, fresh lime, silky egg white.</p>
                    <div class="special-ingredients">Hibiscus Gin · Lime · Egg White</div>
                </div>
                <div class="special-card fade-up" style="transition-delay:0.2s;">
                    <div class="special-num">03</div>
                    <h3>Espresso Martini Noir</h3>
                    <p>Cold-brew, vanilla vodka and a velvet crema.</p>
                    <div class="special-ingredients">Vodka · Cold Brew · Vanilla</div>
                </div>
            </div>
        </section>

        <!-- TESTIMONIALS -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">In Good Company</span>
                <h2>What our guests say</h2>
            </div>
            <div class="testimonial-grid container">
                <div class="testimonial fade-up">
                    <div class="stars">★★★★★</div>
                    <p class="quote">"The single malt selection is unreal for Kigali. Easily my favourite spot in the city for a quiet, serious pour."</p>
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
                    <p class="quote">"Booked the room for a corporate evening — flawless service, custom flight, zero stress. We'll be back."</p>
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
                    <p class="quote">"That smoked old fashioned is the best cocktail I've had in the city. The whole place just feels considered."</p>
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

        <!-- VISIT CTA -->
        <section class="section ink">
            <div class="cta-band fade-up">
                <span class="eyebrow on-dark">Find Your Seat</span>
                <h2 style="color:#fff;">An evening at Ozone awaits</h2>
                <p>Open Monday to Saturday from noon, Sundays from four. Happy hour daily, 5–7pm. Walk in, or reserve the room for something special.</p>
                <div class="hero-actions" style="margin-top:32px;">
                    <a href="contact.php" class="btn btn-gold">Plan Your Visit</a>
                    <a href="menu/index.php" class="btn btn-outline" style="color:#fff; border-color:rgba(255,255,255,0.7);">See The Menu</a>
                </div>
            </div>
        </section>

        <!-- NEWSLETTER -->
        <section class="section">
            <div class="newsletter fade-up">
                <span class="eyebrow">The Inner Circle</span>
                <h2>New arrivals, before anyone else</h2>
                <p>Join our list for first word on rare bottles, tasting nights and members-only events. No noise — just the good stuff.</p>
                <form class="newsletter-form" onsubmit="event.preventDefault(); this.reset(); this.nextElementSibling.style.display='block';">
                    <input type="email" placeholder="your@email.com" aria-label="Email address" required>
                    <button type="submit">Subscribe</button>
                </form>
                <p class="fineprint" style="display:none;">Thank you — you're on the list. Welcome to the inner circle.</p>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>
