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

// D. Active Cashiers
$stmt = $pdo->query("SELECT COUNT(DISTINCT cashier_id) FROM sales WHERE DATE(created_at) = CURDATE()");
$activeCashiers = (int)$stmt->fetchColumn();

// E. Recent Transactions
$stmt = $pdo->query("
    SELECT s.id, s.total, s.created_at, u.full_name as cashier_name 
    FROM sales s 
    LEFT JOIN users u ON s.cashier_id = u.id 
    ORDER BY s.created_at DESC LIMIT 5
");
$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Goal Calculation
$dailyGoal = 1000.00;
$goalProgress = min(100, ($todaySales / $dailyGoal) * 100);
$ringOffset = 226 - (226 * ($goalProgress / 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ozone Admin | Dashboard Overview</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        /* --- CORE WIDGET STYLES --- */
        .widget-progress-circle { transform: rotate(-90deg); }
        .widget-circle-bg { fill: none; stroke: rgba(255, 255, 255, 0.03); stroke-width: 6; }
        .widget-circle-fill { fill: none; stroke: var(--kami-accent); stroke-width: 6; stroke-dasharray: 226; stroke-dashoffset: <?= $ringOffset ?>; stroke-linecap: round; filter: drop-shadow(0 0 6px var(--kami-accent)); transition: stroke-dashoffset 1s ease-in-out; }

        .pulse-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: var(--kami-success); box-shadow: 0 0 8px var(--kami-success); animation: pulse-glow 1.5s infinite alternate ease-in-out; }
        @keyframes pulse-glow { from { transform: scale(0.9); opacity: 0.5; } to { transform: scale(1.2); opacity: 1; } }
        @keyframes bellShake { 0%, 100% { transform: rotate(0); } 15% { transform: rotate(12deg); } 30% { transform: rotate(-12deg); } 45% { transform: rotate(8deg); } 60% { transform: rotate(-8deg); } 75% { transform: rotate(4deg); } 85% { transform: rotate(-4deg); } }

        /* --- DESKTOP FIRST GRID & CARDS --- */
        .bento-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; }
        
        .stat-card {
            grid-column: span 4;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            min-height: 160px;
            padding: 20px;
            cursor: pointer;
        }

        .stat-info { display: flex; flex-direction: column; gap: 4px; z-index: 2; }
        .stat-visual { position: absolute; right: 20px; bottom: 20px; display: flex; align-items: center; justify-content: center; }

        .widget-accent-ring { width: 72px; height: 72px; position: relative; display: flex; align-items: center; justify-content: center; }
        .alert-bell { width: 48px; height: 48px; border-radius: 50%; background: var(--kami-danger-bg); display: flex; align-items: center; justify-content: center; border: 1px solid var(--kami-danger-border); }
        .cashier-bars { display: flex; gap: 4px; height: 24px; }
        .cashier-bar { width: 12px; height: 100%; border-radius: 3px; background: var(--kami-success); box-shadow: 0 0 8px var(--kami-success); }

        .bento-main { grid-column: span 8; grid-row: span 2; }
        .bento-side { grid-column: span 4; }
        
        .shortcut-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .shortcut-btn { background: var(--kami-surface-3); border: 1px solid var(--kami-border); border-radius: var(--kami-radius-md); padding: 20px 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--kami-text); cursor: pointer; transition: all var(--kami-transition); text-decoration: none; font-weight: 700; font-size: 13px; text-align: center; }
        .shortcut-btn i { font-size: 24px; color: var(--kami-accent); transition: transform var(--kami-transition); }
        .shortcut-btn:hover { border-color: var(--kami-accent-border); background: var(--kami-accent-bg); box-shadow: var(--kami-glow); }
        .shortcut-btn:hover i { transform: scale(1.1) rotate(5deg); }

        .table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: var(--kami-radius-sm); }
        .recent-sales-list { width: 100%; border-collapse: collapse; min-width: 500px; }
        .recent-sales-list th { text-align: left; padding: 12px 16px; color: var(--kami-text-muted); font-size: 12px; font-weight: 600; border-bottom: 1px solid var(--kami-border); }
        .recent-sales-list td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.02); white-space: nowrap; }

        /* --- MOBILE MENU ELEMENTS (HIDDEN ON DESKTOP) --- */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        /* --- RESPONSIVE BREAKPOINTS --- */

        /* Tablet (Pro/Air) */
        @media (max-width: 1024px) {
            .stat-card { grid-column: span 6; }
            .stat-card:last-of-type { grid-column: span 12; } 
            .bento-main { grid-column: span 12; grid-row: auto; }
            .bento-side { grid-column: span 12; }
            .shortcut-grid { grid-template-columns: repeat(4, 1fr); }
        }

        /* Mobile (Phones) */
        @media (max-width: 768px) {
            
            /* TRUE EDGE-TO-EDGE MOBILE FULLSCREEN */
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; margin: 0 !important; }

            /* Remove the card-like floating effect from main content */
            .main-content {
                margin: 0 !important; 
                padding: 16px !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                min-height: 100vh;
            }
            
            .card, .glass {
                border: none !important;
                background: transparent;
                padding: 0;
            }
            .card-header { padding: 0 0 16px 0 !important; }

            .shortcut-btn {
                border: 1px solid var(--kami-border) !important; /* Keep border on buttons to distinguish them */
            }

            /* Sidebar Slide Control */
            .sidebar, aside {
                position: fixed !important;
                top: 0;
                left: -300px !important;
                width: 280px !important;
                height: 100vh !important;
                z-index: 1000 !important;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 4px 0 24px rgba(0,0,0,0.5);
            }
            
            body.sidebar-open .sidebar, body.sidebar-open aside {
                left: 0 !important;
            }

            /* Blurred background overlay when sidebar is open */
            .sidebar-overlay {
                display: block;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(3px);
                z-index: 999;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            /* Header Adjustments for Menu Button */
            .page-header-container {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 24px;
            }
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--kami-surface-3);
                border: none !important; 
                border-radius: var(--kami-radius-sm);
                width: 44px;
                height: 44px;
                color: var(--kami-text);
                cursor: pointer;
                flex-shrink: 0;
            }
            .page-header { margin-bottom: 0 !important; }
            .page-header p { display: none; }

            .bento-grid { display: flex; flex-direction: column; gap: 32px; }
            
            .stat-card {
                min-height: auto;
                flex-direction: row;
                align-items: center;
                padding: 16px;
                gap: 16px;
                background: var(--kami-surface-2); /* Give back a slight background for contrast on edge-to-edge */
                border: 1px solid var(--kami-border) !important;
                border-radius: var(--kami-radius-md);
            }
            .stat-info { flex: 1; }
            .stat-visual { position: relative; right: 0; bottom: 0; }
            
            .widget-accent-ring { width: 60px; height: 60px; }
            .widget-progress-circle { width: 60px; height: 60px; }
            .widget-circle-bg, .widget-circle-fill { cx: 30; cy: 30; r: 26; }
            .alert-bell { width: 40px; height: 40px; border: none !important; } 
            
            .shortcut-grid { grid-template-columns: repeat(2, 1fr); }
            .shortcut-btn { padding: 24px 12px; }

            /* =========================================
               TRUE MOBILE TABLE REWRITE (CARDS)
               ========================================= */
            
            .recent-sales-list thead { display: none; }
            .recent-sales-list, .recent-sales-list tbody, .recent-sales-list tr, .recent-sales-list td { display: block; width: 100%; min-width: 0; }
            
            .recent-sales-list tr {
                margin-bottom: 16px;
                background: var(--kami-surface-2);
                border: 1px solid var(--kami-border) !important;
                border-radius: var(--kami-radius-md);
                padding: 16px;
            }

            .recent-sales-list td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0 !important;
                border-bottom: 1px solid rgba(255,255,255,0.04) !important;
                text-align: right;
            }
            .recent-sales-list td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }

            .recent-sales-list td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--kami-text-muted);
                font-size: 13px;
                text-align: left;
                padding-right: 16px;
            }
        }
    </style>
