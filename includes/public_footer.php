<?php
/* Shared OZONE boutique footer. Subdirectory pages set $base = '../' first. */
$base = $base ?? '';
?>
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <h2>OZONE LIQUOR</h2>
            <p>Purveyors of distinguished spirits and crafted cocktails. A boutique tasting house in the heart of Kigali, curating liquid history one pour at a time.</p>
        </div>
        <div class="footer-links">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?= $base ?>menu/index.php">The Collection</a></li>
                <li><a href="<?= $base ?>features.php">Experience</a></li>
                <li><a href="<?= $base ?>about.php">Our Heritage</a></li>
                <li><a href="<?= $base ?>contact.php">Visit Us</a></li>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Visit</h4>
            <ul>
                <li><a href="<?= $base ?>contact.php">KN 4 Ave, Kigali</a></li>
                <li><a href="tel:+250788000000">+250 788 000 000</a></li>
                <li><a href="mailto:hello@ozoneliquor.rw">hello@ozoneliquor.rw</a></li>
                <li><a href="<?= $base ?>contact.php">Private Events</a></li>
            </ul>
        </div>
        <div class="footer-links">
            <h4>Connect</h4>
            <ul>
                <li><a href="#">Instagram</a></li>
                <li><a href="#">Twitter / X</a></li>
                <li><a href="#">Facebook</a></li>
                <li><a href="<?= $base ?>login.php">Staff Terminal</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; <?= date('Y') ?> Ozone Liquor. All rights reserved.</div>
        <div>Please enjoy responsibly. Not for sale to persons under 18.</div>
    </div>
</footer>

<script>
    // Sticky nav: switch from blend-mode overlay to solid bar after scrolling.
    (function () {
        var navbar = document.getElementById('navbar');
        if (navbar) {
            var onScroll = function () {
                if (window.scrollY > 50) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        // Scroll-reveal for .fade-up elements (public-fx.js defers to this).
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            document.querySelectorAll('.fade-up').forEach(function (el) { io.observe(el); });
        } else {
            document.querySelectorAll('.fade-up').forEach(function (el) { el.classList.add('visible'); });
        }
    })();
</script>
<script src="<?= $base ?>assets/js/public-fx.js" defer></script>
