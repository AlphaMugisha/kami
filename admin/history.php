<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

// Branch filter — all branches combined by default.
$branches = $pdo->query("SELECT id, name, is_main FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$filter_branch_id = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT) ?: 0; // 0 = all branches

// ADVANCED SQL: Fetch ALL shifts from ALL cashiers (optionally scoped to one branch)
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as cashier_name, b.name as branch_name,
    (SELECT SUM(total) FROM sales
       WHERE cashier_id = s.cashier_id AND branch_id = s.branch_id
         AND created_at >= s.clock_in AND created_at <= IFNULL(s.clock_out, NOW())) as shift_sales
    FROM shifts s
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN branches b ON b.id = s.branch_id
    WHERE (:bid1 = 0 OR s.branch_id = :bid2)
    ORDER BY s.clock_in DESC
");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$staff_area = 'admin';
$page_title = 'Shift History';
require '../includes/staff_header.php';
?>
<?php include '../includes/preloader.php'; ?>
    <style>
        /* Force strict box-sizing universally to stop padding from breaking the grid */
        *, *::before, *::after { box-sizing: border-box; }

        /* Desktop Table Styling */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 650px; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); }

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

            /* Header Adjustments */
            .page-header-container { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; width: 100%; }
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

        <h1 class="kami-page-title">Global Shift History</h1>
        <p class="kami-page-sub">Master ledger of all employee shifts across the entire system.</p>

        <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;" class="animate-fade-in">
            <label class="form-label" for="histBranchFilter" style="margin:0;">Branch</label>
            <select id="histBranchFilter" class="form-select" style="width:auto; min-width:200px;" onchange="location.href='history.php'+(this.value>0?'?branch='+this.value:'')">
                <option value="0" <?= $filter_branch_id === 0 ? 'selected' : '' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $filter_branch_id === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?><?= $b['is_main'] ? ' (Main)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Starting Float</th>
                            <th>Shift Sales</th>
                            <th>Over/Short</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="7" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
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
                                    <td data-label="Branch" class="row-secondary"><?= htmlspecialchars($s['branch_name'] ?? '—') ?></td>
                                    <td data-label="Status">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="badge badge-success">On Duty</span>
                                        <?php elseif ($s['submitted_at']): ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Closed (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Starting Float" class="row-secondary" style="color: var(--kami-text-muted); font-size: 15px;">
                                        $<?= number_format((float)$s['starting_cash'], 2) ?>
                                    </td>
                                    <td data-label="Shift Sales" class="row-secondary" style="color: var(--kami-accent); font-weight: 700; font-size: 15px;">
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
                                    <td class="row-expand-cell">
                                        <button type="button" class="row-expand-btn"><i class="ph-bold ph-caret-down"></i> Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php require '../includes/staff_footer.php'; ?>