<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (file_exists('../includes/preloader.php')) {
    include '../includes/preloader.php';
}

require_once '../config/db.php';

$userName = $_SESSION['full_name'] ?? 'Admin';
$userRole = ucfirst($_SESSION['role'] ?? 'Admin');

// ==========================================
// 2. LIVE DASHBOARD ANALYTICS ENGINE
// ==========================================

// A. Today's Revenue
$stmt = $pdo->query("SELECT SUM(total) FROM sales WHERE DATE(created_at) = CURDATE()");
$todaySales = (float)($stmt->fetchColumn() ?: 0);

// B. Yesterday's Revenue
$stmt = $pdo->query("SELECT SUM(total) FROM sales WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
$yesterdaySales = (float)($stmt->fetchColumn() ?: 0);

$trendPercent = 0;
if ($yesterdaySales > 0) {
    $trendPercent = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100;
} elseif ($todaySales > 0) {
    $trendPercent = 100;
}

// C. Low Stock Warnings
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5");
$lowStockCount = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
$productCount = (int)$stmt->fetchColumn();

// D. Active Cashiers
$stmt = $pdo->query("SELECT COUNT(DISTINCT cashier_id) FROM sales WHERE DATE(created_at) = CURDATE()");
$activeCashiers = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'cashier'");
$cashierTotal = (int)$stmt->fetchColumn();

// E. Transactions today (for briefing)
$stmt = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()");
$txToday = (int)$stmt->fetchColumn();

// Goal Calculation
$dailyGoal = 1000.00;
$goalProgress = min(100, ($todaySales / $dailyGoal) * 100);

// F. Greeting + motivational quote (presentation only)
date_default_timezone_set('Africa/Kigali');
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$nameParts = preg_split('/\s+/', trim($userName)) ?: ['Admin'];
$firstName = $nameParts[0] ?: 'Admin';
$initials = strtoupper(substr($firstName, 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
$quotes = [
    'Excellence is a craft poured one measure at a time.',
    'Great service is the finest spirit you can offer.',
    'Precision today, prosperity tomorrow.',
    'Every bottle tells a story — make today\'s a good one.',
    'Attention to detail is the house signature.',
];
$quote = $quotes[(int)date('z') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ozone Admin | Dashboard</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        // Apply saved light/dark preference before paint (no flash)
        if (localStorage.getItem('kami_lightmode') === '1') document.documentElement.classList.add('light-mode');
    </script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        /* ── Top bar ───────────────────────────────────────────── */
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 26px; }
        .topbar-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; letter-spacing: -0.02em; color: var(--kami-text); }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .icon-btn {
            width: 44px; height: 44px; border-radius: var(--kami-radius-full);
            display: flex; align-items: center; justify-content: center;
            background: var(--kami-surface-2); border: 1px solid var(--kami-border);
            color: var(--kami-text-muted); font-size: 19px; cursor: pointer;
            transition: all var(--kami-transition);
        }
        .icon-btn:hover { color: var(--kami-accent); border-color: var(--kami-accent-border); background: var(--kami-accent-bg); transform: translateY(-2px); }
        .topbar-avatar {
            width: 44px; height: 44px; border-radius: var(--kami-radius-full);
            display: flex; align-items: center; justify-content: center;
            background: var(--kami-accent-grad); color: var(--kami-on-accent);
            font-family: var(--font-display); font-weight: 800; font-size: 15px;
            border: 1px solid var(--kami-accent-border); text-decoration: none; flex-shrink: 0;
        }

        /* ── Gradient hero header ──────────────────────────────── */
        .dash-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--kami-radius-xl);
            padding: 40px 44px;
            margin-bottom: 24px;
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            gap: 36px;
            flex-wrap: wrap;
            border: 1px solid var(--kami-accent-border);
            box-shadow: var(--kami-shadow-md);
            background:
                radial-gradient(120% 140% at 88% 0%, rgba(240, 206, 126, 0.55), transparent 55%),
                radial-gradient(120% 160% at 0% 120%, rgba(120, 80, 16, 0.55), transparent 60%),
                linear-gradient(120deg, #C8912F 0%, #A9761E 48%, #6E4E12 100%);
        }
        .dash-hero::after {
            content: '';
            position: absolute;
            top: -80px; right: 16%;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(255, 240, 200, 0.35), transparent 70%);
            filter: blur(24px);
            pointer-events: none;
        }
        .hero-left { position: relative; z-index: 2; max-width: 560px; display: flex; flex-direction: column; justify-content: center; }
        .hero-greeting {
            font-family: var(--font-display);
            font-size: clamp(30px, 3.6vw, 42px);
            font-weight: 800; line-height: 1.06; letter-spacing: -0.02em;
            color: #1A1206; margin-bottom: 12px;
        }
        .hero-sub { font-size: 16px; font-weight: 600; color: rgba(26, 18, 6, 0.78); margin-bottom: 20px; }
        .hero-quote {
            display: inline-flex; align-items: center; gap: 10px;
            font-family: var(--font-serif);
            font-size: 17px; font-style: italic;
            color: rgba(26, 18, 6, 0.9);
            background: rgba(255, 250, 240, 0.28);
            border: 1px solid rgba(26, 18, 6, 0.14);
            padding: 10px 18px; border-radius: var(--kami-radius-full);
            align-self: flex-start; backdrop-filter: blur(4px);
        }
        .hero-quote i { font-size: 16px; color: #5A3E0C; }

        .hero-right { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: flex-end; gap: 16px; }
        .clock-card {
            background: rgba(14, 10, 4, 0.42);
            border: 1px solid rgba(255, 246, 224, 0.22);
            border-radius: var(--kami-radius-lg);
            padding: 22px 30px; text-align: center;
            -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.12);
            min-width: 230px;
        }
        .clock-time {
            font-family: var(--font-display);
            font-size: 56px; font-weight: 800;
            letter-spacing: -0.04em; line-height: 1;
            font-variant-numeric: tabular-nums;
            color: #FFF6E0; text-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .clock-time .sec { font-size: 22px; font-weight: 700; color: rgba(255,246,224,0.7); margin-left: 4px; }
        .clock-date {
            margin-top: 14px; display: inline-block;
            font-size: 13px; font-weight: 700; color: #1A1206;
            background: rgba(255, 246, 224, 0.82);
            padding: 6px 16px; border-radius: var(--kami-radius-full);
        }
        .hero-cta {
            background: #1A1206; color: #FFF6E0;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 13px 24px; border-radius: var(--kami-radius-full);
            font-family: var(--font-sans); font-weight: 700; font-size: 14px;
            display: inline-flex; align-items: center; gap: 10px;
            text-decoration: none; cursor: pointer; transition: all var(--kami-transition);
            box-shadow: var(--kami-shadow-sm);
        }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: var(--kami-shadow-md); gap: 14px; }

        /* ── 4-up stat grid ────────────────────────────────────── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .kpi-card { display: flex; flex-direction: column; gap: 14px; padding: 22px; min-height: 158px; cursor: pointer; }
        .stat-icon {
            width: 46px; height: 46px; border-radius: var(--kami-radius-md);
            display: flex; align-items: center; justify-content: center; font-size: 23px;
            background: var(--kami-accent-bg); color: var(--kami-accent);
            border: 1px solid var(--kami-accent-border); flex-shrink: 0;
        }
        .stat-icon.info    { background: var(--kami-info-bg);    color: var(--kami-info);    border-color: var(--kami-info-border); }
        .stat-icon.success { background: var(--kami-success-bg); color: var(--kami-success); border-color: var(--kami-success-border); }
        .stat-icon.warning { background: var(--kami-warning-bg); color: var(--kami-warning); border-color: var(--kami-warning-border); }
        .stat-icon.danger  { background: var(--kami-danger-bg);  color: var(--kami-danger);  border-color: var(--kami-danger-border); }
        .stat-value { font-family: var(--font-display); font-size: 30px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; color: var(--kami-text); }
        .stat-sub { font-size: 13px; font-weight: 600; color: var(--kami-text-muted); }
        .stat-label { margin-top: auto; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--kami-text-dim); }
        .stat-bar { height: 7px; border-radius: var(--kami-radius-full); background: rgba(255,255,255,0.07); overflow: hidden; }
        .stat-bar-fill { height: 100%; border-radius: var(--kami-radius-full); background: var(--kami-accent-grad); box-shadow: 0 0 10px var(--kami-accent-border); transition: width 1.1s var(--kami-ease); }

        /* ── Bottom full-width briefing card ───────────────────── */
        .briefing { display: flex; align-items: center; gap: 22px; padding: 26px 30px; }
        .briefing-avatar {
            width: 58px; height: 58px; border-radius: var(--kami-radius-lg);
            display: flex; align-items: center; justify-content: center; font-size: 28px;
            background: var(--kami-accent-grad); color: var(--kami-on-accent);
            flex-shrink: 0; box-shadow: var(--kami-glow);
        }
        .briefing-text { flex: 1; min-width: 0; }
        .briefing-text h3 { font-family: var(--font-display); font-size: 17px; font-weight: 800; color: var(--kami-text); margin-bottom: 5px; }
        .briefing-text p { font-size: 14px; color: var(--kami-text-muted); line-height: 1.55; }
        .briefing-text p .hl { color: var(--kami-accent); font-weight: 700; }
        .briefing-action {
            display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0;
            color: var(--kami-accent); font-weight: 700; font-size: 14px; text-decoration: none;
            padding: 11px 20px; border-radius: var(--kami-radius-full);
            border: 1px solid var(--kami-accent-border); background: var(--kami-accent-bg);
            transition: all var(--kami-transition);
        }
        .briefing-action:hover { background: var(--kami-accent); color: var(--kami-on-accent); gap: 12px; }

        /* ── Mobile menu button + sidebar drawer (preserved) ───── */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-right { align-items: flex-start; }
        }

        @media (max-width: 768px) {
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; }
            .main-content { margin: 0 !important; padding: 16px !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; min-height: 100vh; }

            .dash-hero { padding: 28px 24px; flex-direction: column; align-items: flex-start; }
            .hero-right { align-items: stretch; width: 100%; }
            .clock-card { width: 100%; }
            .hero-cta { justify-content: center; }
            .clock-time { font-size: 46px; }
            .stats-grid { grid-template-columns: 1fr; gap: 14px; }
            .kpi-card { min-height: auto; }
            .briefing { flex-direction: column; align-items: flex-start; }
            .briefing-action { align-self: stretch; justify-content: center; }

            /* Sidebar slide-in drawer */
            .sidebar, aside.sidebar {
                position: fixed !important; top: 0; left: -300px !important;
                width: 280px !important; height: 100vh !important; z-index: 1000 !important;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 4px 0 24px rgba(0,0,0,0.5);
            }
            body.sidebar-open .sidebar { left: 0 !important; }
            .sidebar-overlay { display: block; position: fixed; inset: 0; background: rgba(0,0,0,0.55); -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
            body.sidebar-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; background: var(--kami-surface-3); border: 1px solid var(--kami-border); border-radius: var(--kami-radius-sm); width: 46px; height: 46px; color: var(--kami-text); cursor: pointer; flex-shrink: 0; }
        }
    </style>
