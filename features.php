<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features | Kami OS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

    <div class="ambient-background"></div>
    <div class="glow-orb top"></div>

    <?php include 'includes/public_nav.php'; ?>

    <main style="padding-top: 120px;">
        <section style="text-align: center; max-width: 800px;">
            <div class="hero-badge reveal">Platform Capabilities</div>
            <h1 style="font-size: clamp(40px, 5vw, 64px); font-weight: 800; letter-spacing: -0.04em; margin-bottom: 24px; color: var(--text-primary);" class="reveal delay-1">Everything you need. Nothing you don't.</h1>
            <p style="font-size: 20px; color: var(--text-secondary); margin-bottom: 48px;" class="reveal delay-2">We stripped away the clutter of legacy POS systems to build a streamlined, highly performant engine.</p>
        </section>

        <!-- Feature Split 1 -->
        <section class="split-section">
            <div class="split-content reveal">
                <h3>High-Velocity Checkout</h3>
                <p>Engineered to eliminate friction at the counter. The terminal interface is designed exclusively for speed, supporting advanced barcode parsing and keyboard shortcuts.</p>
                <ul class="split-list">
                    <li><i class="ph-bold ph-check-circle"></i> Offline-first architecture</li>
                    <li><i class="ph-bold ph-check-circle"></i> Integrated payment processing</li>
                    <li><i class="ph-bold ph-check-circle"></i> Sub-millisecond barcode response</li>
                </ul>
            </div>
            <!-- Custom CSS Mockup: POS Terminal -->
            <div class="mockup-window reveal delay-1" style="transform: none; box-shadow: var(--shadow-glass);">
                <div class="mockup-header">
                    <div class="mockup-dot"></div><div class="mockup-dot"></div><div class="mockup-dot"></div>
                </div>
                <div style="padding: 24px; display: grid; grid-template-columns: 1fr 200px; gap: 24px; background: var(--bg-surface);">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                        <!-- Mock Products -->
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--border-dim); border-radius: 8px;"></div>
                    </div>
                    <div style="background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border-dim); padding: 16px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="height: 12px; background: rgba(255,255,255,0.1); border-radius: 4px; margin-bottom: 8px;"></div>
                            <div style="height: 12px; background: rgba(255,255,255,0.1); border-radius: 4px; margin-bottom: 8px; width: 80%;"></div>
                        </div>
                        <div>
                            <div style="height: 1px; background: var(--border-dim); margin: 16px 0;"></div>
                            <div style="height: 24px; background: var(--accent-main); border-radius: 4px; opacity: 0.8;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Split 2 -->
        <section class="split-section reverse" style="padding-top: 40px;">
            <div class="split-content reveal">
                <h3>Absolute Auditing Precision</h3>
                <p>Human error is a thing of the past. Kami OS mathematically compares starting floats against hard transaction logs to generate flawless daily Z-Reports.</p>
                <ul class="split-list">
                    <li><i class="ph-bold ph-check-circle"></i> Cryptographic shift locking</li>
                    <li><i class="ph-bold ph-check-circle"></i> Blind cash drop methodology</li>
                    <li><i class="ph-bold ph-check-circle"></i> Instant discrepancy alerts</li>
                </ul>
            </div>
            <!-- Custom CSS Mockup: Data List -->
            <div class="mockup-window reveal delay-1" style="transform: none; box-shadow: var(--shadow-glass);">
                <div class="mockup-header">
                    <div class="mockup-dot"></div><div class="mockup-dot"></div><div class="mockup-dot"></div>
                </div>
                <div style="padding: 24px; background: var(--bg-surface); display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-dim); border-radius: 8px;">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></div>
                            <div style="height: 8px; width: 120px; background: var(--border-bright); border-radius: 4px;"></div>
                        </div>
                        <div style="height: 8px; width: 60px; background: var(--border-dim); border-radius: 4px;"></div>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-dim); border-radius: 8px;">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></div>
                            <div style="height: 8px; width: 100px; background: var(--border-bright); border-radius: 4px;"></div>
                        </div>
                        <div style="height: 8px; width: 60px; background: var(--border-dim); border-radius: 4px;"></div>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; border: 1px solid var(--border-accent); background: rgba(245, 158, 11, 0.05); border-radius: 8px;">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-main); box-shadow: 0 0 8px var(--accent-main);"></div>
                            <div style="height: 8px; width: 140px; background: var(--accent-main); border-radius: 4px;"></div>
                        </div>
                        <div style="height: 8px; width: 60px; background: var(--accent-main); opacity: 0.5; border-radius: 4px;"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA SECTION -->
        <section>
            <div class="cta-box reveal">
                <h2>Experience the speed firsthand.</h2>
                <p>Sign up today and get full access to the platform for 14 days. No credit card required.</p>
                <div style="display: flex; gap: 16px; justify-content: center;">
                    <a href="login.php" class="btn btn-lg btn-primary">Start Trial</a>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>