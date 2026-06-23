<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
if (file_exists('../includes/preloader.php')) { include '../includes/preloader.php'; }

$cashier_id = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

$stmt = $pdo->prepare("
    SELECT s.*, 
    (SELECT SUM(total) FROM sales WHERE cashier_id = s.cashier_id AND created_at >= s.clock_in AND created_at <= IFNULL(s.clock_out, NOW())) as shift_sales
    FROM shifts s 
    WHERE s.cashier_id = :id 
    ORDER BY s.clock_in DESC
");
$stmt->execute(['id' => $cashier_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal | Shift History</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
</head>
<body class="app-layout">
    <?php include '../includes/cashier_sidebar.php'; ?>
    
    <main class="main-content">
        <header class="page-header">
            <h1>My Shift History</h1>
            <p>Personal audit ledger of all past operational shifts</p>
        </header>

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
                            <th>Starting Float</th>
                            <th>Shift Sales</th>
                            <th>Final Expected</th>
                            <th>Over/Short</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr><td colspan="6" class="empty-state">No shifts found in your history.</td></tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): 
                                $sales = (float)($s['shift_sales'] ?? 0);
                                $expected = (float)$s['starting_cash'] + $sales;
                                $discrepancy = ($s['status'] === 'closed') ? ((float)$s['ending_cash'] - $expected) : 0;
                            ?>
                                <tr class="hover-scale">
                                    <td class="fw-600">
                                        <?= date('M j, Y', strtotime($s['clock_in'])) ?><br>
                                        <span class="time-muted"><?= date('h:i A', strtotime($s['clock_in'])) ?> - <?= $s['clock_out'] ? date('h:i A', strtotime($s['clock_out'])) : 'Active' ?></span>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active Now</span>
                                        <?php elseif ($s['submitted_at']): ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Closed (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">$<?= number_format((float)$s['starting_cash'], 2) ?></td>
                                    <td class="text-accent">+$<?= number_format($sales, 2) ?></td>
                                    <td class="fw-700">$<?= number_format($expected, 2) ?></td>
                                    <td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>