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

// Branch filter — all branches combined by default.
$branches = $pdo->query("SELECT id, name, is_main FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$filter_branch_id = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT) ?: 0; // 0 = all branches

/* ================================================================
   DAILY CONSOLIDATED REPORT — only meaningful for one specific
   branch on one specific day (this is what "Close Shop" links to):
   every cashier's shift that day combined into one summary, plus
   any stock refills the branch received.
   ================================================================ */
$daily_report = null;
if ($filter_branch_id > 0) {
    $report_date = trim((string)($_GET['report_date'] ?? '')) ?: date('Y-m-d');
    if (strtotime($report_date) === false) $report_date = date('Y-m-d');

    $branch_name = 'Branch';
    foreach ($branches as $b) { if ((int)$b['id'] === $filter_branch_id) { $branch_name = $b['name']; break; } }

    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name AS cashier_name,
               (SELECT SUM(total) FROM sales
                  WHERE cashier_id = s.cashier_id AND branch_id = s.branch_id
                    AND created_at >= s.clock_in AND created_at <= IFNULL(s.clock_out, NOW())) AS shift_sales
          FROM shifts s
          JOIN users u ON s.cashier_id = u.id
         WHERE s.branch_id = :bid AND DATE(s.clock_in) = :date
         ORDER BY s.clock_in ASC
    ");
    $stmt->execute(['bid' => $filter_branch_id, 'date' => $report_date]);
    $day_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $day_total_sales = 0.0;
    $day_total_discrepancy = 0.0;
    $day_open_shifts = 0;
    foreach ($day_shifts as &$ds) {
        $ds['shift_sales'] = (float)($ds['shift_sales'] ?? 0);
        $day_total_sales += $ds['shift_sales'];
        if ($ds['status'] === 'closed') {
            $expected = (float)$ds['starting_cash'] + $ds['shift_sales'];
            $ds['discrepancy'] = (float)$ds['ending_cash'] - $expected;
            $day_total_discrepancy += $ds['discrepancy'];
        } else {
            $ds['discrepancy'] = null;
            $day_open_shifts++;
        }
    }
    unset($ds);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE branch_id = :bid AND DATE(created_at) = :date");
    $stmt->execute(['bid' => $filter_branch_id, 'date' => $report_date]);
    $day_tx_count = (int)$stmt->fetchColumn();

    // Refills actually confirmed at this branch this day (received_at, not
    // sent-at — a report is about what really landed on the shelf that day).
    $stmt = $pdo->prepare("
        SELECT t.*, p.name AS product_name, fl.name AS from_name, fb.name AS from_branch, tl.name AS to_name
          FROM stock_transfers t
          JOIN products p ON t.product_id = p.id
          JOIN stock_locations tl ON t.to_location_id = tl.id
          JOIN stock_locations fl ON t.from_location_id = fl.id
          JOIN branches fb ON fl.branch_id = fb.id
         WHERE tl.branch_id = :bid AND t.status = 'received' AND DATE(t.received_at) = :date
         ORDER BY t.received_at DESC
    ");
    $stmt->execute(['bid' => $filter_branch_id, 'date' => $report_date]);
    $day_refills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Refills still in transit to this branch (sent but not yet confirmed) —
    // regardless of date, so nothing gets lost track of.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM stock_transfers t
          JOIN stock_locations tl ON t.to_location_id = tl.id
         WHERE tl.branch_id = :bid AND t.status = 'pending'
    ");
    $stmt->execute(['bid' => $filter_branch_id]);
    $day_pending_refills = (int)$stmt->fetchColumn();

    $daily_report = [
        'branch_name'  => $branch_name,
        'pending_refills' => $day_pending_refills,
        'date'         => $report_date,
        'shifts'       => $day_shifts,
        'total_sales'  => $day_total_sales,
        'tx_count'     => $day_tx_count,
        'discrepancy'  => $day_total_discrepancy,
        'open_shifts'  => $day_open_shifts,
        'refills'      => $day_refills,
    ];
}

// Fetch all officially submitted Z-Reports
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as cashier_name, b.name as branch_name
    FROM shifts s
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN branches b ON b.id = s.branch_id
    WHERE s.submitted_at IS NOT NULL
      AND (:bid1 = 0 OR s.branch_id = :bid2)
    ORDER BY s.submitted_at DESC
");
$stmt->execute(['bid1' => $filter_branch_id, 'bid2' => $filter_branch_id]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================================================
   PROFIT REPORT (batch-traced) — uses sale_items.buying_price
   captured at sale time via FIFO. Filterable by date range + branch.
   ================================================================ */
$pr_from = trim((string)($_GET['pr_from'] ?? ''));
$pr_to   = trim((string)($_GET['pr_to'] ?? ''));

$pwhere  = ['si.buying_price IS NOT NULL', '(:pbid1 = 0 OR s.branch_id = :pbid2)'];
$pparams = ['pbid1' => $filter_branch_id, 'pbid2' => $filter_branch_id];
if ($pr_from !== '' && strtotime($pr_from) !== false) {
    $pwhere[] = 's.created_at >= :pf';
    $pparams['pf'] = date('Y-m-d 00:00:00', strtotime($pr_from));
}
if ($pr_to !== '' && strtotime($pr_to) !== false) {
    $pwhere[] = 's.created_at <= :pt';
    $pparams['pt'] = date('Y-m-d 23:59:59', strtotime($pr_to));
}
$pwhere_sql = 'WHERE ' . implode(' AND ', $pwhere);

// Per-product rollup
$profit_stmt = $pdo->prepare("
    SELECT p.id, p.name,
           SUM(si.qty)                          AS units_sold,
           SUM(si.qty * si.buying_price)        AS total_cost,
           SUM(si.qty * si.price)               AS total_revenue,
           SUM(si.qty * (si.price - si.buying_price)) AS gross_profit
      FROM sale_items si
      JOIN products p ON si.product_id = p.id
      JOIN sales    s ON si.sale_id    = s.id
      $pwhere_sql
     GROUP BY p.id
     ORDER BY gross_profit DESC
");
$profit_stmt->execute($pparams);
$profit_rows = $profit_stmt->fetchAll(PDO::FETCH_ASSOC);

// Per-batch breakdown (grouped by product in PHP for expandable detail rows)
$batch_stmt = $pdo->prepare("
    SELECT si.product_id, si.batch_id,
           SUM(si.qty)                          AS units,
           SUM(si.qty * si.buying_price)        AS cost,
           SUM(si.qty * si.price)               AS revenue,
           SUM(si.qty * (si.price - si.buying_price)) AS profit
      FROM sale_items si
      JOIN sales s ON si.sale_id = s.id
      $pwhere_sql
     GROUP BY si.product_id, si.batch_id
     ORDER BY si.batch_id ASC
");
$batch_stmt->execute($pparams);
$batch_detail = [];
foreach ($batch_stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $batch_detail[(int)$b['product_id']][] = $b;
}

// Summary totals
$sum_units = 0; $sum_cost = 0.0; $sum_rev = 0.0; $sum_profit = 0.0;
foreach ($profit_rows as $r) {
    $sum_units  += (int)$r['units_sold'];
    $sum_cost   += (float)$r['total_cost'];
    $sum_rev    += (float)$r['total_revenue'];
    $sum_profit += (float)$r['gross_profit'];
}
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
        <p class="kami-page-sub">Review submitted end-of-day Z-Reports, batch-traced profit, and register discrepancies.</p>

        <?php if ($daily_report): ?>
        <!-- ===================== DAILY CONSOLIDATED REPORT ===================== -->
        <div class="card glass animate-fade-in" style="margin-bottom: 24px; border-color: var(--kami-accent-border);">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 16px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3><i class="ph-bold ph-calendar-check"></i> Daily Report — <?= htmlspecialchars($daily_report['branch_name']) ?></h3>
                    <div style="color:var(--kami-text-dim); font-size:13px; margin-top:4px;"><?= date('l, F j, Y', strtotime($daily_report['date'])) ?></div>
                </div>
                <form method="GET" action="reports.php" style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="branch" value="<?= $filter_branch_id ?>">
                    <input type="date" name="report_date" class="form-input" value="<?= htmlspecialchars($daily_report['date']) ?>" onchange="this.form.submit()">
                </form>
            </div>

            <div class="summary-badges" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
                <span class="badge badge-success">$<?= number_format($daily_report['total_sales'], 2) ?> total sales</span>
                <span class="badge badge-info"><?= $daily_report['tx_count'] ?> transactions</span>
                <span class="badge <?= $daily_report['discrepancy'] == 0 ? 'badge-success' : 'badge-danger' ?>">
                    <?= $daily_report['discrepancy'] >= 0 ? '+' : '' ?>$<?= number_format($daily_report['discrepancy'], 2) ?> combined over/short
                </span>
                <?php if ($daily_report['open_shifts'] > 0): ?>
                    <span class="badge badge-danger"><?= $daily_report['open_shifts'] ?> shift(s) still open</span>
                <?php endif; ?>
                <span class="badge badge-info"><?= count($daily_report['refills']) ?> refill(s) received</span>
                <?php if ($daily_report['pending_refills'] > 0): ?>
                    <span class="badge badge-danger"><?= $daily_report['pending_refills'] ?> refill(s) still in transit</span>
                <?php endif; ?>
            </div>

            <div class="table-responsive" style="margin-bottom:20px;">
                <table class="data-table">
                    <thead>
                        <tr><th>Cashier</th><th>Clock In / Out</th><th>Status</th><th>Sales</th><th>Over/Short</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily_report['shifts'])): ?>
                            <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--kami-text-dim);">No shifts recorded at this branch on this day.</td></tr>
                        <?php else: ?>
                            <?php foreach ($daily_report['shifts'] as $ds): ?>
                                <tr>
                                    <td data-label="Cashier" style="font-weight:700;"><?= htmlspecialchars($ds['cashier_name']) ?></td>
                                    <td data-label="Clock In / Out" class="row-secondary" style="color:var(--kami-text-muted);">
                                        <?= date('g:i A', strtotime($ds['clock_in'])) ?> – <?= $ds['clock_out'] ? date('g:i A', strtotime($ds['clock_out'])) : 'Active' ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($ds['status'] === 'active'): ?>
                                            <span class="badge badge-success">On Duty</span>
                                        <?php elseif ($ds['submitted_at']): ?>
                                            <span class="badge badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Closed (Unsubmitted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Sales" style="color:var(--kami-accent); font-weight:700;">$<?= number_format($ds['shift_sales'], 2) ?></td>
                                    <td data-label="Over/Short">
                                        <?php if ($ds['discrepancy'] === null): ?>
                                            <span style="color:var(--kami-text-dim);">In progress</span>
                                        <?php else: ?>
                                            <span style="color:<?= $ds['discrepancy'] == 0 ? '#10b981' : '#ef4444' ?>; font-weight:700;">
                                                <?= $ds['discrepancy'] >= 0 ? '+' : '' ?>$<?= number_format($ds['discrepancy'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($daily_report['refills'])): ?>
                <h4 style="font-size:13px; text-transform:uppercase; letter-spacing:0.05em; color:var(--kami-text-muted); margin-bottom:12px;">Refills Received</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Confirmed At</th><th>Product</th><th>From</th><th>Into</th><th>Qty</th></tr></thead>
                        <tbody>
                            <?php foreach ($daily_report['refills'] as $r): ?>
                                <tr>
                                    <td data-label="Confirmed At" style="color:var(--kami-text-muted); white-space:nowrap;"><?= date('g:i A', strtotime($r['received_at'])) ?></td>
                                    <td data-label="Product" style="font-weight:700;"><?= htmlspecialchars($r['product_name']) ?></td>
                                    <td data-label="From" class="row-secondary"><?= htmlspecialchars($r['from_name']) ?> (<?= htmlspecialchars($r['from_branch']) ?>)</td>
                                    <td data-label="Into" class="row-secondary"><?= htmlspecialchars($r['to_name']) ?></td>
                                    <td data-label="Qty" style="font-weight:800; color:var(--kami-accent);"><?= (int)$r['quantity'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===================== PROFIT REPORT ===================== -->
        <div class="card glass animate-fade-in" style="margin-bottom: 24px;">
            <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                <h3><i class="ph-bold ph-trend-up"></i> Profit Report <span style="color:var(--kami-text-dim);font-weight:500;font-size:14px;">(per-batch cost vs. revenue)</span></h3>
            </div>

            <form method="GET" action="reports.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:20px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Branch</label>
                    <select name="branch" class="form-select">
                        <option value="0" <?= $filter_branch_id === 0 ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= $filter_branch_id === (int)$b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?><?= $b['is_main'] ? ' (Main)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">From</label>
                    <input type="date" name="pr_from" class="form-input" value="<?= htmlspecialchars($pr_from) ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">To</label>
                    <input type="date" name="pr_to" class="form-input" value="<?= htmlspecialchars($pr_to) ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="ph-bold ph-funnel"></i> Filter</button>
                <a href="reports.php" class="btn" style="background:var(--kami-surface-3);color:var(--kami-text);">Reset</a>
            </form>
            <p style="color:var(--kami-text-dim); font-size:12.5px; margin:-8px 0 20px;">This branch filter also applies to the Management Inbox below.</p>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Units Sold</th>
                            <th>Total Cost</th>
                            <th>Total Revenue</th>
                            <th>Gross Profit</th>
                            <th>Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($profit_rows)): ?>
                            <tr>
                                <td colspan="6" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
                                    <i class="ph ph-chart-line" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                    No batch-traced sales in this period yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($profit_rows as $r):
                                $rev = (float)$r['total_revenue'];
                                $cost = (float)$r['total_cost'];
                                $profit = (float)$r['gross_profit'];
                                $pmargin = $cost > 0 ? ($profit / $cost) * 100 : null;
                                $details = $batch_detail[(int)$r['id']] ?? [];
                            ?>
                                <tr class="hover-scale" style="cursor:pointer;" onclick="toggleBatchRows(<?= (int)$r['id'] ?>)">
                                    <td data-label="Product" style="font-weight:700;">
                                        <i class="ph ph-caret-right" id="caret-<?= (int)$r['id'] ?>" style="transition:transform .2s; margin-right:6px; color:var(--kami-text-dim);"></i>
                                        <?= htmlspecialchars($r['name']) ?>
                                    </td>
                                    <td data-label="Units Sold"><?= (int)$r['units_sold'] ?></td>
                                    <td data-label="Total Cost">$<?= number_format($cost, 2) ?></td>
                                    <td data-label="Total Revenue">$<?= number_format($rev, 2) ?></td>
                                    <td data-label="Gross Profit" style="font-weight:800; color: <?= $profit >= 0 ? '#10b981' : '#ef4444' ?>;">
                                        <?= ($profit >= 0 ? '+' : '-') ?>$<?= number_format(abs($profit), 2) ?>
                                    </td>
                                    <td data-label="Margin %" style="font-weight:800; color: <?= ($pmargin ?? 0) >= 0 ? '#10b981' : '#ef4444' ?>;">
                                        <?= $pmargin === null ? 'n/a' : (($pmargin >= 0 ? '+' : '') . number_format($pmargin, 1) . '%') ?>
                                    </td>
                                </tr>
                                <?php foreach ($details as $d):
                                    $d_profit = (float)$d['profit'];
                                ?>
                                <tr class="batch-detail-row detail-of-<?= (int)$r['id'] ?>" style="display:none; background:rgba(255,255,255,0.02);">
                                    <td data-label="Batch" style="padding-left:34px; color:var(--kami-text-muted);">
                                        <?= $d['batch_id'] !== null ? 'Batch #' . (int)$d['batch_id'] : 'Legacy / untracked' ?>
                                    </td>
                                    <td data-label="Units Sold"><?= (int)$d['units'] ?></td>
                                    <td data-label="Total Cost">$<?= number_format((float)$d['cost'], 2) ?></td>
                                    <td data-label="Total Revenue">$<?= number_format((float)$d['revenue'], 2) ?></td>
                                    <td data-label="Gross Profit" style="color: <?= $d_profit >= 0 ? '#10b981' : '#ef4444' ?>;">
                                        <?= ($d_profit >= 0 ? '+' : '-') ?>$<?= number_format(abs($d_profit), 2) ?>
                                    </td>
                                    <td data-label="Margin %"></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <tr style="border-top: 2px solid var(--kami-border);">
                                <td data-label="Total" style="font-weight:800;">TOTALS</td>
                                <td data-label="Units Sold" style="font-weight:800;"><?= $sum_units ?></td>
                                <td data-label="Total Cost" style="font-weight:800;">$<?= number_format($sum_cost, 2) ?></td>
                                <td data-label="Total Revenue" style="font-weight:800;">$<?= number_format($sum_rev, 2) ?></td>
                                <td data-label="Gross Profit" style="font-weight:800; color: <?= $sum_profit >= 0 ? '#10b981' : '#ef4444' ?>;">
                                    <?= ($sum_profit >= 0 ? '+' : '-') ?>$<?= number_format(abs($sum_profit), 2) ?>
                                </td>
                                <td data-label="Margin %" style="font-weight:800; color: <?= ($sum_cost > 0 ? ($sum_profit/$sum_cost) : 0) >= 0 ? '#10b981' : '#ef4444' ?>;">
                                    <?= $sum_cost > 0 ? (($sum_profit >= 0 ? '+' : '') . number_format(($sum_profit / $sum_cost) * 100, 1) . '%') : 'n/a' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function toggleBatchRows(pid) {
            var rows = document.querySelectorAll('.detail-of-' + pid);
            var caret = document.getElementById('caret-' + pid);
            var show = rows.length && rows[0].style.display === 'none';
            rows.forEach(function (r) { r.style.display = show ? '' : 'none'; });
            if (caret) caret.style.transform = show ? 'rotate(90deg)' : 'rotate(0deg)';
        }
        </script>

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
                            <th>Branch</th>
                            <th>Starting Float</th>
                            <th>Declared Cash</th>
                            <th>Audit Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="6" data-label="Status" style="text-align:center; padding:32px; color: var(--kami-text-dim);">
                                    <i class="ph ph-warning-circle" style="font-size: 32px; margin-bottom: 8px; display: block;"></i>
                                    No Z-Reports submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report):
                                // Calculate the sales for this specific shift (same branch) to find the expected cash
                                $sales_stmt = $pdo->prepare("SELECT SUM(total) FROM sales WHERE cashier_id = :id AND branch_id = :bid AND created_at >= :start AND created_at <= :end");
                                $sales_stmt->execute(['id' => $report['cashier_id'], 'bid' => $report['branch_id'], 'start' => $report['clock_in'], 'end' => $report['clock_out']]);
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
                                    <td data-label="Branch" class="row-secondary"><?= htmlspecialchars($report['branch_name'] ?? '—') ?></td>
                                    <td data-label="Starting Float" class="row-secondary" style="font-size: 15px;">
                                        $<?= number_format((float)$report['starting_cash'], 2) ?>
                                    </td>
                                    <td data-label="Declared Cash" class="row-secondary" style="font-weight: 700; color: var(--kami-text); font-size: 16px;">
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