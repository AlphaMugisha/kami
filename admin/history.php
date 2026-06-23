<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
if (file_exists('../includes/preloader.php')) { include '../includes/preloader.php'; }

// ADVANCED SQL: Fetch ALL shifts from ALL cashiers
$stmt = $pdo->query("
    SELECT s.*, u.full_name as cashier_name,
    (SELECT SUM(total) FROM sales WHERE cashier_id = s.cashier_id AND created_at >= s.clock_in AND created_at <= IFNULL(s.clock_out, NOW())) as shift_sales
    FROM shifts s 
    JOIN users u ON s.cashier_id = u.id
    ORDER BY s.clock_in DESC
");
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ozone Admin | Global Shift History</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        /* Force strict box-sizing universally to stop padding from breaking the grid */
        *, *::before, *::after { box-sizing: border-box; }

        /* Desktop Table Styling */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 650px; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        /* --- MOBILE MENU ELEMENTS --- */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        /* --- MOBILE UX BREAKPOINT --- */
        @media (max-width: 768px) {
            
            /* Aggressive anti-overflow measures */
            body, html { margin: 0 !important; padding: 0 !important; overflow-x: hidden !important; width: 100% !important; }
            .app-layout { padding: 0 !important; margin: 0 !important; overflow-x: hidden !important; width: 100% !important; }
            
            .main-content { 
                margin: 0 !important; 
                padding: 16px !important; 
                border: none !important; 
                border-radius: 0 !important; 
                box-shadow: none !important; 
                min-height: 100vh; 
                width: 100% !important;
                max-width: 100vw !important;
            }
            
            .card, .glass { border: none !important; background: transparent; padding: 0;}
            .card-header { padding: 0 0 16px 0 !important; }

            /* Sidebar Controls */
            .sidebar, aside { position: fixed !important; top: 0; left: -300px !important; width: 280px !important; height: 100vh !important; z-index: 1000 !important; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; box-shadow: 4px 0 24px rgba(0,0,0,0.5); }
            body.sidebar-open .sidebar, body.sidebar-open aside { left: 0 !important; }
            .sidebar-overlay { display: block; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(3px); z-index: 999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
            body.sidebar-open .sidebar-overlay { opacity: 1; pointer-events: auto; }

            /* Header Adjustments */
            .page-header-container { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; width: 100%; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; background: var(--kami-surface-3); border-radius: var(--kami-radius-sm); width: 44px; height: 44px; color: var(--kami-text); cursor: pointer; flex-shrink: 0; border: none; }
            .page-header { margin-bottom: 0 !important; }
            .page-header p { display: none; }

            /* =========================================
               VERTICALLY STACKED MOBILE CARDS
               ========================================= */
            
            /* Kill the horizontal scroll wrapper on mobile */
            .table-responsive { overflow: hidden !important; width: 100% !important; }
            
            /* Hide the Desktop Header completely */
            .data-table thead { display: none !important; }
            
            /* Convert table elements to block to break the grid */
            .data-table, .data-table tbody, .data-table tr { 
                display: block !important; 
                width: 100% !important; 
            }
            
            /* Turn every Row into a standalone Card */
            .data-table tr {
                margin-bottom: 16px !important;
                background: var(--kami-surface-2) !important;
                border: 1px solid var(--kami-border) !important;
                border-radius: var(--kami-radius-md) !important;
                padding: 16px !important;
            }

            /* Stack the label ON TOP of the value, never side-by-side */
            .data-table td {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                padding: 10px 0 !important;
                border-bottom: 1px solid rgba(255,255,255,0.04) !important;
                text-align: left !important;
                width: 100% !important;
                white-space: normal !important; 
                word-wrap: break-word !important; 
            }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }

            /* Inject the Column Title from HTML data-label */
            .data-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--kami-text-dim);
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 6px;
                display: block;
            }

            /* Fix badge margins in stacked layout */
            .badge { margin-top: 4px; }
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
                <h1>Global Shift History</h1>
                <p>Master ledger of all employee shifts across the entire system</p>
            </header>
        </div>

        <div class="card glass animate-fade-in">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                <h3><i class="ph-bold ph-books"></i> Master Audit Trail</h3>
                <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($shifts) ?> Total Records</div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Shift Date</th>
                            <th>Cashier</th>
                            <th>Status</th>
                            <th>Starting Float</th>
                            <th>Shift Sales</th>
                            <th>Over/Short</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="6" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
                                    <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                    No system shifts recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): 
                                $sales = (float)($s['shift_sales'] ?? 0);
                                $expected = (float)$s['starting_cash'] + $sales;
                                $discrepancy = ($s['status'] === 'closed') ? ((float)$s['ending_cash'] - $expected) : 0;
                            ?>
                                <tr class="hover-scale">
                                    <td data-label="Shift Date" style="font-weight: 600; font-size: 15px;">
                                        <?= date('M j, Y', strtotime($s['clock_in'])) ?><br>
                                        <span style="font-size: 12px; color: var(--kami-text-muted); font-weight: 500; display: inline-block; margin-top: 2px;">
                                            <?= date('h:i A', strtotime($s['clock_in'])) ?> - <?= $s['clock_out'] ? date('h:i A', strtotime($s['clock_out'])) : 'Active' ?>
                                        </span>
                                    </td>
                                    <td data-label="Cashier" style="font-weight: 700; font-size: 15px;">
                                        <i class="ph-fill ph-user" style="color: var(--kami-text-dim); margin-right: 4px;"></i> <?= htmlspecialchars($s['cashier_name']) ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="badge badge-success">On Duty</span>
                                        <?php elseif ($s['submitted_at']): ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Closed (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Starting Float" style="color: var(--kami-text-muted); font-size: 15px;">
                                        $<?= number_format((float)$s['starting_cash'], 2) ?>
                                    </td>
                                    <td data-label="Shift Sales" style="color: var(--kami-accent); font-weight: 700; font-size: 15px;">
                                        +$<?= number_format($sales, 2) ?>
                                    </td>
                                    <td data-label="Over/Short">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span style="color: var(--kami-text-dim); font-size: 13px; font-weight: 600;">In Progress</span>
                                        <?php elseif ($discrepancy === 0.0): ?>
                                            <span style="color: #10b981; font-weight: 700; font-size: 14px;"><i class="ph-bold ph-check"></i> Balanced</span>
                                        <?php else: ?>
                                            <span style="color: <?= $discrepancy > 0 ? '#10b981' : '#ef4444' ?>; font-weight: 700; font-size: 15px;">
                                                <?= $discrepancy > 0 ? '+' : '' ?>$<?= number_format($discrepancy, 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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