<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Kami OS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

    <div class="ambient-background"></div>
    <div class="glow-orb top" style="left: -10%;"></div>

    <?php include 'includes/public_nav.php'; ?>

    <main style="padding-top: 160px;">
        <section style="padding-top: 0;">
            <div class="section-header reveal">
                <h1 style="font-size: clamp(48px, 6vw, 72px); font-weight: 800; letter-spacing: -0.04em; margin-bottom: 24px;">Talk to our team.</h1>
                <p style="font-size: 22px;">Have technical questions or need a custom deployment plan? Our engineers and account executives are here to assist.</p>
            </div>

            <div class="contact-grid">
                <!-- Contact Info Column -->
                <div class="reveal delay-1" style="display: flex; flex-direction: column; gap: 40px;">
                    <div class="bento-card" style="padding: 32px;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--accent-main);"><i class="ph-bold ph-envelope" style="font-size: 24px;"></i></div>
                            <h4 style="font-size: 20px; font-weight: 700;">Email</h4>
                        </div>
                        <p style="color: var(--text-secondary); margin-bottom: 8px;">General inquiries & Sales</p>
                        <p style="font-size: 16px; font-weight: 600; color: var(--text-primary);">hello@kamios.com</p>
                    </div>

                    <div class="bento-card" style="padding: 32px;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; background: rgba(56, 189, 248, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #38bdf8;"><i class="ph-bold ph-phone" style="font-size: 24px;"></i></div>
                            <h4 style="font-size: 20px; font-weight: 700;">Phone</h4>
                        </div>
                        <p style="color: var(--text-secondary); margin-bottom: 8px;">Available Mon-Fri, 9am-6pm (CAT)</p>
                        <p style="font-size: 16px; font-weight: 600; color: var(--text-primary);">+250 788 000 000</p>
                    </div>
                </div>

                <!-- Form Column -->
                <div class="form-container reveal delay-2">
                    <form action="#" method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="input-group">
                                <label>First Name</label>
                                <input type="text" placeholder="John" required>
                            </div>
                            <div class="input-group">
                                <label>Last Name</label>
                                <input type="text" placeholder="Doe" required>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Work Email</label>
                            <input type="email" placeholder="john@company.com" required>
                        </div>
                        <div class="input-group">
                            <label>Company/Store Name</label>
                            <input type="text" placeholder="Acme Retail" required>
                        </div>
                        <div class="input-group">
                            <label>How can we help?</label>
                            <textarea rows="5" placeholder="Tell us about your technical requirements..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-accent" style="width: 100%; font-size: 16px; padding: 16px;">Send Message</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section style="max-width: 800px;">
            <div class="section-header reveal" style="text-align: center; margin: 0 auto 64px;">
                <h2>Frequently Asked Questions</h2>
            </div>
            <div class="reveal delay-1">
                <details class="faq-item">
                    <summary>Do I need specialized hardware to run Kami OS?</summary>
                    <p>No. Kami OS is engineered as a highly optimized web application. It runs smoothly on any modern desktop, laptop, or tablet with a web browser, eliminating the need for expensive proprietary hardware.</p>
                </details>
                <details class="faq-item">
                    <summary>Is the system secure?</summary>
                    <p>Absolute security is our foundational principle. We utilize modern cryptographic hashing for passwords, strict session validation, and Role-Based Access Control (RBAC) to ensure cashiers cannot access management dashboards.</p>
                </details>
                <details class="faq-item">
                    <summary>How does the automated shift auditing work?</summary>
                    <p>When a cashier clocks out, the system calculates the expected drawer balance by applying mathematical logic to the starting float, absolute sales logs, and documented drops. It instantly highlights any discrepancies.</p>
                </details>
                <details class="faq-item">
                    <summary>Can I manage multiple store locations?</summary>
                    <p>Yes. The platform is designed from the ground up to support multi-tenant architectures, allowing you to track inventory and revenue across an unlimited number of geographic locations from a single admin workspace.</p>
                </details>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>