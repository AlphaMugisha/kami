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
$nav_active = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | Our Story</title>

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
                <img src="https://images.unsplash.com/photo-1569529465841-dfecdab7503b?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Oak whiskey barrels resting in a cellar">
            </div>
            <div class="hero-content">
                <span class="eyebrow on-dark fade-up">Established in Kigali</span>
                <h1 class="fade-up" style="transition-delay: 0.1s;">Our Story</h1>
                <p class="fade-up" style="transition-delay: 0.2s;">Ozone began with a single conviction — that a great pour is never just a drink, but a moment worth slowing down for.</p>
            </div>
        </section>

        <!-- 2 · BRAND STORY -->
        <section class="editorial-split">
            <div class="editorial-text">
                <span class="eyebrow fade-up">How It Began</span>
                <h2 class="fade-up">A boutique built on patience and curiosity.</h2>
                <p class="fade-up">Ozone was founded in 2021 by a small circle of friends who shared an obsession with craft — and a frustration with how rushed the everyday drink had become. We opened our doors on KN 4 Avenue with a modest shelf of bottles, a handful of barstools, and a promise to treat every spirit with the reverence it deserved.</p>
                <p class="fade-up">What started as a quiet tasting corner has grown into one of Kigali's most considered liquor destinations. We travel, we taste, and we negotiate directly with distillers — from the misty Scottish Highlands to the foothills of Kentucky — to bring home bottles you simply won't find anywhere else in the city.</p>
                <p class="fade-up">Yet our ambition has never been size. It has always been feeling: the warmth of a room that knows your name, the thrill of a pour you've never met before, and the easy confidence of people who genuinely love what they serve.</p>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1595963503565-df0ea6df28b5?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Master pouring whisky into a tasting glass">
            </div>
        </section>

        <!-- 3 · MISSION & VALUES -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">What We Stand For</span>
                <h2>The values behind every pour</h2>
                <p>Three principles guide what we stock, how we serve, and the kind of room we want you to walk into.</p>
            </div>
            <div class="value-grid container">
                <div class="value-card fade-up">
                    <div class="value-icon"><i class="ph ph-medal"></i></div>
                    <h3>Quality</h3>
                    <p>We never carry a bottle we wouldn't pour for ourselves. Every spirit on our shelves is tasted, vetted, and chosen for character — not for the label or the markup. If it earns a place at Ozone, it has earned your trust.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.12s;">
                    <div class="value-icon"><i class="ph ph-sparkle"></i></div>
                    <h3>Experience</h3>
                    <p>A drink should be an occasion. From the weight of the glass to the music in the room, we obsess over the details most places overlook — because the difference between good and unforgettable lives entirely in the details.</p>
                </div>
                <div class="value-card fade-up" style="transition-delay: 0.24s;">
                    <div class="value-icon"><i class="ph ph-users-three"></i></div>
                    <h3>Community</h3>
                    <p>Ozone is proudly Kigali. We champion local makers, host neighbourhood tastings, and build a room where regulars and first-timers share the same bar. Great spirits are best enjoyed in great company.</p>
                </div>
            </div>
        </section>

        <!-- 4 · OUR SPACE -->
        <section class="editorial-split reverse">
            <div class="editorial-text">
                <span class="eyebrow fade-up">The Room</span>
                <h2 class="fade-up">Step inside, and the city quiets down.</h2>
                <p class="fade-up">Push open our brass-handled door and the noise of the street falls away. Warm amber light pools across reclaimed oak, the back bar glows with a wall of bottles, and the air carries the faint sweetness of charred barrels and citrus peel.</p>
                <p class="fade-up">There's a seat for every mood — the long copper bar for conversation with our tenders, a quiet velvet booth for two, and a candlelit tasting nook for those who want to linger over a flight. Whether you've come to celebrate, to unwind, or simply to learn, the room makes space for it.</p>
                <a href="contact.php" class="btn btn-outline fade-up">Plan Your Visit</a>
            </div>
            <div class="image-reveal fade-up">
                <img src="https://images.unsplash.com/photo-1514362545857-3bc16c4c7d1b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1000&q=80" alt="Atmospheric bar interior with warm lighting">
            </div>
        </section>

        <!-- 5 · THE TEAM -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">The People</span>
                <h2>Faces behind the bar</h2>
                <p>A small team with an outsized passion for spirits, hospitality, and getting the little things right.</p>
            </div>
            <div class="team-grid container">
                <div class="team-card fade-up">
                    <div class="team-photo" style="background-image:url('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80');"></div>
                    <h4>Jean-Paul Habimana</h4>
                    <span class="team-role">Founder &amp; Curator</span>
                    <p>The palate behind the collection. Jean-Paul personally selects every cask and rare bottle that reaches our shelves.</p>
                </div>
                <div class="team-card fade-up" style="transition-delay: 0.1s;">
                    <div class="team-photo" style="background-image:url('https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80');"></div>
                    <h4>Aline Uwase</h4>
                    <span class="team-role">Head Sommelier</span>
                    <p>Equal parts host and guide, Aline can match any mood to the perfect pour — and tell you the story behind it.</p>
                </div>
                <div class="team-card fade-up" style="transition-delay: 0.2s;">
                    <div class="team-photo" style="background-image:url('https://images.unsplash.com/photo-1500648767791-00dcc994a43e?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80');"></div>
                    <h4>David Mugisha</h4>
                    <span class="team-role">Lead Mixologist</span>
                    <p>The inventor of our signature cocktails. If it shakes, stirs, or smokes, David has probably perfected it.</p>
                </div>
                <div class="team-card fade-up" style="transition-delay: 0.3s;">
                    <div class="team-photo" style="background-image:url('https://images.unsplash.com/photo-1438761681033-6461ffad8d80?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80');"></div>
                    <h4>Grace Ingabire</h4>
                    <span class="team-role">Guest Experience</span>
                    <p>The first smile you meet and the last goodbye you hear. Grace makes sure every visit feels like coming home.</p>
                </div>
            </div>
        </section>

        <!-- 6 · NUMBERS / STATS -->
        <section class="section ink">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow on-dark">By The Numbers</span>
                <h2>Five years, one obsession</h2>
            </div>
            <div class="stats-row fade-up">
                <div class="stat">
                    <div class="stat-num" data-count="500+">500+</div>
                    <div class="stat-label">Premium Spirits</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="5">5</div>
                    <div class="stat-label">Years of Excellence</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="10000+">10,000+</div>
                    <div class="stat-label">Happy Guests</div>
                </div>
                <div class="stat">
                    <div class="stat-num" data-count="50+">50+</div>
                    <div class="stat-label">Craft Cocktails</div>
                </div>
            </div>
        </section>

        <!-- 7 · CTA -->
        <section class="section">
            <div class="cta-band fade-up">
                <span class="eyebrow">Your Table Awaits</span>
                <h2>Experience Ozone</h2>
                <p>Whether it's a quiet evening pour or a celebration worth remembering, there's always a seat for you at the bar. Come taste what we've been curating.</p>
                <a href="menu/index.php" class="btn btn-solid">Explore The Collection</a>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>
