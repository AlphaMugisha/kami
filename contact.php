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

// Lightweight front-end confirmation flag (no backend mailer wired yet).
$sent = isset($_POST['submit_contact']);
$nav_active = 'contact';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OZONE | Visit Us</title>

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
                <img src="https://images.unsplash.com/photo-1543007630-9710e4a00a20?ixlib=rb-1.2.1&auto=format&fit=crop&w=2000&q=80" alt="Warmly lit bar at night">
            </div>
            <div class="hero-content">
                <span class="eyebrow on-dark fade-up">We'd Love To Meet You</span>
                <h1 class="fade-up" style="transition-delay: 0.1s;">Get In Touch</h1>
                <p class="fade-up" style="transition-delay: 0.2s;">Questions, reservations, or planning something special? Our team is always happy to hear from you.</p>
            </div>
        </section>

        <!-- 2 · CONTACT INFO GRID -->
        <section class="section">
            <div class="contact-grid container">
                <div class="info-card fade-up">
                    <div class="value-icon"><i class="ph ph-map-pin"></i></div>
                    <h4>Visit</h4>
                    <p>KN 4 Avenue, Nyarugenge</p>
                    <p>Kigali, Rwanda</p>
                    <p style="margin-top:8px; font-size:12px;">A short walk from the city centre, just off the main avenue.</p>
                </div>
                <div class="info-card fade-up" style="transition-delay:0.08s;">
                    <div class="value-icon"><i class="ph ph-phone"></i></div>
                    <h4>Call</h4>
                    <p><a href="tel:+250788000000">+250 788 000 000</a></p>
                    <p style="margin-top:8px; font-size:12px;">Mon–Sat, 12pm – 11pm</p>
                    <p style="font-size:12px;">Sun, 4pm – 10pm</p>
                </div>
                <div class="info-card fade-up" style="transition-delay:0.16s;">
                    <div class="value-icon"><i class="ph ph-envelope-simple"></i></div>
                    <h4>Email</h4>
                    <p><a href="mailto:hello@ozoneliquor.rw">hello@ozoneliquor.rw</a></p>
                    <p style="margin-top:8px; font-size:12px;">Events &amp; bookings:</p>
                    <p style="font-size:12px;"><a href="mailto:events@ozoneliquor.rw">events@ozoneliquor.rw</a></p>
                </div>
                <div class="info-card fade-up" style="transition-delay:0.24s;">
                    <div class="value-icon"><i class="ph ph-instagram-logo"></i></div>
                    <h4>Follow</h4>
                    <p style="font-size:12px;">Pours, events &amp; new arrivals.</p>
                    <div class="socials">
                        <a href="#" aria-label="Instagram"><i class="ph-fill ph-instagram-logo"></i></a>
                        <a href="#" aria-label="Twitter"><i class="ph-fill ph-twitter-logo"></i></a>
                        <a href="#" aria-label="Facebook"><i class="ph-fill ph-facebook-logo"></i></a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3 · FORM + 4 · MAP -->
        <section class="section paper">
            <div class="contact-layout container">
                <div class="fade-up">
                    <span class="eyebrow">Send A Message</span>
                    <h2 style="font-size:clamp(32px,4vw,46px); margin-bottom:32px;">Drop us a line</h2>

                    <?php if ($sent): ?>
                        <div style="border:1px solid var(--accent-gold); background:var(--accent-gold-soft); padding:24px; margin-bottom:24px; display:flex; gap:14px; align-items:center;">
                            <i class="ph-fill ph-check-circle" style="font-size:28px; color:var(--accent-gold);"></i>
                            <p style="color:var(--text-charcoal); font-weight:400;">Thank you — your message is on its way. We'll be in touch shortly.</p>
                        </div>
                    <?php endif; ?>

                    <form class="contact-form" action="contact.php" method="POST">
                        <div class="form-row">
                            <div class="field">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" placeholder="Your full name" required>
                            </div>
                            <div class="field">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" placeholder="+250 7XX XXX XXX">
                            </div>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="field">
                            <label for="subject">Subject</label>
                            <select id="subject" name="subject">
                                <option>General Inquiry</option>
                                <option>Event Booking</option>
                                <option>Feedback</option>
                                <option>Partnership</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" placeholder="Tell us how we can help..." required></textarea>
                        </div>
                        <button type="submit" name="submit_contact" class="btn btn-solid" style="align-self:flex-start;">Send Message</button>
                    </form>
                </div>

                <div class="fade-up" style="transition-delay:0.1s;">
                    <span class="eyebrow">Find Us</span>
                    <h2 style="font-size:clamp(32px,4vw,46px); margin-bottom:32px;">On the map</h2>
                    <div class="map-placeholder">
                        <iframe
                            src="https://www.google.com/maps?q=Kigali,Rwanda&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Ozone Liquor location in Kigali"></iframe>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5 · BUSINESS HOURS -->
        <section class="section">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">When To Find Us</span>
                <h2>Opening hours</h2>
            </div>
            <div class="hours-card fade-up">
                <div class="hours-row">
                    <span class="hours-day">Monday – Thursday</span>
                    <span class="hours-time">12:00pm – 11:00pm</span>
                </div>
                <div class="hours-row">
                    <span class="hours-day">Friday – Saturday</span>
                    <span class="hours-time">12:00pm – 1:00am</span>
                </div>
                <div class="hours-row">
                    <span class="hours-day">Sunday</span>
                    <span class="hours-time">4:00pm – 10:00pm</span>
                </div>
                <div class="hours-row featured">
                    <span class="hours-day">Happy Hour · Daily</span>
                    <span class="hours-time">5:00pm – 7:00pm</span>
                </div>
            </div>
        </section>

        <!-- 6 · FAQ -->
        <section class="section paper">
            <div class="section-head fade-up">
                <div class="rule"></div>
                <span class="eyebrow">Good To Know</span>
                <h2>Frequently asked</h2>
            </div>
            <div class="faq container fade-up">
                <details class="faq-item">
                    <summary>Do you take reservations? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>Yes. We hold a portion of the room for walk-ins, but we recommend reserving for groups of four or more — especially on Friday and Saturday evenings. Drop us a message or give us a call to book.</p></div>
                </details>
                <details class="faq-item">
                    <summary>Can I order from my table? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>Absolutely. Every table has a QR code that opens our full menu on your phone. Browse, order, and pay right from your seat — by Mobile Money, card, or cash.</p></div>
                </details>
                <details class="faq-item">
                    <summary>Do you host private events? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>We do — from guided tastings to corporate evenings and milestone celebrations. We can tailor a menu and reserve the room for your group. Email <a href="mailto:events@ozoneliquor.rw" style="color:var(--accent-gold);">events@ozoneliquor.rw</a> to start planning.</p></div>
                </details>
                <details class="faq-item">
                    <summary>What payment methods do you accept? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>Mobile Money (MoMo), all major cards, and cash. You can settle from the table-side menu or with any member of our team.</p></div>
                </details>
                <details class="faq-item">
                    <summary>Is there parking? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>Yes — there's secure on-site parking for guests, with additional street parking just along the avenue. Ride-hailing drop-offs are easy at our front entrance too.</p></div>
                </details>
                <details class="faq-item">
                    <summary>What's the dress code? <span class="faq-plus">+</span></summary>
                    <div class="faq-body"><p>Smart casual. Come comfortable, but we'd gently ask you to leave the gym wear and flip-flops at home — it helps keep the room feeling like the occasion it is.</p></div>
                </details>
            </div>
        </section>

        <!-- 7 · CTA -->
        <section class="section">
            <div class="cta-band fade-up">
                <span class="eyebrow">See You Soon</span>
                <h2>We'd love to hear from you</h2>
                <p>Whether it's a question, a booking, or just a hello — our door (and our inbox) is always open.</p>
                <a href="menu/index.php" class="btn btn-solid">Explore The Menu</a>
            </div>
        </section>
    </main>

    <?php include 'includes/public_footer.php'; ?>

</body>
</html>
