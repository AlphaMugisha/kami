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
$success_msg = '';
$error_msg = '';

// PROCESS: PASSWORD UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All security fields must be filled out.";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "The new passwords do not match. Please try again.";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "For your protection, the new password must be at least 6 characters long.";
    } else {
        // 2. Fetch the cashier's current secure hash from the database
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $cashier_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Verify they actually know their current password
        if ($user && password_verify($current_password, $user['password_hash'])) {
            
            // 4. Hash the new password and update the database
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            
            if ($update_stmt->execute(['hash' => $new_hash, 'id' => $cashier_id])) {
                $success_msg = "Security credentials updated successfully.";
            } else {
                $error_msg = "A server error occurred while updating your credentials.";
            }
        } else {
            // They typed their current password wrong!
            $error_msg = "Authorization failed. Incorrect current password.";
        }
    }
}
?>
<?php
$staff_area  = 'cashier';
$page_title  = 'Security Settings';
$page_styles = '<style>
    .settings-container { display: grid; grid-template-columns: 1fr; gap: 24px; max-width: 600px; margin: 0 auto; }
    .settings-container .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--kami-text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; }
    .password-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
    @media (max-width: 620px) {
        .password-row { grid-template-columns: 1fr; }
    }
</style>';
require '../includes/staff_header.php';
?>
        <h1 class="kami-page-title">Account Security</h1>
        <p class="kami-page-sub">Manage your terminal access credentials and private password.</p>

        <div class="settings-container animate-fade-in">
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 24px;">
                    <h3><i class="ph-bold ph-shield-check"></i> Update Password</h3>
                </div>
                
                <form action="settings.php" method="POST">
                    <div class="form-group" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--kami-border);">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-input" required placeholder="Verify your current identity" autocomplete="current-password">
                    </div>

                    <div class="password-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-input" required placeholder="Minimum 6 characters" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required placeholder="Type it exactly again" autocomplete="new-password">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-primary btn-block" style="padding: 16px; font-size: 16px;">
                        <i class="ph-bold ph-lock-key"></i> Save New Credentials
                    </button>
                </form>
            </div>
        </div>

    <?php if ($success_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Security Locked', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        </script>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Access Denied', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        </script>
    <?php endif; ?>
<?php require '../includes/staff_footer.php'; ?>