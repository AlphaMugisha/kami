<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$userName = $_SESSION['full_name'] ?? 'Admin';

// Branch filter — admins see every branch combined by default; picking one
// narrows every stat below to just that location.
$branches = $pdo->query("SELECT id, name, is_main FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$filter_branch_id = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT) ?: 0; // 0 = all branches

// ==========================================
// 2. LIVE DASHBOARD ANALYTICS ENGINE
// ==========================================

// A. Today's revenue
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE DATE(created_at) = CURDATE() AND (:bid1 = 0 OR branch_id = :bid2)");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$todaySales = (float)($stmt->fetchColumn() ?: 0);

// B. Yesterday's revenue (trend)
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND (:bid1 = 0 OR branch_id = :bid2)");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$yesterdaySales = (float)($stmt->fetchColumn() ?: 0);
$trendPercent = 0;
if ($yesterdaySales > 0)      { $trendPercent = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100; }
elseif ($todaySales > 0)      { $trendPercent = 100; }

// C. Items sold today
$stmt = $pdo->prepare(
    "SELECT SUM(si.qty) FROM sale_items si JOIN sales s ON si.sale_id = s.id
      WHERE DATE(s.created_at) = CURDATE() AND (:bid1 = 0 OR s.branch_id = :bid2)"
);
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$itemsSold = (int)($stmt->fetchColumn() ?: 0);

// D. Transactions today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND (:bid1 = 0 OR branch_id = :bid2)");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$txToday = (int)$stmt->fetchColumn();

// E. Low stock
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock <= 5 AND (:bid1 = 0 OR branch_id = :bid2)");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$lowStockCount = (int)$stmt->fetchColumn();

// F. Recent transactions feed (last 8)
$stmt = $pdo->prepare("
    SELECT s.id, s.total, s.created_at, u.full_name
    FROM sales s
    LEFT JOIN users u ON s.cashier_id = u.id
    WHERE (:bid1 = 0 OR s.branch_id = :bid2)
    ORDER BY s.created_at DESC
    LIMIT 8
");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$feed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// G. Greeting + rotating quote
date_default_timezone_set('Africa/Kigali');
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$nameParts = preg_split('/\s+/', trim($userName)) ?: ['Admin'];
$firstName = $nameParts[0] ?: 'Admin';
$quotes = [
    'Excellence is a craft poured one measure at a time.',
    'Great service is the finest spirit you can offer.',
    'Precision today, prosperity tomorrow.',
    'Every bottle tells a story — make today\'s a good one.',
    'Attention to detail is the house signature.',
];
$quote = $quotes[(int)date('z') % count($quotes)];

// 3. SHELL
$staff_area  = 'admin';
$page_title  = 'Dashboard';
require '../includes/staff_header.php';
?>

<?php include '../includes/preloader.php'; ?>

<div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;" class="animate-fade-in">
    <label class="form-label" for="dashBranchFilter" style="margin:0;">Showing</label>
    <select id="dashBranchFilter" class="form-select" style="width:auto; min-width:200px;" onchange="location.href='index.php'+(this.value>0?'?branch='+this.value:'')">
        <option value="0" <?= $filter_branch_id === 0 ? 'selected' : '' ?>>All Branches (combined)</option>
        <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $filter_branch_id === (int)$b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?><?= $b['is_main'] ? ' (Main)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ════════ HERO BANNER ════════ -->
<section class="kami-hero animate-fade-in">
    <div class="hero-left">
        <div class="hero-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?> &#128075;</div>
        <p class="hero-tagline">Here's what's happening across Ozone today.</p>
        <span class="hero-quote"><i class="ph-fill ph-sparkle"></i> <?= htmlspecialchars($quote) ?></span>
        <a href="../cashier/index.php" class="hero-cta"><i class="ph-fill ph-play-circle"></i> Start Selling <i class="ph-bold ph-arrow-right"></i></a>
    </div>
    <div class="hero-right">
        <div class="clock-card">
            <div class="live-clock">--:--</div>
            <span class="clock-date">&nbsp;</span>
        </div>
    </div>
</section>

<!-- ════════ STAT CARDS ROW (4) ════════ -->
<div class="kami-stat-row">

    <div class="kami-stat-card animate-fade-in">
        <div class="stat-ic success"><i class="ph-fill ph-currency-dollar"></i></div>
        <div class="stat-num" data-count-to="<?= $todaySales ?>" data-prefix="$" data-decimals="2">$<?= number_format($todaySales, 2) ?></div>
        <div class="stat-name">Today's Sales</div>
        <div class="stat-detail">
            <?php if ($trendPercent >= 0): ?>
                <span style="color: var(--kami-success);">&#9650; +<?= number_format($trendPercent, 1) ?>%</span> vs yesterday
            <?php else: ?>
                <span style="color: var(--kami-danger);">&#9660; <?= number_format($trendPercent, 1) ?>%</span> vs yesterday
            <?php endif; ?>
        </div>
    </div>

    <div class="kami-stat-card animate-fade-in">
        <div class="stat-ic info"><i class="ph-fill ph-wine"></i></div>
        <div class="stat-num" data-count-to="<?= $itemsSold ?>"><?= $itemsSold ?></div>
        <div class="stat-name">Items Sold Today</div>
        <div class="stat-detail">Units rung across the bar</div>
    </div>

    <div class="kami-stat-card animate-fade-in">
        <div class="stat-ic"><i class="ph-fill ph-receipt"></i></div>
        <div class="stat-num" data-count-to="<?= $txToday ?>"><?= $txToday ?></div>
        <div class="stat-name">Transactions</div>
        <div class="stat-detail"><?= $txToday === 1 ? '1 sale' : $txToday.' sales' ?> logged today</div>
    </div>

    <a href="inventory.php" class="kami-stat-card animate-fade-in" style="text-decoration:none; color:inherit;">
        <div class="stat-ic <?= $lowStockCount > 0 ? 'danger' : 'success' ?>"><i class="ph-fill <?= $lowStockCount > 0 ? 'ph-warning' : 'ph-check-circle' ?>"></i></div>
        <div class="stat-num" style="color: <?= $lowStockCount > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"><?= $lowStockCount ?></div>
        <div class="stat-name">Low Stock</div>
        <div class="stat-detail"><?= $lowStockCount > 0 ? 'item'.($lowStockCount === 1 ? '' : 's').' need reorder' : 'all lines healthy' ?></div>
    </a>

</div>

<!-- ════════ ACTIVITY FEED ════════ -->
<section class="kami-feed-card animate-fade-in">
    <div class="feed-head">
        <h3><i class="ph-fill ph-pulse"></i> Recent Transactions</h3>
        <a href="reports.php" class="badge badge-accent" style="text-decoration:none;">View reports</a>
    </div>

    <?php if (empty($feed)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="ph ph-receipt"></i></div>
            <h3>No sales yet</h3>
            <p>Transactions will appear here as cashiers ring them up.</p>
        </div>
    <?php else: ?>
        <?php foreach ($feed as $row):
            $when = strtotime($row['created_at']);
            $isToday = date('Y-m-d', $when) === date('Y-m-d'); ?>
            <div class="feed-item">
                <div class="feed-ic"><i class="ph-fill ph-currency-dollar"></i></div>
                <div class="feed-meta">
                    <div class="feed-title">Sale #<?= (int)$row['id'] ?></div>
                    <div class="feed-sub"><?= htmlspecialchars($row['full_name'] ?? 'Unknown cashier') ?></div>
                </div>
                <div class="feed-amount">$<?= number_format((float)$row['total'], 2) ?></div>
                <div class="feed-time"><?= $isToday ? date('g:i A', $when) : date('M j, g:i A', $when) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require '../includes/staff_footer.php'; ?>