</head>
<body class="app-layout">
    
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        
        <div class="page-header-container">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="ph ph-list" style="font-size: 24px;"></i>
            </button>
            <header class="page-header">
                <h1>System Overview</h1>
                <p>Real-time server synchronization & operational telemetry</p>
            </header>
        </div>

        <div class="bento-grid animate-fade-in">
            
            <div class="card stat-card glass" onclick="window.triggerDynamicIsland('Financial Directory', 'Total revenue generated: $<?= number_format($todaySales, 2) ?>', 'success')">
                <div class="stat-info">
                    <div class="stat-label">
                        <span>Today's Sales</span>
                        <i class="ph-bold ph-currency-dollar" style="color: var(--kami-accent);"></i>
                    </div>
                    <div class="stat-value" data-count-to="<?= $todaySales ?>" data-prefix="$" data-decimals="2">$<?= number_format($todaySales, 2) ?></div>
                    <div>
                        <?php if ($trendPercent >= 0): ?>
                            <div class="badge badge-success"><i class="ph ph-trend-up"></i> +<?= number_format($trendPercent, 1) ?>% vs Yesterday</div>
                        <?php else: ?>
                            <div class="badge badge-danger"><i class="ph ph-trend-down"></i> <?= number_format($trendPercent, 1) ?>% vs Yesterday</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="stat-visual">
                    <div class="widget-accent-ring">
                        <svg width="72" height="72" class="widget-progress-circle" viewBox="0 0 72 72">
                            <circle class="widget-circle-bg" cx="36" cy="36" r="32" />
                            <circle class="widget-circle-fill" cx="36" cy="36" r="32" />
                        </svg>
                        <span style="position: absolute; font-size: 12px; font-weight: 800; color: var(--kami-text);"><?= round($goalProgress) ?>%</span>
                    </div>
                </div>
            </div>

            <div class="card stat-card glass" onclick="location.href='inventory.php'">
                <div class="stat-info">
                    <div class="stat-label">
                        <span>Stock Warnings</span>
                        <i class="ph-bold ph-warning-octagon" style="color: <?= $lowStockCount > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"></i>
                    </div>
                    <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--kami-danger)' : 'var(--kami-success)' ?>;"><?= $lowStockCount ?> Items</div>
                    <div>
                        <?php if ($lowStockCount > 0): ?>
                            <div class="badge badge-danger">Reorder Required</div>
                        <?php else: ?>
                            <div class="badge badge-success">Stock Optimal</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="stat-visual">
                    <?php if ($lowStockCount > 0): ?>
                        <div class="alert-bell">
                            <i class="ph-bold ph-bell" style="font-size: 20px; color: var(--kami-danger); animation: bellShake 2s infinite ease-in-out;"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card stat-card glass" onclick="window.triggerDynamicIsland('Active Registry', 'Cashiers processing sales today: <?= $activeCashiers ?>', 'info')">
                <div class="stat-info">
                    <div class="stat-label">
                        <span>Operational Cashiers</span>
                        <i class="ph-bold ph-desktop" style="color: var(--kami-info);"></i>
                    </div>
                    <div class="stat-value"><?= $activeCashiers ?> Today</div>
                    <div>
                        <div class="badge badge-info" style="display: inline-flex; align-items: center; gap: 6px;">
                            <?php if ($activeCashiers > 0): ?><span class="pulse-indicator"></span><?php endif; ?>
                            <span>Clocked In</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-visual">
                    <div class="cashier-bars">
                        <?php for ($i = 0; $i < max(1, $activeCashiers); $i++): ?>
                            <div class="cashier-bar"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="card glass bento-main">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <h3 style="margin: 0;"><i class="ph-fill ph-receipt"></i> Recent Transaction Logs</h3>
                    <a href="pos.php" class="btn btn-secondary btn-sm">Open Register</a>
                </div>
                
                <?php if (empty($recentSales)): ?>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; opacity: 0.85;">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--kami-surface-3); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; border: none;">
                            <i class="ph-bold ph-database" style="font-size: 28px; color: var(--kami-text-dim);"></i>
                        </div>
                        <p class="fw-600" style="font-size: 15px; color: var(--kami-text); text-align: center;">No Transaction Records</p>
                        <p class="text-dim" style="font-size: 12px; margin-top: 4px; text-align: center;">Open Point of Sale register billing to capture real-time cashier activities.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="recent-sales-list">
                            <thead>
                                <tr>
                                    <th>Receipt ID</th>
                                    <th>Timestamp</th>
                                    <th>Cashier</th>
                                    <th style="text-align: right;">Total Logged</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td data-label="Receipt ID" style="font-family: monospace; color: var(--kami-accent);">#<?= str_pad((string)$sale['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                        <td data-label="Timestamp"><?= date('M j, g:i A', strtotime($sale['created_at'])) ?></td>
                                        <td data-label="Cashier"><?= htmlspecialchars($sale['cashier_name'] ?? 'System') ?></td>
                                        <td data-label="Total Logged" style="font-weight: 700;">$<?= number_format((float)$sale['total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card glass bento-side">
                <div class="card-header">
                    <h3 style="margin: 0;"><i class="ph ph-lightning"></i> Command Shortcuts</h3>
                </div>
                
                <div class="shortcut-grid" style="margin-top: 16px;">
                    <a href="pos.php" class="shortcut-btn">
                        <i class="ph ph-shopping-bag"></i>
                        <span>Register POS</span>
                    </a>
                    <a href="inventory.php" class="shortcut-btn">
                        <i class="ph ph-plus-circle"></i>
                        <span>New Product</span>
                    </a>
                    <a href="users.php" class="shortcut-btn">
                        <i class="ph ph-user-plus"></i>
                        <span>Staff Roster</span>
                    </a>
                    <div class="shortcut-btn" onclick="window.triggerDynamicIsland('Printer Check', 'Hardware synced: printer online.', 'success')">
                        <i class="ph ph-printer"></i>
                        <span>Test Printer</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }
    </script>
</body>
</html>