<?php
// 1. ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: pos.php?error=unauthorized");
    exit();
}

require_once '../config/db.php';

$success_msg = '';
$error_msg = '';

// 2. PROCESS NEW BRANCH (CREATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $name = trim((string)filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));

    if ($name !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO branches (name, is_main) VALUES (:name, 0)");
            $stmt->execute(['name' => $name]);
            $success_msg = "Branch '$name' created.";
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    } else {
        $error_msg = "Please provide a branch name.";
    }
}

// 3. PROCESS RENAME (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_branch'])) {
    $edit_id = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
    $name = trim((string)filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING));

    if ($edit_id && $name !== '') {
        $stmt = $pdo->prepare("UPDATE branches SET name = :name WHERE id = :id");
        $stmt->execute(['name' => $name, 'id' => $edit_id]);
        $success_msg = "Branch renamed successfully.";
    } else {
        $error_msg = "Please provide a valid branch name.";
    }
}

// 4. PROCESS DELETE (DELETE) — never allow deleting the main branch or one still in use
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        $stmt = $pdo->prepare("SELECT is_main FROM branches WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        $isMain = $stmt->fetchColumn();

        if ($isMain === false) {
            $error_msg = "Branch not found.";
        } elseif ((int)$isMain === 1) {
            $error_msg = "The Main Branch cannot be deleted.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = :id");
                $stmt->execute(['id' => $delete_id]);
                $success_msg = "Branch removed.";
            } catch (PDOException $e) {
                $error_msg = "Cannot delete this branch — it still has products, sales, or shifts recorded against it.";
            }
        }
    }
}

// 4b. PROCESS CLOSE SHOP FOR THE DAY (per branch, independent of the other branch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_shop'])) {
    $bid = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    if ($bid) {
        $pdo->prepare("UPDATE branches SET is_open = 0 WHERE id = :id")->execute(['id' => $bid]);
        $pdo->prepare("INSERT INTO shop_day_logs (branch_id, action, acted_by) VALUES (:bid, 'close', :uid)")
            ->execute(['bid' => $bid, 'uid' => $_SESSION['user_id']]);
        $success_msg = "Shop closed for the day. Cashiers can't sign in there until you reopen it.";
    }
}

// 4c. PROCESS OPEN SHOP (morning re-open)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_shop'])) {
    $bid = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    if ($bid) {
        $pdo->prepare("UPDATE branches SET is_open = 1 WHERE id = :id")->execute(['id' => $bid]);
        $pdo->prepare("INSERT INTO shop_day_logs (branch_id, action, acted_by) VALUES (:bid, 'open', :uid)")
            ->execute(['bid' => $bid, 'uid' => $_SESSION['user_id']]);
        $success_msg = "Shop reopened — cashiers can sign in again.";
    }
}

// 5. FETCH BRANCHES + PER-BRANCH COUNTS
$branches = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM products WHERE branch_id = b.id) AS product_count,
           (SELECT COUNT(*) FROM sales    WHERE branch_id = b.id) AS sale_count,
           (SELECT acted_at FROM shop_day_logs WHERE branch_id = b.id ORDER BY acted_at DESC LIMIT 1) AS last_action_at
      FROM branches b
     ORDER BY b.is_main DESC, b.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$staff_area = 'admin';