</head>
<body class="app-layout">

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- ════════ TOP BAR ════════ -->
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu">
                    <i class="ph ph-list" style="font-size: 24px;"></i>
                </button>
                <h1 class="topbar-title">Dashboard</h1>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" onclick="ozoneToast('Search', 'Global search is coming soon.', 'info')" aria-label="Search"><i class="ph ph-magnifying-glass"></i></button>
                <button class="icon-btn" onclick="ozoneToast('Notifications', '<?= $lowStockCount ?> low-stock alert<?= $lowStockCount === 1 ? '' : 's' ?> pending.', '<?= $lowStockCount > 0 ? 'warning' : 'success' ?>')" aria-label="Notifications"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme"><i class="ph ph-sun"></i></button>
                <a href="settings.php" class="topbar-avatar" title="<?= htmlspecialchars($userName) ?>"><?= htmlspecialchars($initials) ?></a>
            </div>
        </div>

        <!-- ════════ GRADIENT HERO HEADER ════════ -->
        <header class="dash-hero animate-fade-in">
            <div class="hero-left">
                <div class="hero-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?> &#128075;</div>
                <p class="hero-sub">Here's what's happening across Ozone today.</p>
                <span class="hero-quote"><i class="ph-fill ph-sparkle"></i> <?= htmlspecialchars($quote) ?></span>
            </div>

            <div class="hero-right">
                <div class="clock-card">
                    <div class="clock-time" id="hero-clock">--:--</div>
                    <span class="clock-date" id="hero-date">&nbsp;</span>
                </div>
                <a href="../cashier/index.php" class="hero-cta">
                    <i class="ph-fill ph-play-circle"></i> Start Selling <i class="ph-bold ph-arrow-right"></i>
                </a>
            </div>
        </header>

        <!-- ════════ STAT GRID (4 equal cards) ════════ -->
        <div class="stats-grid">

            <!-- 1. Today's Sales -->
            <div class="card kpi-card animate-fade-in" onclick="ozoneToast('Revenue', 'Total logged today: $<?= number_format($todaySales, 2) ?>', 'success')">
                <div class="stat-icon"><i class="ph-fill ph-currency-dollar"></i></div>
                <div class="stat-value" data-count-to="<?= $todaySales ?>" data-prefix="$" data-decimals="2">$<?= number_format($todaySales, 2) ?></div>
                <div class="stat-sub">
                    <?php if ($trendPercent >= 0): ?>
                        <span style="color: var(--kami-success);">&#9650; +<?= number_format($trendPercent, 1) ?>%</span> vs yesterday
                    <?php else: ?>
                        <span style="color: var(--kami-danger);">&#9660; <?= number_format($trendPercent, 1) ?>%</span> vs yesterday
                    <?php endif; ?>
                </div>
                <div class="stat-label">Today's Sales</div>
            </div>

            <!-- 2. Daily Goal (progress) -->
            <div class="card kpi-card animate-fade-in" onclick="ozoneToast('Daily Goal', '$<?= number_format($todaySales, 0) ?> of $<?= number_format($dailyGoal, 0) ?> reached.', 'info')">
                <div class="stat-icon info"><i class="ph-fill ph-target"></i></div>
                <div class="stat-value"><?= round($goalProgress) ?>%</div>
                <div class="stat-bar"><div class="stat-bar-fill" style="width: <?= $goalProgress ?>%;"></div></div>
                <div class="stat-sub">$<?= number_format($todaySales, 0) ?> / $<?= number_format($dailyGoal, 0) ?> target</div>
                <div class="stat-label">Daily Goal</div>
            </div>

            <!-- 3. Stock Alerts -->
            <div class="card kpi-card animate-fade-in" onclick="location.href='inventory.php'">
                <div class="stat-icon <?= $lowStockCount > 0 ? 'danger' : 'success' ?>">
                    <i class="ph-fill <?= $lowStockCount > 0 ? 'ph-warning' : 'ph-check-circle' ?>"></i>
                </div>
                <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"><?= $lowStockCount ?></div>
                <div class="stat-sub"><?= $lowStockCount > 0 ? 'items need reorder' : 'all '.$productCount.' lines healthy' ?></div>
                <div class="stat-label">Stock Alerts</div>
            </div>

            <!-- 4. Active Cashiers -->
            <div class="card kpi-card animate-fade-in" onclick="ozoneToast('Team', '<?= $activeCashiers ?> of <?= $cashierTotal ?> cashiers active today.', 'info')">
                <div class="stat-icon success"><i class="ph-fill ph-users-three"></i></div>
                <div class="stat-value"><?= $activeCashiers ?><span style="font-size:18px; color: var(--kami-text-dim);">/<?= $cashierTotal ?></span></div>
                <div class="stat-sub"><?= $txToday ?> transaction<?= $txToday === 1 ? '' : 's' ?> rung today</div>
                <div class="stat-label">Team Online</div>
            </div>

        </div>

        <!-- ════════ FULL-WIDTH BRIEFING CARD ════════ -->
        <section class="card briefing animate-fade-in">
            <div class="briefing-avatar"><i class="ph-fill ph-martini"></i></div>
            <div class="briefing-text">
                <h3>Ozone Concierge</h3>
                <p>
                    <?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?>. The house has logged
                    <span class="hl">$<?= number_format($todaySales, 2) ?></span> across
                    <span class="hl"><?= $txToday ?></span> transaction<?= $txToday === 1 ? '' : 's' ?> today
                    — <?= $goalProgress >= 100 ? 'the daily goal is reached. ' : round($goalProgress).'% of the daily goal. ' ?>
                    <?= $lowStockCount > 0 ? '<span class="hl">'.$lowStockCount.'</span> product line'.($lowStockCount === 1 ? '' : 's').' need restocking.' : 'Inventory levels are healthy.' ?>
                </p>
            </div>
            <a href="reports.php" class="briefing-action">Open Reports <i class="ph-bold ph-arrow-right"></i></a>
        </section>

    </main>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }

        // Light / dark theme toggle (kami.css ships html.light-mode)
        function toggleTheme() {
            const isLight = document.documentElement.classList.toggle('light-mode');
            localStorage.setItem('kami_lightmode', isLight ? '1' : '0');
            const icon = document.querySelector('#themeToggle i');
            if (icon) icon.className = isLight ? 'ph ph-moon' : 'ph ph-sun';
        }
        (function () {
            const icon = document.querySelector('#themeToggle i');
            if (icon && document.documentElement.classList.contains('light-mode')) icon.className = 'ph ph-moon';
        })();

        // Live clock + date (client local time, with seconds like the reference)
        (function () {
            const clockEl = document.getElementById('hero-clock');
            const dateEl = document.getElementById('hero-date');
            function tick() {
                const now = new Date();
                let h = now.getHours();
                const m = now.getMinutes().toString().padStart(2, '0');
                const s = now.getSeconds().toString().padStart(2, '0');
                h = h % 12 || 12;
                clockEl.innerHTML = h + ':' + m + '<span class="sec">' + s + '</span>';
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
