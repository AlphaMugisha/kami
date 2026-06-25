<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$userName = $_SESSION['full_name'] ?? 'Admin';
$userRole = ucfirst($_SESSION['role'] ?? 'Admin');

// Fetch all officially submitted Z-Reports
$stmt = $pdo->query("
    SELECT s.*, u.full_name as cashier_name 
    FROM shifts s 
    JOIN users u ON s.cashier_id = u.id 
    WHERE s.submitted_at IS NOT NULL 
    ORDER BY s.submitted_at DESC
");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$staff_area = 'admin';
$page_title = 'Audit Reports';
require '../includes/staff_header.php';
?>
<?php include '../includes/preloader.php'; ?>
    <style>
        .shortage-badge { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }
        .balanced-badge { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; }

        /* Desktop Table Styling */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 600px; }
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
               NEW: VERTICALLY STACKED MOBILE CARDS
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
            
            /* Ensure the badges align nicely in the stacked layout */
            .shortage-badge, .balanced-badge {
                white-space: normal !important;
                text-align: left;
                height: auto !important;
                margin-top: 4px;
            }
        }
    </style>

        <h1 class="kami-page-title">Audit Reports</h1>
        <p class="kami-page-sub">Review submitted end-of-day Z-Reports and register discrepancies.</p>

        <div class="card glass animate-fade-in">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                <h3><i class="ph-bold ph-archive"></i> Management Inbox</h3>
                <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($reports) ?> Submitted Audits</div>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Cashier</th>
                            <th>Starting Float</th>
                            <th>Declared Cash</th>
                            <th>Audit Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="5" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
                                    <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                    No Z-Reports submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): 
                                // Calculate the sales for this specific shift to find the expected cash
                                $sales_stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = :id AND created_at >= :start AND created_at <= :end");
                                $sales_stmt->execute(['id' => $report['cashier_id'], 'start' => $report['clock_in'], 'end' => $report['clock_out']]);
                                $shift_sales = (float)$sales_stmt->fetchColumn();
                                
                                $expected = (float)$report['starting_cash'] + $shift_sales;
                                $discrepancy = (float)$report['ending_cash'] - $expected;
                            ?>
                                <tr class="hover-scale">
                                    <td data-label="Date Submitted" style="color: var(--kami-text-muted); font-weight: 600; font-size: 15px;">
                                        <?= date('M j, Y - g:i A', strtotime($report['submitted_at'])) ?>
                                    </td>
                                    <td data-label="Cashier" style="font-weight: 700; font-size: 15px;">
                                        <i class="ph-fill ph-user" style="color: var(--kami-text-dim); margin-right: 4px;"></i> <?= htmlspecialchars($report['cashier_name']) ?>
                                    </td>
                                    <td data-label="Starting Float" style="font-size: 15px;">
                                        $<?= number_format((float)$report['starting_cash'], 2) ?>
                                    </td>
                                    <td data-label="Declared Cash" style="font-weight: 700; color: var(--kami-text); font-size: 16px;">
                                        $<?= number_format((float)$report['ending_cash'], 2) ?>
                                    </td>
                                    <td data-label="Audit Result">
                                        <?php if ($discrepancy === 0.0): ?>
                                            <span class="balanced-badge"><i class="ph-bold ph-scales"></i> Balanced</span>
                                        <?php else: ?>
                                            <span class="shortage-badge">
                                                <i class="ph-bold ph-warning"></i> <?= $discrepancy > 0 ? '+' : '' ?>$<?= number_format($discrepancy, 2) ?>
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
<?php require '../includes/staff_footer.php'; ?>