$page_title = 'Branches';
require '../includes/staff_header.php';
?>
    <style>
        .modal-overlay-ui { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay-ui.active { display: flex; opacity: 1; }
        .modal-content { background: var(--kami-surface-1); border: 1px solid var(--kami-border); border-radius: 16px; padding: 32px; width: 100%; max-width: 420px; box-shadow: 0 24px 48px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { background: none; border: none; color: var(--kami-text-muted); font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: var(--kami-text); }
        .form-group { margin-bottom: 16px; }

        .table-responsive { width: 100%; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; border-bottom: 1px solid var(--kami-border); color: var(--kami-text-muted); font-size: 13px; font-weight: 600; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.02); }

        @media (max-width: 768px) {
            body, html { margin: 0 !important; padding: 0 !important; }
            .app-layout { padding: 0 !important; margin: 0 !important; }
            .main-content { margin: 0 !important; padding: 16px !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; min-height: 100vh; }
            .card, .glass { border: none !important; background: transparent; padding: 0; }
            .card-header { padding: 0 0 16px 0 !important; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table tr { margin-bottom: 16px; background: var(--kami-surface-2); border: 1px solid var(--kami-border) !important; border-radius: var(--kami-radius-md); padding: 16px; }
            .data-table td { display: flex; justify-content: space-between; align-items: center; padding: 10px 0 !important; border-bottom: 1px solid rgba(255,255,255,0.04) !important; text-align: right; }
            .data-table td:last-child { border-bottom: none !important; padding-bottom: 0 !important; }
            .data-table td::before { content: attr(data-label); font-weight: 600; color: var(--kami-text-muted); font-size: 13px; text-align: left; padding-right: 16px; }
            .modal-content { padding: 24px; width: 90%; }
        }
    </style>

        <h1 class="kami-page-title">Branches</h1>
        <p class="kami-page-sub">Manage every physical location this system runs — each keeps its own catalog, stock, and sales.</p>

        <div class="card glass animate-fade-in">
                <div class="card-header" style="border:none; padding:0; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3><i class="ph-bold ph-storefront"></i> All Branches</h3>
                        <div class="badge badge-info" style="margin-top: 8px; display: inline-block;"><?= count($branches) ?> Locations</div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="openAddBranchModal()">
                        <i class="ph-bold ph-plus"></i> Add Branch
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Products</th>
                                <th>Sales Logged</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $b): ?>
                                <tr class="hover-scale">
                                    <td data-label="Branch" style="font-weight: 700; font-size: 15px;">
                                        <?= htmlspecialchars($b['name']) ?>
                                        <?php if ($b['is_main']): ?>
                                            <span class="badge badge-info" style="margin-left: 8px;">Main</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <?php if ($b['is_open']): ?>
                                            <span class="badge badge-success"><i class="ph-bold ph-storefront"></i> Open</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger"><i class="ph-bold ph-lock-simple"></i> Closed</span>
                                        <?php endif; ?>
                                        <?php if ($b['last_action_at']): ?>
                                            <div style="font-size:11px; color:var(--kami-text-dim); margin-top:4px;">since <?= date('M j, g:i A', strtotime($b['last_action_at'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Products"><?= (int)$b['product_count'] ?></td>
                                    <td data-label="Sales Logged"><?= (int)$b['sale_count'] ?></td>
                                    <td data-label="Created" style="color: var(--kami-text-muted);"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                                    <td data-label="Actions">
                                        <div class="action-btns">
                                            <?php if ($b['is_open']): ?>
                                                <form action="branches.php" method="POST" style="display:inline;" onsubmit="return confirm('Close this shop for the day? Cashiers here won\'t be able to sign in until you reopen it.');">
                                                    <input type="hidden" name="branch_id" value="<?= (int)$b['id'] ?>">
                                                    <button type="submit" name="close_shop" class="btn-icon" title="Close Shop">
                                                        <i class="ph ph-lock-simple"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="branches.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="branch_id" value="<?= (int)$b['id'] ?>">
                                                    <button type="submit" name="open_shop" class="btn-icon" title="Open Shop">
                                                        <i class="ph ph-lock-simple-open"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="reports.php?branch=<?= (int)$b['id'] ?>" class="btn-icon" title="Today's Report">
                                                <i class="ph ph-file-text"></i>
                                            </a>
                                            <button type="button" class="btn-icon" title="Rename"
                                                onclick='openRenameModal(<?= (int)$b["id"] ?>, <?= htmlspecialchars(json_encode($b["name"]), ENT_QUOTES, "UTF-8") ?>)'>
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                            <?php if (!$b['is_main']): ?>
                                                <form action="branches.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this branch? This only works if it has no products, sales, or shifts recorded.');">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$b['id'] ?>">
                                                    <button type="submit" name="delete_branch" class="btn-icon danger" title="Delete Branch">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        </div>

    <div class="modal-overlay-ui" id="addBranchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="ph-bold ph-plus-circle"></i> Add Branch</h3>
                <button class="modal-close" onclick="closeAddBranchModal()"><i class="ph ph-x"></i></button>
            </div>
            <form action="branches.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g. Kacyiru Branch" autocomplete="off">
                </div>
                <button type="submit" name="add_branch" class="btn btn-primary btn-block" style="margin-top: 8px;">
                    <i class="ph-bold ph-floppy-disk"></i> <span>Create Branch</span>
                </button>
            </form>
        </div>
    </div>

    <div class="modal-overlay-ui" id="renameBranchModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Rename Branch</h3>
                <button class="modal-close" onclick="closeRenameModal()"><i class="ph ph-x"></i></button>
            </div>
            <form action="branches.php" method="POST">
                <input type="hidden" name="edit_id" id="renameIdInput">
                <div class="form-group">
                    <label class="form-label">Branch Name</label>
                    <input type="text" name="edit_name" id="renameNameInput" class="form-input" required autocomplete="off">
                </div>
                <button type="submit" name="rename_branch" class="btn btn-primary btn-block" style="margin-top: 8px;">
                    Save Name
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAddBranchModal() { document.getElementById('addBranchModal').classList.add('active'); }
        function closeAddBranchModal() { document.getElementById('addBranchModal').classList.remove('active'); }
        document.getElementById('addBranchModal').addEventListener('click', function (e) {
            if (e.target === this) closeAddBranchModal();
        });

        function openRenameModal(id, name) {
            document.getElementById('renameIdInput').value = id;
            document.getElementById('renameNameInput').value = name;
            document.getElementById('renameBranchModal').classList.add('active');
        }
        function closeRenameModal() { document.getElementById('renameBranchModal').classList.remove('active'); }
        document.getElementById('renameBranchModal').addEventListener('click', function (e) {
            if (e.target === this) closeRenameModal();
        });

        <?php if ($success_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Branches Updated', '<?= htmlspecialchars($success_msg) ?>', 'success');
            });
        <?php endif; ?>
        <?php if ($error_msg): ?>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.triggerDynamicIsland) window.triggerDynamicIsland('Action Failed', '<?= htmlspecialchars($error_msg) ?>', 'error');
            });
        <?php endif; ?>
    </script>
<?php require '../includes/staff_footer.php'; ?>
