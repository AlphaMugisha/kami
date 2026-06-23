<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

// Strict redirect if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// ==========================================
// STRICT ROLE-BASED ACCESS CONTROL (RBAC)
// ==========================================
// If the user is just a cashier, kick them back to the POS!
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: pos.php?error=unauthorized");
    exit();
}

// Preloader hook
if (file_exists('../includes/preloader.php')) { 
    include '../includes/preloader.php'; 
}

require_once '../config/db.php';

$success_msg = '';
$error_msg = '';

// 2. PROCESS USER DELETION
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Safety check: Prevent the admin from deleting themselves
    if ($delete_id === $_SESSION['user_id']) {
        $error_msg = "Security constraint: You cannot delete your own active session account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $success_msg = "Staff account removed successfully.";
    }
}

// 3. PROCESS NEW USER CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $role = $_POST['role'] ?? 'cashier';
    $raw_password = $_POST['password'];

    if ($name && $username && $raw_password && in_array($role, ['admin', 'cashier'])) {
        // Core Security: Bcrypt the raw password before it ever touches the DB
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (:name, :username, :hash, :role)");
            $stmt->execute([
                'name' => $name,
                'username' => $username,
                'hash' => $hashed_password,
                'role' => $role
            ]);
            $success_msg = "Account for '$name' created. They can now log in.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_msg = "An account with the username '@$username' already exists.";
            } else {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "Please fill all fields securely.";
    }
}

// 4. FETCH LIVE USER DIRECTORY
$stmt = $pdo->query("SELECT id, full_name, username, role, created_at FROM users ORDER BY role ASC, created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ozone Admin | Staff Directory</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/kami.css">
    <style>
        /* Base Grid Setup */
        .users-grid { 
            display: grid; 
            grid-template-columns: minmax(300px, 360px) 1fr; 
            gap: 20px; 
            align-items: start; 
        }

        /* Touch-Friendly Form Elements */
        .form-input, .form-select, .btn {
            box-sizing: border-box;
            width: 100%;
            min-height: 48px; /* Increased for better touch targets */
            padding: 10px 14px;
        }

        .form-group { margin-bottom: 16px; }

        /* Custom Role Badges */
        .role-badge-admin { background: rgba(147, 51, 234, 0.15); color: #c084fc; border: 1px solid rgba(147, 51, 234, 0.3); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center;}
        .role-badge-cashier { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center;}

        /* --- MOBILE MENU ELEMENTS (HIDDEN ON DESKTOP) --- */
        .mobile-menu-btn { display: none; }
        .sidebar-overlay { display: none; }

        /* Tablet Layout */
        @media (max-width: 1100px) { 
            .users-grid { grid-template-columns: 1fr; } 
        }
        
        /* Mobile "Card View" Transformation */
        @media (max-width: 768px) {
            
            /* TRUE EDGE-TO-EDGE MOBILE FULLSCREEN */
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; margin: 0 !important; }

            .main-content {
                margin: 0 !important; 
                padding: 16px !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                min-height: 100vh;
            }
            
            /* Maintain Card Structure on Mobile (FIXED) */
            .card.glass {
                padding: 20px !important;
                border: 1px solid var(--kami-border) !important;
                border-radius: 16px !important;
                background: var(--kami-surface-1); /* Restore background */
                margin-bottom: 24px;
            }
            .card-header { 
                padding: 0 0 16px 0 !important; 
                border-bottom: 1px solid var(--kami-border); 
                margin-bottom: 20px !important; 
            }

            /* Sidebar Slide Control */
            .sidebar, aside {
                position: fixed !important;
                top: 0;
                left: -300px !important;
                width: 280px !important;
                height: 100vh !important;
                z-index: 1000 !important;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 4px 0 24px rgba(0,0,0,0.5);
            }
            
            body.sidebar-open .sidebar, body.sidebar-open aside {
                left: 0 !important;
            }

            /* Blurred background overlay when sidebar is open */
            .sidebar-overlay {
                display: block;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(3px);
                z-index: 999;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            /* Header Adjustments */
            .page-header-container {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 24px;
            }
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--kami-surface-3);
                border: 1px solid var(--kami-border) !important; 
                border-radius: var(--kami-radius-sm);
                width: 44px;
                height: 44px;
                color: var(--kami-text);
                cursor: pointer;
                flex-shrink: 0;
            }
            .page-header { margin-bottom: 0 !important; }
            .page-header p { display: none; }
            .page-header h1 { font-size: 1.5rem; }
            .card-header h3 { font-size: 1.2rem; }

            .users-grid { display: flex; flex-direction: column; gap: 0; }

            /* =========================================
               TRUE MOBILE TABLE REWRITE (CARDS)
               ========================================= */
            .data-table-container { padding: 0; background: transparent; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            
            /* Make each row look like a beautiful nested card */
            .data-table tr {
                margin-bottom: 16px;
                background: var(--kami-surface-2);
                border: 1px solid var(--kami-border) !important;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            }
            .data-table tr:last-child { margin-bottom: 0; }

            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0 !important;
                border-bottom: 1px solid rgba(128,128,128,0.1) !important;
                text-align: right;
            }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }

            .data-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--kami-text-muted);
                font-size: 13px;
                text-align: left;
            }

            /* Make the Avatar & Name act as the card's visual header */
            .data-table td[data-label="Personnel"] {
                border-bottom: 1px dashed rgba(128,128,128,0.2) !important;
                padding-top: 0 !important;
                padding-bottom: 16px !important;
                margin-bottom: 8px;
            }
            .data-table td[data-label="Personnel"]::before { display: none; }
            .data-table td[data-label="Personnel"] > div { width: 100%; justify-content: flex-start; }
            
            .data-table td[data-label="Actions"] { margin-top: 8px; }
            .data-table td[data-label="Actions"]::before { display: none; /* Hide 'Actions' text to make button full width */ }
        }
    </style>
