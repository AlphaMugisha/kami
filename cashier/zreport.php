<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';

$cashier_id = $_SESSION['user_id'];
$cashier_name = $_SESSION['full_name'];
$success_msg = '';

// PROCESS: SUBMIT REPORT TO ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
    if ($shift_id) {
        $stmt = $pdo->prepare("UPDATE shifts SET submitted_at = NOW() WHERE id = :id AND cashier_id = :cid");
        $stmt->execute(['id' => $shift_id, 'cid' => $cashier_id]);
        $success_msg = "Z-Report officially submitted to management.";
    }
}

// 2. FETCH THE LATEST SHIFT FOR THIS CASHIER
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE cashier_id = :id ORDER BY clock_in DESC LIMIT 1");
$stmt->execute(['id' => $cashier_id]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. CALCULATE SHIFT FINANCIALS
$shift_sales = 0.00;
$transaction_count = 0;
$expected_cash = 0.00;
$discrepancy = 0.00;

if ($shift) {
    $start_time = $shift['clock_in'];
    $end_time = $shift['clock_out'] ? $shift['clock_out'] : date('Y-m-d H:i:s');

    $sales_stmt = $pdo->prepare("
        SELECT SUM(total) as total_sales, COUNT(id) as receipt_count 
        FROM sales 
        WHERE cashier_id = :id AND created_at >= :start AND created_at <= :end
    ");
    $sales_stmt->execute(['id' => $cashier_id, 'start' => $start_time, 'end' => $end_time]);
    
    $sales_data = $sales_stmt->fetch(PDO::FETCH_ASSOC);
    $shift_sales = (float)($sales_data['total_sales'] ?? 0);
    $transaction_count = (int)($sales_data['receipt_count'] ?? 0);
    
    $expected_cash = (float)$shift['starting_cash'] + $shift_sales;
    
    if ($shift['status'] === 'closed') {
        $discrepancy = (float)$shift['ending_cash'] - $expected_cash;
    }
}
?>
<?php
$staff_area = 'cashier';
$page_title = 'Daily Z-Report';
require '../includes/staff_header.php';
?>
    <style>
        .report-container { max-width: 600px; margin: 0 auto; }
        .receipt-paper { background: #ffffff; color: #000000; padding: 40px; border-radius: 8px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); font-family: 'Courier New', Courier, monospace; position: relative; }
        .receipt-paper::after { content: ""; position: absolute; bottom: -10px; left: 0; right: 0; height: 10px; background-size: 20px 20px; background-image: linear-gradient(135deg, #ffffff 25%, transparent 25%), linear-gradient(225deg, #ffffff 25%, transparent 25%); background-position: 0 0; }
        .receipt-header { text-align: center; margin-bottom: 24px; border-bottom: 2px dashed #cccccc; padding-bottom: 16px; }
        .receipt-header h2 { font-weight: 900; font-size: 24px; margin-bottom: 4px; letter-spacing: 1px; }
        .receipt-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 15px; font-weight: 600; }
        .receipt-row.divider { border-bottom: 1px dashed #cccccc; padding-bottom: 8px; margin-bottom: 16px; }
        .receipt-row.total { font-size: 18px; font-weight: 900; margin-top: 16px; border-top: 2px dashed #000; padding-top: 16px; }
        .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 800; color: #fff; text-transform: uppercase; }
        .bg-active { background: #10b981; }
        .bg-closed { background: #ef4444; }
        @media print { body * { visibility: hidden; } .receipt-paper, .receipt-paper * { visibility: visible; } .receipt-paper { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; padding: 0; } .receipt-paper::after { display: none; } .no-print { display: none !important; } }
    </style>

        <div class="no-print">
            <h1 class="kami-page-title">End of Day Z-Report</h1>
            <p class="kami-page-sub">Financial reconciliation and shift drawer auditing.</p>
        </div>

        <div class="report-container animate-fade-in">
            <?php if (!$shift): ?>
                <div class="card glass" style="text-align: center; padding: 48px;">
                    <i class="ph-bold ph-receipt" style="font-size: 48px; color: var(--kami-text-dim); margin-bottom: 16px;"></i>
                    <h2>No Shift Data Found</h2>
                    <p class="text-dim">You have not started a shift yet. Please clock in to begin tracking financials.</p>
                </div>
            <?php else: ?>
                
                <div class="receipt-paper">
                    <?php if ($shift['submitted_at']): ?>
                        <div class="no-print" style="position: absolute; top: 20px; right: -20px; transform: rotate(15deg); border: 4px solid #10b981; color: #10b981; padding: 8px 16px; font-size: 20px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; opacity: 0.8;">
                            SUBMITTED
                        </div>
                    <?php endif; ?>

                    <div class="receipt-header">
                        <h2>OZONE LIQUOR</h2>
                        <p>Daily Z-Report Audit</p>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">Printed: <?= date('Y-m-d H:i:s') ?></p>
                    </div>

                    <div class="receipt-row">
                        <span>Cashier ID:</span>
                        <span>EMP-<?= str_pad((string)$cashier_id, 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="receipt-row divider">
                        <span>Shift Status:</span>
                        <span class="badge-status <?= $shift['status'] === 'active' ? 'bg-active' : 'bg-closed' ?>"><?= htmlspecialchars($shift['status']) ?></span>
                    </div>

                    <div class="receipt-row">
                        <span>Starting Float:</span>
                        <span>$<?= number_format((float)$shift['starting_cash'], 2) ?></span>
                    </div>
                    <div class="receipt-row">
                        <span>Gross Cash Sales:</span>
                        <span>+$<?= number_format($shift_sales, 2) ?></span>
                    </div>
                    
                    <div class="receipt-row total">
                        <span>EXPECTED IN DRAWER:</span>
                        <span>$<?= number_format($expected_cash, 2) ?></span>
                    </div>

                    <?php if ($shift['status'] === 'closed'): ?>
                        <div class="receipt-row" style="margin-top: 16px;">
                            <span>Actual Declared Cash:</span>
                            <span>$<?= number_format((float)$shift['ending_cash'], 2) ?></span>
                        </div>
                        <div class="receipt-row" style="margin-top: 8px; color: <?= $discrepancy === 0.0 ? '#10b981' : '#ef4444' ?>;">
                            <span>OVER/SHORT:</span>
                            <span><?= $discrepancy > 0 ? '+' : '' ?>$<?= number_format($discrepancy, 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="no-print" style="margin-top: 40px; display: flex; gap: 16px;">
                    <button onclick="window.print()" class="btn btn-secondary btn-block" style="padding: 16px; font-size: 16px;">
                        <i class="ph-bold ph-printer"></i> Print Audit
                    </button>
                    
                    <?php if ($shift['status'] === 'closed' && !$shift['submitted_at']): ?>
                        <form action="zreport.php" method="POST" style="flex: 1;" onsubmit="return confirm('Send this final audit to management?');">
                            <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                            <button type="submit" name="submit_report" class="btn btn-primary btn-block" style="padding: 16px; font-size: 16px;">
                                <i class="ph-bold ph-paper-plane-tilt"></i> Submit to Admin
                            </button>
                        </form>
                    <?php elseif ($shift['submitted_at']): ?>
                        <button class="btn btn-block" disabled style="flex: 1; padding: 16px; font-size: 16px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); cursor: not-allowed;">
                            <i class="ph-bold ph-check-circle"></i> Sent to Management
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php if ($success_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => { if(window.triggerDynamicIsland) window.triggerDynamicIsland('Audit Sent', '<?= htmlspecialchars($success_msg) ?>', 'success'); });
        </script>
    <?php endif; ?>
<?php require '../includes/staff_footer.php'; ?>