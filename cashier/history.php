<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$cashier_id = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

$stmt = $pdo->prepare("
    SELECT s.*, b.name AS branch_name,
    (SELECT SUM(total) FROM sales
       WHERE cashier_id = s.cashier_id AND branch_id = s.branch_id
         AND created_at >= s.clock_in AND created_at <= IFNULL(s.clock_out, NOW())) as shift_sales
    FROM shifts s
    LEFT JOIN branches b ON b.id = s.branch_id
    WHERE s.cashier_id = :id
    ORDER BY s.clock_in DESC
");
$stmt->execute(['id' => $cashier_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$staff_area = 'cashier';
$page_title = 'Shift History';
require '../includes/staff_header.php';
?>
    <style>
        /* Mobile card-conversion for the shift-history table (same technique as admin/inventory.php) */
        @media (max-width: 768px) {
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table tr {
                margin-bottom: 16px;
                background: var(--kami-surface-2);
                border: 1px solid var(--kami-border) !important;
                border-radius: var(--kami-radius-md);
                padding: 16px;
            }
            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0 !important;
                border-bottom: 1px solid rgba(255,255,255,0.04) !important;
                text-align: right;
            }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
            .data-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--kami-text-muted);
                font-size: 13px;
                text-align: left;
                padding-right: 16px;
            }
        }
    </style>

        <h1 class="kami-page-title">My Shift History</h1>
        <p class="kami-page-sub">Personal audit ledger of all past operational shifts.</p>

        <div class="card glass animate-fade-in responsive-table-wrapper">
            <div class="card-header borderless-header">
                <h3><i class="ph-bold ph-clock-counter-clockwise"></i> Historical Logs</h3>
                <div class="badge badge-info"><?= count($shifts) ?> Total Shifts</div>
            </div>
            
            <div class="data-table-container">
                <table class="data-table responsive-table">
                    <thead>
                        <tr>
                            <th>Date / Clock In</th>
                            <th>Status</th>
                            <th>Branch</th>
                            <th>Starting Float</th>
                            <th>Shift Sales</th>
                            <th>Final Expected</th>
                            <th>Over/Short</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr><td colspan="7" class="empty-state">No shifts found in your history.</td></tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): 
                                $sales = (float)($s['shift_sales'] ?? 0);
                                $expected = (float)$s['starting_cash'] + $sales;
                                $discrepancy = ($s['status'] === 'closed') ? ((float)$s['ending_cash'] - $expected) : 0;
                            ?>
                                <tr class="hover-scale">
                                    <td class="fw-600" data-label="Date / Clock In">
                                        <?= date('M j, Y', strtotime($s['clock_in'])) ?><br>
                                        <span class="time-muted"><?= date('h:i A', strtotime($s['clock_in'])) ?> - <?= $s['clock_out'] ? date('h:i A', strtotime($s['clock_out'])) : 'Active' ?></span>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active Now</span>
                                        <?php elseif ($s['submitted_at']): ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Closed (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="row-secondary" data-label="Branch"><?= htmlspecialchars($s['branch_name'] ?? '—') ?></td>
                                    <td class="text-muted row-secondary" data-label="Starting Float">$<?= number_format((float)$s['starting_cash'], 2) ?></td>
                                    <td class="text-accent row-secondary" data-label="Shift Sales">+$<?= number_format($sales, 2) ?></td>
                                    <td class="fw-700 row-secondary" data-label="Final Expected">$<?= number_format($expected, 2) ?></td>
                                    <td data-label="Over/Short">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="status-pending">Pending</span>
                                        <?php elseif ($discrepancy === 0.0): ?>
                                            <span class="status-balanced"><i class="ph-bold ph-check"></i> Balanced</span>
                                        <?php else: ?>
                                            <span class="<?= $discrepancy > 0 ? 'status-positive' : 'status-negative' ?>">
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