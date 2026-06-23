<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

if (file_exists('../includes/preloader.php')) {
    include '../includes/preloader.php';
}

require_once '../config/db.php';

$cashierId = (int)$_SESSION['user_id'];
$userName  = $_SESSION['full_name'] ?? 'Cashier';
$userRole  = ucfirst($_SESSION['role'] ?? 'Cashier');

// ==========================================
// 2. PERSONAL SHIFT ANALYTICS ENGINE
// ==========================================

// A. My revenue today
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$cashierId]);
$todaySales = (float)($stmt->fetchColumn() ?: 0);

// B. My revenue yesterday (trend basis)
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = ? AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
$stmt->execute([$cashierId]);
$yesterdaySales = (float)($stmt->fetchColumn() ?: 0);

$trendPercent = 0;
if ($yesterdaySales > 0) {
    $trendPercent = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100;
} elseif ($todaySales > 0) {
    $trendPercent = 100;
}

// C. Transactions rung today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE cashier_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$cashierId]);
$txCount = (int)$stmt->fetchColumn();

// D. Current shift status
$stmt = $pdo->prepare("SELECT clock_in, starting_cash FROM shifts WHERE cashier_id = ? AND status = 'active' ORDER BY clock_in DESC LIMIT 1");
$stmt->execute([$cashierId]);
$activeShift = $stmt->fetch(PDO::FETCH_ASSOC);
$onShift = (bool)$activeShift;

// E. Pending tables awaiting checkout (shared)
$stmt = $pdo->query("SELECT COUNT(*) FROM qr_orders WHERE status IN ('pending','preparing')");
$pendingTables = (int)$stmt->fetchColumn();

