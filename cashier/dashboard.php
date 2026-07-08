<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$cashierId = (int)$_SESSION['user_id'];
$userName  = $_SESSION['full_name'] ?? 'Cashier';
$branchId  = (int)($_SESSION['branch_id'] ?? 1);

// ==========================================
// 2. PERSONAL SHIFT ANALYTICS ENGINE (scoped to the branch this shift is at)
// ==========================================

// A. My revenue today
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = ? AND branch_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$cashierId, $branchId]);
$todaySales = (float)($stmt->fetchColumn() ?: 0);

// B. My revenue yesterday (trend basis)
$stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = ? AND branch_id = ? AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY");
$stmt->execute([$cashierId, $branchId]);
$yesterdaySales = (float)($stmt->fetchColumn() ?: 0);

$trendPercent = 0;
if ($yesterdaySales > 0)      { $trendPercent = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100; }
elseif ($todaySales > 0)      { $trendPercent = 100; }

// C. Transactions rung today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE cashier_id = ? AND branch_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$cashierId, $branchId]);
$txCount = (int)$stmt->fetchColumn();

// D. Current shift status
$stmt = $pdo->prepare("SELECT clock_in, starting_cash FROM shifts WHERE cashier_id = ? AND branch_id = ? AND status = 'active' ORDER BY clock_in DESC LIMIT 1");
$stmt->execute([$cashierId, $branchId]);
$activeShift = $stmt->fetch(PDO::FETCH_ASSOC);
$onShift = (bool)$activeShift;

// Shift duration (if on shift)
$shiftDuration = '—';
if ($onShift) {
    $mins = max(0, (int)((time() - strtotime($activeShift['clock_in'])) / 60));
    $shiftDuration = floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
}

