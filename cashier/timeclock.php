<?php
declare(strict_types=1);
session_start();

// Security: Only logged-in cashiers allowed
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
if (file_exists('../includes/preloader.php')) { include '../includes/preloader.php'; }

$cashier_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// PROCESS: CLOCK IN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_in'])) {
    $starting_cash = filter_input(INPUT_POST, 'starting_cash', FILTER_VALIDATE_FLOAT);
    if ($starting_cash !== false && $starting_cash >= 0) {
        $stmt = $pdo->prepare("INSERT INTO shifts (cashier_id, starting_cash) VALUES (:id, :cash)");
        $stmt->execute(['id' => $cashier_id, 'cash' => $starting_cash]);
        $success_msg = "Shift started successfully. Register unlocked.";
    } else {
        $error_msg = "Please enter a valid starting cash amount.";
    }
}

// PROCESS: CLOCK OUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clock_out'])) {
    $ending_cash = filter_input(INPUT_POST, 'ending_cash', FILTER_VALIDATE_FLOAT);
    $shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
    
    if ($ending_cash !== false && $shift_id) {
        $stmt = $pdo->prepare("UPDATE shifts SET ending_cash = :cash, clock_out = NOW(), status = 'closed' WHERE id = :id AND cashier_id = :cid");
        $stmt->execute(['cash' => $ending_cash, 'id' => $shift_id, 'cid' => $cashier_id]);
        $success_msg = "Shift closed successfully. Good work today!";
    }
}

// CHECK CURRENT SHIFT STATUS
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE cashier_id = :id AND status = 'active' ORDER BY clock_in DESC LIMIT 1");
$stmt->execute(['id' => $cashier_id]);
$active_shift = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal | Time Clock</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        .clock-container { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 70vh; }
        .digital-clock { font-family: 'Outfit', monospace; font-size: 84px; font-weight: 900; color: var(--kami-text); letter-spacing: -2px; margin-bottom: 8px; text-shadow: 0 0 40px rgba(255,255,255,0.1); }
        .date-display { color: var(--kami-text-muted); font-size: 18px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 48px; }
        
        .action-card { background: var(--kami-surface-2); border: 1px solid var(--kami-border); border-radius: 24px; padding: 40px; width: 100%; max-width: 480px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); text-align: center; }
        .input-massive { width: 100%; background: rgba(0,0,0,0.3); border: 2px solid var(--kami-border); color: var(--kami-accent); font-size: 32px; font-weight: 800; text-align: center; padding: 20px; border-radius: 16px; margin-bottom: 24px; outline: none; }
        .input-massive:focus { border-color: var(--kami-accent); box-shadow: 0 0 20px rgba(217, 119, 6, 0.2); }
    </style>
</head>
<body class="app-layout">
    <?php include '../includes/cashier_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="clock-container animate-fade-in">
            <div class="digital-clock" id="live-clock">00:00:00</div>
            <div class="date-display" id="live-date">Loading Date...</div>

            <div class="action-card">
                <?php if (!$active_shift): ?>
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px auto;">
                        <i class="ph-fill ph-play-circle"></i>
                    </div>
                    <h2 style="margin-bottom: 8px;">Start Your Shift</h2>
                    <p style="color: var(--kami-text-muted); margin-bottom: 24px; font-size: 14px;">Enter the starting cash amount currently in your register drawer.</p>
                    
                    <form action="timeclock.php" method="POST">
                        <label style="display:block; text-align:left; margin-bottom:8px; color: var(--kami-text-muted); font-weight:700;">Starting Drawer Float ($)</label>
                        <input type="number" step="0.01" name="starting_cash" class="input-massive" required placeholder="0.00" autofocus>
                        <button type="submit" name="clock_in" class="btn btn-primary btn-block" style="padding: 18px; font-size: 16px; background: #10b981; border-color: #059669;">
                            <i class="ph-bold ph-check-circle"></i> Clock In & Unlock Register
                        </button>
                    </form>

                <?php else: ?>
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px auto;">
                        <i class="ph-fill ph-stop-circle"></i>
                    </div>
                    <h2 style="margin-bottom: 8px;">Active Shift Logged</h2>
                    <p style="color: var(--kami-text-muted); margin-bottom: 24px; font-size: 14px;">You clocked in at <strong><?= date('h:i A', strtotime($active_shift['clock_in'])) ?></strong>.</p>
                    
                    <form action="timeclock.php" method="POST" onsubmit="return confirm('Are you sure you want to close out your drawer for the day?');">
                        <input type="hidden" name="shift_id" value="<?= $active_shift['id'] ?>">
                        <label style="display:block; text-align:left; margin-bottom:8px; color: var(--kami-text-muted); font-weight:700;">Final Drawer Count ($)</label>
                        <input type="number" step="0.01" name="ending_cash" class="input-massive" required placeholder="0.00">
                        <button type="submit" name="clock_out" class="btn btn-danger btn-block" style="padding: 18px; font-size: 16px;">
                            <i class="ph-bold ph-lock-key"></i> End Shift & Lock Register
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Live Clock Script
        function updateClock() {
            const now = new Date();
            let h = now.getHours();
            let m = now.getMinutes();
            let s = now.getSeconds();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12; 
            m = m < 10 ? '0' + m : m;
            s = s < 10 ? '0' + s : s;
            document.getElementById('live-clock').innerText = h + ':' + m + ':' + s + ' ' + ampm;
            
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('live-date').innerText = now.toLocaleDateString('en-US', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        <?php if ($success_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Shift Update', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        <?php endif; ?>
    </script>
</body>
</html>