// F. My recent transactions
$stmt = $pdo->prepare("
    SELECT s.id, s.total, s.created_at,
           (SELECT COALESCE(SUM(si.qty), 0) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
    FROM sales s
    WHERE s.cashier_id = ?
    ORDER BY s.created_at DESC LIMIT 5
");
$stmt->execute([$cashierId]);
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Shift goal calculation
$shiftGoal = 500.00;
$goalProgress = min(100, ($todaySales / $shiftGoal) * 100);
$ringOffset = 226 - (226 * ($goalProgress / 100));

// G. Greeting + motivational quote (presentation only)
date_default_timezone_set('Africa/Kigali');
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($userName))[0] ?: 'Cashier';
$quotes = [
    'Every sale is a story well served.',
    'A warm welcome is the best garnish.',
    'Quick hands, kind smile, perfect pour.',
    'Make the register sing, one ticket at a time.',
    'Great shifts are built on small wins.',
];
$quote = $quotes[(int)date('z') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ozone POS | My Shift</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        /* ── Gradient hero header ─────────────────────────────── */
        .dash-hero {
            position: relative;
            overflow: hidden;
            border-radius: var(--kami-radius-xl);
            padding: 38px 40px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 36px;
            flex-wrap: wrap;
            border: 1px solid var(--kami-glass-border);
            box-shadow: var(--kami-shadow-md);
            background:
                radial-gradient(130% 180% at 100% 0%, rgba(224, 184, 92, 0.20), transparent 58%),
                radial-gradient(120% 160% at 0% 120%, rgba(94, 179, 255, 0.08), transparent 55%),
                linear-gradient(135deg, rgba(34, 34, 44, 0.92), rgba(18, 18, 24, 0.78));
        }
        .dash-hero::after {
            content: '';
            position: absolute;
            top: -60px; right: -40px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(224, 184, 92, 0.25), transparent 70%);
            filter: blur(20px);
            pointer-events: none;
        }
        .hero-left { position: relative; z-index: 2; max-width: 560px; }
        .hero-eyebrow {
            font-family: var(--font-sans);
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.28em;
            color: var(--kami-accent);
            display: inline-flex; align-items: center; gap: 8px;
            margin-bottom: 14px;
        }
        .hero-eyebrow .island-status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--kami-success); box-shadow: 0 0 8px var(--kami-success); animation: pulse 2s infinite; }
        .hero-eyebrow.off .island-status-dot { background: var(--kami-text-dim); box-shadow: none; animation: none; }
        .hero-left h1 {
            font-family: var(--font-serif);
            font-size: clamp(32px, 4vw, 46px);
            font-weight: 600; line-height: 1.08;
            margin-bottom: 12px;
        }
        .hero-left h1 .accent-name { color: var(--kami-accent); font-style: italic; }
        .hero-quote {
            font-family: var(--font-serif);
            font-size: 19px; font-style: italic;
            color: var(--kami-text-muted);
            line-height: 1.5;
            border-left: 2px solid var(--kami-accent-border);
            padding-left: 16px;
            margin-bottom: 22px;
        }
        .hero-chips { display: flex; flex-wrap: wrap; gap: 10px; }
        .hero-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 9px 16px; border-radius: var(--kami-radius-full);
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--kami-border);
            color: var(--kami-text-muted);
            font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
            transition: all var(--kami-transition);
        }
        .hero-chip i { font-size: 16px; color: var(--kami-accent); }
        .hero-chip:hover { color: var(--kami-text); border-color: var(--kami-accent-border); background: var(--kami-accent-bg); transform: translateY(-2px); }

        .hero-right {
            position: relative; z-index: 2;
            display: flex; flex-direction: column;
            align-items: flex-end; gap: 18px;
            text-align: right;
        }
        .hero-clock {
            font-family: var(--font-display);
            font-size: 52px; font-weight: 800;
            letter-spacing: -0.03em; line-height: 1;
            font-variant-numeric: tabular-nums;
            color: var(--kami-text);
            text-shadow: 0 0 30px rgba(224,184,92,0.15);
        }
        .hero-clock .ampm { font-size: 22px; color: var(--kami-accent); margin-left: 4px; }
        .hero-date { font-size: 14px; font-weight: 600; color: var(--kami-text-muted); letter-spacing: 0.02em; }
        .hero-right .btn { margin-top: 4px; }

        /* ── 4-up metrics grid ─────────────────────────────────── */
        .metrics-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .metric-card { display: flex; flex-direction: column; justify-content: space-between; min-height: 168px; gap: 16px; padding: 24px; }
        .metric-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .metric-icon { width: 44px; height: 44px; border-radius: var(--kami-radius-md); display: flex; align-items: center; justify-content: center; font-size: 22px; background: var(--kami-accent-bg); color: var(--kami-accent); border: 1px solid var(--kami-accent-border); flex-shrink: 0; }
        .metric-icon.danger { background: var(--kami-danger-bg); color: var(--kami-danger); border-color: var(--kami-danger-border); }
        .metric-icon.info { background: var(--kami-info-bg); color: var(--kami-info); border-color: var(--kami-info-border); }
        .metric-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--kami-text-muted); }
        .metric-value { font-family: var(--font-display); font-size: 34px; font-weight: 800; letter-spacing: -0.03em; line-height: 1; color: var(--kami-text); }

        /* Goal ring */
        .widget-progress-circle { transform: rotate(-90deg); }
        .widget-circle-bg { fill: none; stroke: rgba(255, 255, 255, 0.05); stroke-width: 6; }
        .widget-circle-fill { fill: none; stroke: var(--kami-accent); stroke-width: 6; stroke-dasharray: 226; stroke-dashoffset: <?= $ringOffset ?>; stroke-linecap: round; filter: drop-shadow(0 0 6px var(--kami-accent)); transition: stroke-dashoffset 1.1s var(--kami-ease); }
        .goal-ring-wrap { width: 56px; height: 56px; position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .goal-ring-wrap span { position: absolute; font-size: 12px; font-weight: 800; color: var(--kami-text); }

        /* ── Secondary full-width activity card ────────────────── */
        .activity-card { padding: 0; overflow: hidden; }
        .activity-card .card-header { padding: 22px 28px; margin: 0; }
        .table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .recent-sales-list { width: 100%; border-collapse: collapse; min-width: 520px; }
        .recent-sales-list th { text-align: left; padding: 14px 28px; color: var(--kami-text-muted); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--kami-border); }
        .recent-sales-list td { padding: 16px 28px; font-size: 15px; border-bottom: 1px solid var(--kami-border); white-space: nowrap; }
        .recent-sales-list tbody tr { transition: background var(--kami-transition); }
        .recent-sales-list tbody tr:last-child td { border-bottom: none; }
        .recent-sales-list tbody tr:hover td { background: var(--kami-accent-bg); }

        /* ── Mobile menu button + sidebar drawer (preserved) ───── */
        .page-top-bar { display: none; align-items: center; gap: 14px; margin-bottom: 20px; }
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        @media (max-width: 1024px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
            .hero-right { align-items: flex-start; text-align: left; }
        }

        @media (max-width: 768px) {
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; }
            .main-content { margin: 0 !important; padding: 16px !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; min-height: 100vh; }

            .dash-hero { padding: 26px 22px; flex-direction: column; align-items: flex-start; }
            .hero-clock { font-size: 42px; }
            .metrics-grid { grid-template-columns: 1fr; gap: 14px; }
            .metric-card { min-height: auto; }

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

            .page-top-bar { display: flex; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; background: var(--kami-surface-3); border: 1px solid var(--kami-border); border-radius: var(--kami-radius-sm); width: 46px; height: 46px; color: var(--kami-text); cursor: pointer; flex-shrink: 0; }

            /* Recent activity -> stacked cards */
            .recent-sales-list, .recent-sales-list thead, .recent-sales-list tbody, .recent-sales-list tr, .recent-sales-list th, .recent-sales-list td { min-width: 0; }
            .recent-sales-list thead { display: none; }
            .recent-sales-list, .recent-sales-list tbody, .recent-sales-list tr, .recent-sales-list td { display: block; width: 100%; }
            .recent-sales-list tr { margin: 14px; background: var(--kami-surface-2); border: 1px solid var(--kami-border); border-radius: var(--kami-radius-md); padding: 14px; }
            .recent-sales-list td { display: flex; justify-content: space-between; align-items: center; padding: 9px 0 !important; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right; }
            .recent-sales-list td:last-child { border-bottom: none; }
            .recent-sales-list td::before { content: attr(data-label); font-weight: 700; color: var(--kami-text-muted); font-size: 13px; text-align: left; }
        }
    </style>