// E. Pending tables awaiting checkout — Main Branch only (lounge/QR-ordering
// feature; Second Branch is a plain walk-in shop and never has these).
$mainBranchId = (int)($pdo->query("SELECT id FROM branches WHERE is_main = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 1);
$showLiveOrdering = $branchId === $mainBranchId;
$pendingTables = 0;
if ($showLiveOrdering) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_orders WHERE status IN ('pending','preparing') AND branch_id = :bid");
    $stmt->execute(['bid' => $mainBranchId]);
    $pendingTables = (int)$stmt->fetchColumn();
}

// F2. Refills awaiting confirmation at this branch — the cashier taps "Mark
// as Received" once the delivery physically arrives, which is what actually
// makes it sellable stock (see receive_transfer() in stock_functions.php).
$stmt = $pdo->prepare("
    SELECT t.id, t.quantity, t.transferred_at, p.name AS product_name, tl.name AS to_name, fb.name AS from_branch
      FROM stock_transfers t
      JOIN products p ON t.product_id = p.id
      JOIN stock_locations tl ON t.to_location_id = tl.id
      JOIN stock_locations fl ON t.from_location_id = fl.id
      JOIN branches fb ON fl.branch_id = fb.id
     WHERE tl.branch_id = :bid AND t.status = 'pending'
     ORDER BY t.transferred_at ASC
");
$stmt->execute(['bid' => $branchId]);
$pendingRefills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// F3. Recently confirmed refills, for a quick "yep that arrived" history.
$stmt = $pdo->prepare("
    SELECT t.quantity, t.received_at, p.name AS product_name, tl.name AS to_name
      FROM stock_transfers t
      JOIN products p ON t.product_id = p.id
      JOIN stock_locations tl ON t.to_location_id = tl.id
     WHERE tl.branch_id = :bid AND t.status = 'received' AND t.received_at IS NOT NULL
     ORDER BY t.received_at DESC
     LIMIT 5
");
$stmt->execute(['bid' => $branchId]);
$recentRefills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// F. My recent sales (feed)
$stmt = $pdo->prepare("SELECT id, total, created_at FROM sales WHERE cashier_id = ? AND branch_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$cashierId, $branchId]);
$feed = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Shift goal calculation
$shiftGoal = 500.00;
$goalProgress = min(100, ($todaySales / $shiftGoal) * 100);

// G. Greeting + rotating quote
date_default_timezone_set('Africa/Kigali');
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$nameParts = preg_split('/\s+/', trim($userName)) ?: ['Cashier'];
$firstName = $nameParts[0] ?: 'Cashier';
$quotes = [
    'Every sale is a story well served.',
    'A warm welcome is the best garnish.',
    'Quick hands, kind smile, perfect pour.',
    'Make the register sing, one ticket at a time.',
    'Great shifts are built on small wins.',
];
$quote = $quotes[(int)date('z') % count($quotes)];

// 3. SHELL
$staff_area = 'cashier';
$page_title = 'My Shift';
if (!$showLiveOrdering) {
    // No Pending Tables card at this branch — reflow the row to 3 even columns
    // instead of leaving a blank gap from the fixed 4-column grid.
    $page_styles = '<style>@media (min-width: 769px) { .kami-stat-row { grid-template-columns: repeat(3, 1fr); } }</style>';
}
require '../includes/staff_header.php';
?>

<?php include '../includes/preloader.php'; ?>

<!-- ════════ HERO BANNER ════════ -->
<section class="kami-hero animate-fade-in">
    <div class="hero-left">
        <div class="hero-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?> &#128075;</div>
        <p class="hero-tagline"><?= $onShift ? 'You are on shift — ready when you are.' : 'Clock in to start your shift.' ?></p>
        <span class="hero-quote"><i class="ph-fill ph-sparkle"></i> <?= htmlspecialchars($quote) ?></span>
        <a href="<?= $onShift ? 'index.php' : 'timeclock.php' ?>" class="hero-cta">
            <i class="ph-fill ph-play-circle"></i> <?= $onShift ? 'Open Register' : 'Clock In' ?> <i class="ph-bold ph-arrow-right"></i>
        </a>
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
        <div class="stat-name">My Sales Today</div>
        <div class="stat-detail">
            <?php if ($trendPercent >= 0): ?>
                <span style="color: var(--kami-success);">&#9650; +<?= number_format($trendPercent, 1) ?>%</span> vs yesterday
            <?php else: ?>
                <span style="color: var(--kami-danger);">&#9660; <?= number_format($trendPercent, 1) ?>%</span> vs yesterday
            <?php endif; ?>
        </div>
    </div>

    <div class="kami-stat-card animate-fade-in">
        <div class="stat-ic <?= $onShift ? 'info' : 'warning' ?>"><i class="ph-fill ph-clock"></i></div>
        <div class="stat-num"><?= htmlspecialchars($shiftDuration) ?></div>
        <div class="stat-name">Shift Duration</div>
        <div class="stat-detail"><?= $onShift ? 'On the clock since '.date('g:i A', strtotime($activeShift['clock_in'])) : 'Not clocked in' ?></div>
    </div>

    <a href="history.php" class="kami-stat-card animate-fade-in" style="text-decoration:none; color:inherit;">
        <div class="stat-ic"><i class="ph-fill ph-receipt"></i></div>
        <div class="stat-num" data-count-to="<?= $txCount ?>"><?= $txCount ?></div>
        <div class="stat-name">Transactions</div>
        <div class="stat-detail"><?= $txCount === 1 ? '1 sale' : $txCount.' sales' ?> rung today</div>
    </a>

    <?php if ($showLiveOrdering): ?>
    <a href="live_orders.php" class="kami-stat-card animate-fade-in" style="text-decoration:none; color:inherit;">
        <div class="stat-ic <?= $pendingTables > 0 ? 'danger' : 'success' ?>"><i class="ph-fill <?= $pendingTables > 0 ? 'ph-bell-ringing' : 'ph-check-circle' ?>"></i></div>
        <div class="stat-num" style="color: <?= $pendingTables > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"><?= $pendingTables ?></div>
        <div class="stat-name">Pending Tables</div>
        <div class="stat-detail"><?= $pendingTables > 0 ? 'awaiting checkout' : 'all tables cleared' ?></div>
    </a>
    <?php endif; ?>

</div>

<?php if (!empty($pendingRefills)): ?>
<!-- ════════ REFILLS AWAITING CONFIRMATION ════════ -->
<section class="kami-feed-card animate-fade-in" style="margin-bottom: 20px; border: 1px solid var(--kami-warning-border);" id="pendingRefillsCard">
    <div class="feed-head">
        <h3><i class="ph-fill ph-truck"></i> Refill On The Way — Confirm It Arrived</h3>
    </div>
    <?php foreach ($pendingRefills as $r): ?>
        <div class="feed-item" id="pending-refill-<?= (int)$r['id'] ?>">
            <div class="feed-ic" style="color: var(--kami-warning);"><i class="ph-fill ph-truck"></i></div>
            <div class="feed-meta">
                <div class="feed-title"><?= (int)$r['quantity'] ?> × <?= htmlspecialchars($r['product_name']) ?></div>
                <div class="feed-sub">from <?= htmlspecialchars($r['from_branch']) ?>, sent <?= date('M j, g:i A', strtotime($r['transferred_at'])) ?></div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="markRefillReceived(<?= (int)$r['id'] ?>)">
                <i class="ph-bold ph-check-circle"></i> Mark Received
            </button>
        </div>
    <?php endforeach; ?>
</section>
<script>
    async function markRefillReceived(transferId) {
        try {
            const fd = new FormData();
            fd.append('transfer_id', transferId);
            const res = await fetch('../api/receive_transfer.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('pending-refill-' + transferId);
                if (row) row.remove();
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Refill Confirmed', data.message, 'success');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Could Not Confirm', data.message || 'Unknown error', 'error');
            }
        } catch (err) {
            if (window.triggerDynamicIsland) window.triggerDynamicIsland('Network Error', 'Could not reach the server.', 'error');
        }
    }
</script>
<?php endif; ?>

<?php if (!empty($recentRefills)): ?>
<!-- ════════ RECENTLY CONFIRMED REFILLS ════════ -->
<section class="kami-feed-card animate-fade-in" style="margin-bottom: 20px;">
    <div class="feed-head">
        <h3><i class="ph-fill ph-package"></i> Recently Refilled</h3>
    </div>
    <?php foreach ($recentRefills as $r):
        $isRecent = (time() - strtotime($r['received_at'])) < 7200; // last 2 hours
    ?>
        <div class="feed-item">
            <div class="feed-ic" style="<?= $isRecent ? 'color: var(--kami-success);' : '' ?>"><i class="ph-fill ph-package"></i></div>
            <div class="feed-meta">
                <div class="feed-title"><?= (int)$r['quantity'] ?> × <?= htmlspecialchars($r['product_name']) ?></div>
                <div class="feed-sub">into <?= htmlspecialchars($r['to_name']) ?><?= $isRecent ? ' — just now' : '' ?></div>
            </div>
            <div class="feed-time"><?= date('M j, g:i A', strtotime($r['received_at'])) ?></div>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ════════ ACTIVITY FEED ════════ -->
<section class="kami-feed-card animate-fade-in">
    <div class="feed-head">
        <h3><i class="ph-fill ph-pulse"></i> My Recent Sales</h3>
        <a href="history.php" class="badge badge-accent" style="text-decoration:none;">Shift history</a>
    </div>

    <?php if (empty($feed)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="ph ph-receipt"></i></div>
            <h3>No sales yet</h3>
            <p>Your rung-up sales will appear here during your shift.</p>
        </div>
    <?php else: ?>
        <?php foreach ($feed as $row):
            $when = strtotime($row['created_at']);
            $isToday = date('Y-m-d', $when) === date('Y-m-d'); ?>
            <div class="feed-item">
                <div class="feed-ic"><i class="ph-fill ph-currency-dollar"></i></div>
                <div class="feed-meta">
                    <div class="feed-title">Sale #<?= (int)$row['id'] ?></div>
                    <div class="feed-sub"><?= $isToday ? 'Today' : date('M j', $when) ?></div>
                </div>
                <div class="feed-amount">$<?= number_format((float)$row['total'], 2) ?></div>
                <div class="feed-time"><?= date('g:i A', $when) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require '../includes/staff_footer.php'; ?>