</head>
<body class="app-layout">
    
    <!-- Sidebar Overlay Hook -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Flex Header Container (Matched to Dashboard) -->
        <div class="page-header-container animate-fade-in">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="ph ph-list" style="font-size: 24px;"></i>
            </button>
            <header class="page-header">
                <h1>Staff Accounts Directory</h1>
                <p>Enroll system cashiers & configure operational permission levels</p>
            </header>
        </div>

        <div class="users-grid animate-fade-in">
            
            <!-- Enroll Form Card -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0;">
                    <h3 style="margin: 0;"><i class="ph-bold ph-user-plus"></i> Enroll Personnel</h3>
                </div>
                
                <form action="users.php" method="POST">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" required placeholder="Jane Cashier">
                    </div>
                    <div class="form-group">
                        <label class="form-label">System Username</label>
                        <input type="text" name="username" class="form-input" required placeholder="e.g. jane.c" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Temporary Password</label>
                        <input type="password" name="password" class="form-input" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Clearance Role</label>
                        <select name="role" class="form-select" required>
                            <option value="cashier">Standard Cashier (POS Only)</option>
                            <option value="admin">Admin (Full System Access)</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_user" class="btn btn-primary btn-block" style="margin-top: 16px;">
                        <i class="ph-bold ph-shield-check"></i> <span>Deploy Account</span>
                    </button>
                </form>
            </div>

            <!-- Active Directory Card -->
            <div class="card glass">
                <div class="card-header" style="border:none; padding:0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0;"><i class="ph-bold ph-users-three"></i> Active Directory</h3>
                    <div class="badge badge-info" style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;"><?= count($users) ?> Enrolled</div>
                </div>
                
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Personnel</th>
                                <th>Username</th>
                                <th>Clearance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover-scale">
                                    <td data-label="Personnel" style="font-weight: 700; font-size: 15px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px; background: var(--kami-surface-3); border: 1px solid var(--kami-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--kami-text);">
                                                <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                                            </div>
                                            <div style="display: flex; flex-direction: column;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <span style="word-break: break-word;"><?= htmlspecialchars($user['full_name']) ?></span>
                                                    <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                                        <span style="font-size: 10px; color: var(--kami-accent); background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.2); padding: 2px 6px; border-radius: 8px; font-weight: 800;">(YOU)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Username" style="color: var(--kami-text-muted); font-weight: 600;">
                                        @<?= htmlspecialchars($user['username']) ?>
                                    </td>
                                    <td data-label="Clearance">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="role-badge-admin"><i class="ph-fill ph-crown" style="margin-right: 4px;"></i> Admin</span>
                                        <?php else: ?>
                                            <span class="role-badge-cashier"><i class="ph-fill ph-storefront" style="margin-right: 4px;"></i> Cashier</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                            <button class="btn btn-secondary" disabled style="opacity: 0.5; cursor: not-allowed; width: 100%; justify-content: center; height: 40px; font-size: 13px;">Active Session</button>
                                        <?php else: ?>
                                            <a href="users.php?delete=<?= $user['id'] ?>" class="btn btn-danger" style="width: 100%; justify-content: center; height: 40px; font-size: 13px;" onclick="return confirm('Are you sure you want to revoke database clearance credentials for <?= htmlspecialchars($user['full_name']) ?>?');">
                                                <i class="ph-bold ph-trash"></i> Revoke Access
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile Sidebar Toggle Script -->
    <script>
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }
    </script>

    <?php if ($success_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Access Created', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        </script>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if(window.triggerDynamicIsland) window.triggerDynamicIsland('Clearance Warning', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        </script>
    <?php endif; ?>
</body>
</html>