</head>
<body class="app-layout">

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <?php include '../includes/cashier_sidebar.php'; ?>

    <main class="main-content">

        <div class="page-top-bar">
            <button class="mobile-menu-btn" onclick="toggleSidebar()" aria-label="Open menu">
                <i class="ph ph-list" style="font-size: 24px;"></i>
            </button>
        </div>

        <!-- ════════ GRADIENT HERO HEADER ════════ -->
        <header class="dash-hero animate-fade-in">
            <div class="hero-left">
                <span class="hero-eyebrow <?= $onShift ? '' : 'off' ?>">
                    <span class="island-status-dot"></span>
                    <?= $onShift ? 'On Shift · Live' : 'Off Shift' ?>
                </span>
                <h1><?= htmlspecialchars($greeting) ?>, <span class="accent-name"><?= htmlspecialchars($firstName) ?></span></h1>
                <p class="hero-quote">&ldquo;<?= htmlspecialchars($quote) ?>&rdquo;</p>
                <div class="hero-chips">
                    <a href="live_orders.php" class="hero-chip"><i class="ph-fill ph-bell-ringing"></i> Live Orders</a>
                    <a href="inventory.php" class="hero-chip"><i class="ph-fill ph-package"></i> Inventory</a>
                    <a href="zreport.php" class="hero-chip"><i class="ph-fill ph-receipt"></i> Z-Report</a>
                    <a href="timeclock.php" class="hero-chip"><i class="ph-fill ph-clock"></i> Time Clock</a>
                </div>
            </div>

            <div class="hero-right">
                <div>
                    <div class="hero-clock" id="hero-clock">--:--</div>
                    <div class="hero-date" id="hero-date">&nbsp;</div>
                </div>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="ph-fill ph-play-circle"></i> Open Register
                </a>
            </div>
        </header>

        <!-- ════════ METRICS GRID (4 equal cards) ════════ -->
        <div class="metrics-grid">

            <!-- 1. My Sales Today -->
            <div class="card metric-card animate-fade-in" onclick="ozoneToast('My Takings', 'You have logged $<?= number_format($todaySales, 2) ?> today.', 'success')">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">My Sales Today</div>
                    </div>
                    <div class="metric-icon"><i class="ph-fill ph-currency-dollar"></i></div>
                </div>
                <div>
                    <div class="metric-value" data-count-to="<?= $todaySales ?>" data-prefix="$" data-decimals="2">$<?= number_format($todaySales, 2) ?></div>
                    <div style="margin-top:10px;">
                        <?php if ($trendPercent >= 0): ?>
                            <span class="badge badge-success"><i class="ph ph-trend-up"></i> +<?= number_format($trendPercent, 1) ?>% vs yesterday</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="ph ph-trend-down"></i> <?= number_format($trendPercent, 1) ?>% vs yesterday</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Shift Goal -->
            <div class="card metric-card animate-fade-in">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Shift Goal</div>
                    </div>
                    <div class="goal-ring-wrap">
                        <svg width="56" height="56" class="widget-progress-circle" viewBox="0 0 72 72">
                            <circle class="widget-circle-bg" cx="36" cy="36" r="32" />
                            <circle class="widget-circle-fill" cx="36" cy="36" r="32" />
                        </svg>
                        <span><?= round($goalProgress) ?>%</span>
                    </div>
                </div>
                <div>
                    <div class="metric-value">$<?= number_format($shiftGoal, 0) ?></div>
                    <div style="margin-top:10px;">
                        <span class="badge <?= $goalProgress >= 100 ? 'badge-success' : 'badge-accent' ?>">
                            <?= $goalProgress >= 100 ? 'Goal reached' : '$' . number_format(max(0, $shiftGoal - $todaySales), 0) . ' to go' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 3. Transactions Today -->
            <div class="card metric-card animate-fade-in" onclick="location.href='history.php'">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Transactions</div>
                    </div>
                    <div class="metric-icon info"><i class="ph-fill ph-receipt"></i></div>
                </div>
                <div>
                    <div class="metric-value"><?= $txCount ?></div>
                    <div style="margin-top:10px;">
                        <span class="badge badge-info"><?= $txCount === 1 ? 'sale rung today' : 'sales rung today' ?></span>
                    </div>
                </div>
            </div>

            <!-- 4. Pending Tables -->
            <div class="card metric-card animate-fade-in" onclick="location.href='live_orders.php'">
                <div class="metric-top">
                    <div>
                        <div class="metric-label">Pending Tables</div>
                    </div>
                    <div class="metric-icon <?= $pendingTables > 0 ? 'danger' : '' ?>">
                        <?php if ($pendingTables > 0): ?>
                            <i class="ph-fill ph-bell-ringing"></i>
                        <?php else: ?>
                            <i class="ph-fill ph-check-circle"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="metric-value" style="color: <?= $pendingTables > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"><?= $pendingTables ?></div>
                    <div style="margin-top:10px;">
                        <span class="badge <?= $pendingTables > 0 ? 'badge-danger' : 'badge-success' ?>">
                            <?= $pendingTables > 0 ? 'Awaiting checkout' : 'All tables cleared' ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ════════ SECONDARY FULL-WIDTH ACTIVITY CARD ════════ -->
        <section class="card activity-card animate-fade-in">
            <div class="card-header">
                <h3><i class="ph-fill ph-clock-counter-clockwise"></i> My Recent Transactions</h3>
                <a href="history.php" class="btn btn-secondary btn-sm">View Shift History</a>
            </div>

            <?php if (empty($recentSales)): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="ph-bold ph-database"></i></div>
                    <h3>No transactions yet</h3>
                    <p>Open the register and ring your first sale of the shift — it will appear here.</p>
                    <a href="index.php" class="btn btn-primary btn-sm"><i class="ph-fill ph-play-circle"></i> Open Register</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="recent-sales-list">
                        <thead>
                            <tr>
                                <th>Receipt ID</th>
                                <th>Timestamp</th>
                                <th>Items</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td data-label="Receipt ID" style="font-family: var(--font-display); color: var(--kami-accent); font-weight: 700;">#<?= str_pad((string)$sale['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td data-label="Timestamp"><?= date('M j, g:i A', strtotime($sale['created_at'])) ?></td>
                                    <td data-label="Items"><?= (int)$sale['item_count'] ?> item<?= (int)$sale['item_count'] === 1 ? '' : 's' ?></td>
                                    <td data-label="Total" style="text-align: right; font-weight: 700;">$<?= number_format((float)$sale['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }

        // Live clock + date (client local time)
        (function () {
            const clockEl = document.getElementById('hero-clock');
            const dateEl = document.getElementById('hero-date');
            function tick() {
                const now = new Date();
                let h = now.getHours();
                const m = now.getMinutes().toString().padStart(2, '0');
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                clockEl.innerHTML = h + ':' + m + '<span class="ampm">' + ampm + '</span>';
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
