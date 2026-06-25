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

$success_msg = '';
$error_msg = '';

// 2. PROCESS PASSWORD CHANGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    if ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_msg = "New password must be at least 8 characters long.";
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $update_stmt->execute(['hash' => $new_hash, 'id' => $user_id]);
            
            $success_msg = "Security credentials updated successfully.";
        } else {
            $error_msg = "Current password is incorrect.";
        }
    }
}
?>
<?php
$staff_area  = 'admin';
$page_title  = 'System Settings';
$page_styles = '<style>
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 1200px; }
    @media (max-width: 1024px) { .settings-grid { grid-template-columns: 1fr; } }
</style>';
require '../includes/staff_header.php';
?>
        <h1 class="kami-page-title">System Settings</h1>
        <p class="kami-page-sub">Configure database profiles, accessibility parameters, and theme profiles.</p>

        <div class="settings-grid animate-fade-in">
            
            <!-- Appearance Widget (2026 style) -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-palette"></i> Display Preferences</h3>
                </div>
                
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>Light Solar Profile</h4>
                        <p>Switch between light and dark cyber mode.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="toggleTheme">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="setting-row" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                    <div class="setting-info">
                        <h4>Color Palette Highlights</h4>
                        <p>Set active highlight indicators to Cobalt Cobalt theme.</p>
                    </div>
                    <div class="color-picker">
                        <div class="color-swatch active" data-color="gold" style="background: linear-gradient(135deg, #38BDF8, #0284C7);" title="Cyber Sky Blue"></div>
                    </div>
                </div>
            </div>

            <!-- Accessibility Settings -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-accessibility"></i> Accessibility</h3>
                </div>
                
                <div class="setting-row">
                    <div class="setting-info">
                        <h4>Compact Interface Grid</h4>
                        <p>Minimize margins for dense operational viewing.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="toggleMotion">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="setting-row">
                    <div class="setting-info">
                        <h4>Sound Notifications</h4>
                        <p>Play light acoustic audios on register actions.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="toggleNotifications">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Security Credentials -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px;">
                    <h3><i class="ph-bold ph-lock-key"></i> Clearance Credentials</h3>
                </div>
                
                <form action="settings.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Current Verification Password</label>
                        <input type="password" name="current_password" class="form-input" required placeholder="Verify current credentials">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required placeholder="Minimum 8 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required placeholder="Match new password">
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-primary btn-block" style="margin-top: 8px;">
                        <i class="ph-bold ph-shield-check"></i> <span>Commit Credentials</span>
                    </button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="card glass" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div class="card-header" style="border:none; padding:0; margin-bottom: 16px;">
                        <h3><i class="ph-bold ph-warning"></i> Operational Hazards</h3>
                    </div>
                    <p style="font-size: 13px; color: var(--kami-text-muted); line-height: 1.6; margin-bottom: 16px;">
                        Clearing local memory will wipe custom color selections, operational profiles, and interface density presets.
                    </p>
                </div>
                
                <div class="setting-row" style="border-bottom: none; padding-bottom: 0;">
                    <div class="setting-info">
                        <h4>Local Memory Wipe</h4>
                        <p>Flush active browser cache storage.</p>
                    </div>
                    <button class="btn btn-danger" onclick="localStorage.clear(); window.triggerDynamicIsland('Cache Cleared', 'Preferences flushed.', 'info'); setTimeout(() => { location.reload(); }, 1200);">
                        <i class="ph-bold ph-trash"></i> Wipe Cache
                    </button>
                </div>
            </div>

        </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Theme toggles
            const toggleTheme = document.getElementById('toggleTheme');
            if (localStorage.getItem('kami_theme_mode') === 'light') {
                toggleTheme.checked = true;
            }
            
            toggleTheme.addEventListener('change', (e) => {
                if (e.target.checked) {
                    document.documentElement.classList.add('light-mode');
                    localStorage.setItem('kami_theme_mode', 'light');
                    window.triggerDynamicIsland('Display Saved', 'High-luminescence profile active.', 'success');
                } else {
                    document.documentElement.classList.remove('light-mode');
                    localStorage.setItem('kami_theme_mode', 'dark');
                    window.triggerDynamicIsland('Display Saved', 'Classic slate profile active.', 'success');
                }
            });

            // Compact mode
            const toggleMotion = document.getElementById('toggleMotion');
            if (localStorage.getItem('kami_reduce_motion') === 'true') {
                toggleMotion.checked = true;
            }
            
            toggleMotion.addEventListener('change', (e) => {
                document.documentElement.classList.toggle('reduce-motion', e.target.checked);
                localStorage.setItem('kami_reduce_motion', e.target.checked ? 'true' : 'false');
                if (e.target.checked) {
                    window.triggerDynamicIsland('Aero Config', 'Dense layout metrics active.', 'info');
                } else {
                    window.triggerDynamicIsland('Aero Config', 'Standard layout metrics active.', 'success');
                }
            });

            // Audio signals
            const toggleNotifs = document.getElementById('toggleNotifications');
            if (toggleNotifs) {
                toggleNotifs.checked = localStorage.getItem('kami_notifs') !== 'false';
                toggleNotifs.addEventListener('change', (e) => {
                    localStorage.setItem('kami_notifs', e.target.checked ? 'true' : 'false');
                    if (e.target.checked) {
                        window.triggerDynamicIsland('Audio Signals', 'Acoustic feedback enabled.', 'success');
                    } else {
                        window.triggerDynamicIsland('Audio Signals', 'Acoustic feedback disabled.', 'info');
                    }
                });
            }
        });
    </script>

    <!-- PHP Error and Success growl intercepts -->
    <?php if ($success_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.triggerDynamicIsland('Security Logged', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        </script>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.triggerDynamicIsland('Credential Violation', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        </script>
    <?php endif; ?>
<?php require '../includes/staff_footer.php'; ?>