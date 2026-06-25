<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="index.php" class="brand">
                <i class="ph-fill ph-hexagon"></i>
                Kami OS
            </a>
            <p>The enterprise-grade retail operations engine designed for high-velocity environments and absolute reliability.</p>
        </div>
        <div class="footer-col">
            <h4>Product</h4>
            <ul class="footer-links">
                <li><a href="features.php">Point of Sale</a></li>
                <li><a href="features.php">Inventory Sync</a></li>
                <li><a href="features.php">Analytics</a></li>
                <li><a href="#">Integrations</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Company</h4>
            <ul class="footer-links">
                <li><a href="about.php">About Us</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Resources</h4>
            <ul class="footer-links">
                <li><a href="#">Documentation</a></li>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">API Reference</a></li>
                <li><a href="#">System Status</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Legal</h4>
            <ul class="footer-links">
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Security</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div>&copy; <?= date('Y') ?> Kami Systems Inc. All rights reserved.</div>
        <div style="display: flex; gap: 24px;">
            <a href="#" style="color: var(--text-tertiary);"><i class="ph-fill ph-twitter-logo" style="font-size: 20px;"></i></a>
            <a href="#" style="color: var(--text-tertiary);"><i class="ph-fill ph-github-logo" style="font-size: 20px;"></i></a>
            <a href="#" style="color: var(--text-tertiary);"><i class="ph-fill ph-linkedin-logo" style="font-size: 20px;"></i></a>
        </div>
    </div>
</footer>

<script>
    // Smooth sticky nav effect
    window.addEventListener('scroll', () => {
        const nav = document.querySelector('nav');
        if (window.scrollY > 20) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // Scroll Reveal Animation Logic
    document.addEventListener('DOMContentLoaded', () => {
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.reveal').forEach(el => {
            observer.observe(el);
        });

        // Mouse tracking for bento card glows
        document.querySelectorAll('.bento-card').forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        });
    });
</script>
<script src="assets/js/public-fx.js" defer></script>