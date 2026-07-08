<?php
// 1. BACKEND: THE ENGINE & SECURITY CHECK
declare(strict_types=1);
session_start();

// INTELLIGENT ROUTER 1: If already logged in, skip the login page
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: cashier/index.php");
    }
    exit();
}

// Cashier backed out of branch selection -> drop the pending login, start over
if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_cashier']);
    header("Location: login.php");
    exit();
}

// Preloader hook
if (file_exists('includes/preloader.php')) {
    include 'includes/preloader.php';
}

// Time-based dynamic greeting
date_default_timezone_set('Africa/Kigali');
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Morning Protocol Active";
} elseif ($hour < 18) {
    $greeting = "Daytime Operations Secured";
} elseif ($hour < 22) {
    $greeting = "Evening Shift Online";
} else {
    $greeting = "Late Night Telemetry Active";
}

$error_state = null;
require_once 'config/db.php';

// STEP 2 (cashiers only): finalize login once a branch has been chosen
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_branch']) && isset($_SESSION['pending_cashier'])) {
    $branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $branch = null;
    if ($branch_id) {
        $stmt = $pdo->prepare("SELECT id, name, is_open FROM branches WHERE id = :id");
        $stmt->execute(['id' => $branch_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($branch && !(int)$branch['is_open']) {
        $error_state = 'branch_closed';
    } elseif ($branch) {
        $pending = $_SESSION['pending_cashier'];
        $_SESSION['user_id']     = $pending['user_id'];
        $_SESSION['role']        = $pending['role'];
        $_SESSION['full_name']   = $pending['full_name'];
        $_SESSION['branch_id']   = (int)$branch['id'];
        $_SESSION['branch_name'] = $branch['name'];
        $_SESSION['logged_in']   = true;
        unset($_SESSION['pending_cashier']);
        header("Location: cashier/index.php");
        exit();
    } else {
        $error_state = 'invalid_branch';
    }
}

// STEP 1: username/password verification
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['confirm_branch'])) {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error_state = 'invalid_credentials';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, password_hash, role, full_name FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                if ($user['role'] === 'admin') {
                    // Admins see every branch — no picker needed.
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['logged_in'] = true;
                    header("Location: admin/index.php");
                    exit();
                }

                // Cashier: only ask which branch if there's more than one to choose from.
                $branchCount = (int)$pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
                if ($branchCount <= 1) {
                    $onlyBranch = $pdo->query("SELECT id, name, is_open FROM branches ORDER BY is_main DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    if ($onlyBranch && !(int)$onlyBranch['is_open']) {
                        $error_state = 'branch_closed';
                    } else {
                        $_SESSION['user_id']     = $user['id'];
                        $_SESSION['role']        = $user['role'];
                        $_SESSION['full_name']   = $user['full_name'];
                        $_SESSION['branch_id']   = $onlyBranch ? (int)$onlyBranch['id'] : 1;
                        $_SESSION['branch_name'] = $onlyBranch ? $onlyBranch['name'] : 'Main Branch';
                        $_SESSION['logged_in']   = true;
                        header("Location: cashier/index.php");
                        exit();
                    }
                } else {
                    // Hold the login open until they pick a branch below.
                    $_SESSION['pending_cashier'] = [
                        'user_id'   => $user['id'],
                        'role'      => $user['role'],
                        'full_name' => $user['full_name'],
                    ];
                }
            } else {
                $error_state = 'invalid_credentials';
            }
        } catch (PDOException $e) {
            error_log("Authentication Error: " . $e->getMessage());
            $error_state = 'server_error';
        }
    }
}

$showBranchPicker = isset($_SESSION['pending_cashier']);
$branches = [];
if ($showBranchPicker) {
    $branches = $pdo->query("SELECT id, name, is_main, is_open FROM branches ORDER BY is_main DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ozone Admin · Sign in</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #C8911E;
            --accent-hover: #B27E14;
            --accent-soft: rgba(200, 145, 30, 0.12);
            --danger: #D9342B;
            --radius: 10px;
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* Dark (default) */
        html[data-theme="dark"] {
            --bg: #0B0C0E;
            --surface: #0B0C0E;
            --text: #F4F5F6;
            --text-soft: #A0A4AB;
            --text-faint: #6A6E76;
            --border: rgba(255, 255, 255, 0.10);
            --border-strong: rgba(255, 255, 255, 0.18);
            --field: rgba(255, 255, 255, 0.025);
            --field-focus: rgba(255, 255, 255, 0.05);
        }

        /* Light */
        html[data-theme="light"] {
            --bg: #FFFFFF;
            --surface: #FFFFFF;
            --text: #16181D;
            --text-soft: #5A606B;
            --text-faint: #8A909B;
            --border: rgba(16, 18, 23, 0.10);
            --border-strong: rgba(16, 18, 23, 0.18);
            --field: rgba(16, 18, 23, 0.015);
            --field-focus: rgba(16, 18, 23, 0.035);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            transition: background 0.4s var(--ease), color 0.4s var(--ease);
        }

        .shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* ============ LEFT — FORM PANEL ============ */
        .auth {
            display: flex;
            flex-direction: column;
            padding: 40px clamp(40px, 6vw, 88px);
            position: relative;
            min-height: 100vh;
        }

        .auth__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
        }

        .brand__mark {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--text);
            color: var(--bg);
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .brand__name {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .brand__sub {
            font-size: 12px;
            color: var(--text-faint);
            font-weight: 500;
        }

        .top-actions { display: flex; align-items: center; gap: 8px; }

        .ghost-btn {
            height: 38px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 100px;
            color: var(--text-soft);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.2s var(--ease), color 0.2s var(--ease), background 0.2s var(--ease);
        }
        .ghost-btn:hover {
            color: var(--text);
            border-color: var(--border-strong);
            background: var(--field-focus);
        }
        .icon-btn { width: 38px; padding: 0; justify-content: center; }

        .auth__body {
            width: 100%;
            max-width: 380px;
            margin: 48px auto;
            display: flex;
            flex-direction: column;
        }

        .eyebrow {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }

        .auth h1 {
            font-size: 30px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin: 0 0 10px;
        }

        .auth__desc {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-soft);
            margin: 0 0 32px;
        }

        /* Error banner */
        .banner {
            display: none;
            align-items: flex-start;
            gap: 11px;
            background: rgba(217, 52, 43, 0.08);
            border: 1px solid rgba(217, 52, 43, 0.30);
            border-radius: var(--radius);
            padding: 13px 15px;
            margin-bottom: 24px;
        }
        .banner.active {
            display: flex;
            animation: dropIn 0.4s var(--ease) both;
        }
        .banner i { color: var(--danger); font-size: 18px; margin-top: 1px; }
        .banner h4 { margin: 0 0 2px; font-size: 13px; font-weight: 700; color: var(--text); }
        .banner p { margin: 0; font-size: 12.5px; color: var(--text-soft); line-height: 1.45; }

        @keyframes dropIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Fields */
        .field { position: relative; margin-bottom: 18px; }

        .field__input {
            width: 100%;
            height: 56px;
            padding: 20px 16px 6px;
            background: var(--field);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font: inherit;
            font-size: 16px;
            color: var(--text);
            outline: none;
            transition: border-color 0.2s var(--ease), box-shadow 0.2s var(--ease), background 0.2s var(--ease);
        }
        .field--pw .field__input { padding-right: 48px; }

        .field__input:focus {
            border-color: var(--accent);
            background: var(--field-focus);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        .field__label {
            position: absolute;
            left: 16px;
            top: 18px;
            font-size: 15px;
            color: var(--text-faint);
            pointer-events: none;
            transition: all 0.18s var(--ease);
        }
        .field__input:focus ~ .field__label,
        .field__input:not(:placeholder-shown) ~ .field__label {
            top: 9px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.03em;
            color: var(--text-soft);
        }
        .field__input:focus ~ .field__label { color: var(--accent); }

        .field--error .field__input { border-color: var(--danger); }
        .field--error .field__input:focus { box-shadow: 0 0 0 3px rgba(217, 52, 43, 0.18); }

        .pw-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: var(--text-faint);
            font-size: 18px;
            cursor: pointer;
            transition: color 0.2s var(--ease), background 0.2s var(--ease);
        }
        .pw-toggle:hover { color: var(--text); background: var(--field-focus); }

        .row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 6px 0 26px;
        }

        .check {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-size: 13.5px;
            color: var(--text-soft);
            cursor: pointer;
            user-select: none;
        }
        .check input { position: absolute; opacity: 0; width: 0; height: 0; }
        .check__box {
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--border-strong);
            border-radius: 5px;
            display: grid;
            place-items: center;
            color: transparent;
            font-size: 12px;
            transition: all 0.18s var(--ease);
        }
        .check input:checked ~ .check__box {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .check input:focus-visible ~ .check__box { box-shadow: 0 0 0 3px var(--accent-soft); }

        .link {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            transition: color 0.2s var(--ease);
        }
        .link:hover { color: var(--accent); }

        .submit {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: var(--radius);
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.2s var(--ease), transform 0.12s var(--ease), box-shadow 0.2s var(--ease);
        }
        .submit:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 8px 22px rgba(200, 145, 30, 0.28); }
        .submit:active { transform: translateY(0); }
        .submit:disabled { opacity: 0.7; cursor: default; transform: none; box-shadow: none; }

        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 26px 0;
            color: var(--text-faint);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .sso { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sso-btn {
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            background: var(--field);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text);
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: border-color 0.2s var(--ease), background 0.2s var(--ease);
        }
        .sso-btn:hover { border-color: var(--border-strong); background: var(--field-focus); }
        .sso-btn i { font-size: 18px; }

        .signup {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: var(--text-soft);
        }
        .signup a { color: var(--text); font-weight: 700; text-decoration: none; }
        .signup a:hover { color: var(--accent); }

        .auth__foot {
            margin-top: auto;
            padding-top: 32px;
            display: flex;
            gap: 20px;
            font-size: 12.5px;
            color: var(--text-faint);
        }
        .auth__foot a { color: var(--text-faint); text-decoration: none; transition: color 0.2s var(--ease); }
        .auth__foot a:hover { color: var(--text-soft); }
        .auth__foot .spacer { margin-left: auto; }

        /* ============ RIGHT — VISUAL PANEL ============ */
        .visual {
            position: relative;
            overflow: hidden;
            background: #0A0B0D;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 56px;
        }
        .visual__img {
            position: absolute;
            inset: 0;
            background-image:
                url('https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1400&q=80');
            background-size: cover;
            background-position: center;
            transform: scale(1.06);
            animation: slowZoom 24s ease-in-out infinite alternate;
        }
        @keyframes slowZoom {
            from { transform: scale(1.06); }
            to { transform: scale(1.14); }
        }
        .visual__scrim {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(10,11,13,0.35) 0%, rgba(10,11,13,0.55) 50%, rgba(10,11,13,0.92) 100%),
                linear-gradient(115deg, rgba(10,11,13,0.45), transparent 60%);
        }

        .visual__top {
            position: absolute;
            top: 56px;
            left: 56px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px 9px 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 100px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            font-size: 13px;
            font-weight: 600;
        }
        .visual__top i { color: var(--accent); font-size: 16px; }

        .visual__content { position: relative; max-width: 520px; }

        .visual h2 {
            font-size: clamp(30px, 3.4vw, 46px);
            line-height: 1.08;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 0 0 18px;
        }
        .visual h2 .accent { color: var(--accent); }

        .visual__lede {
            font-size: 16px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.72);
            margin: 0 0 36px;
            max-width: 440px;
        }

        .stats {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }
        .stat {
            flex: 1;
            min-width: 130px;
            padding: 18px 20px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .stat__num {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.02em;
            display: flex;
            align-items: baseline;
            gap: 2px;
        }
        .stat__num .unit { color: var(--accent); }
        .stat__label {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 4px;
        }

        /* ============ ENTRANCE ============ */
        .reveal { opacity: 0; transform: translateY(14px); animation: rise 0.7s var(--ease) forwards; }
        .d1 { animation-delay: 0.05s; }
        .d2 { animation-delay: 0.12s; }
        .d3 { animation-delay: 0.19s; }
        .d4 { animation-delay: 0.26s; }
        .d5 { animation-delay: 0.33s; }
        .d6 { animation-delay: 0.40s; }
        @keyframes rise {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-7px); }
            40%, 80% { transform: translateX(7px); }
        }
        .shake { animation: shake 0.45s ease-in-out; }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 980px) {
            .shell { grid-template-columns: 1fr; }
            .visual { display: none; }
            .auth { min-height: 100vh; }
        }
        @media (max-width: 520px) {
            .auth { padding: 28px 22px; }
            .auth__body { margin: 36px auto; }
            .auth h1 { font-size: 26px; }
            .sso { grid-template-columns: 1fr; }
            .auth__foot { flex-wrap: wrap; gap: 12px 20px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal, .visual__img { animation: none; opacity: 1; transform: none; }
        }

        /* ============ BRANCH PICKER (cashier login, step 2) ============ */
        .branch-grid { display: flex; flex-direction: column; gap: 12px; margin-bottom: 8px; }
        .branch-card {
            display: flex; align-items: center; gap: 14px;
            width: 100%; padding: 18px 20px;
            background: var(--field); border: 1px solid var(--border); border-radius: var(--radius);
            color: var(--text); font: inherit; text-align: left; cursor: pointer;
            transition: border-color 0.2s var(--ease), background 0.2s var(--ease), transform 0.12s var(--ease);
        }
        .branch-card:hover { border-color: var(--accent); background: var(--field-focus); transform: translateY(-1px); }
        .branch-card:active { transform: translateY(0); }
        .branch-card__icon {
            width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
            background: var(--accent-soft); color: var(--accent);
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .branch-card__name { font-size: 16px; font-weight: 700; flex: 1; }
        .branch-card__tag {
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--accent); background: var(--accent-soft); padding: 4px 10px; border-radius: 100px;
        }
    </style>
</head>
<body>
    <div class="shell">

        <!-- ============ LEFT: FORM ============ -->
        <main class="auth">
            <div class="auth__top reveal d1">
                <a href="index.php" class="brand">
                    <span class="brand__mark"><i class="ph-bold ph-shield-check"></i></span>
                    <span>
                        <span class="brand__name" style="display:block;">Ozone Admin</span>
                        <span class="brand__sub">Staff Terminal</span>
                    </span>
                </a>
                <div class="top-actions">
                    <button type="button" class="ghost-btn icon-btn" id="theme-toggle" title="Toggle theme" aria-label="Toggle theme">
                        <i class="ph ph-moon"></i>
                    </button>
                    <a href="index.php" class="ghost-btn">
                        <i class="ph ph-arrow-left"></i><span>Exit</span>
                    </a>
                </div>
            </div>

            <div class="auth__body">
            <?php if ($showBranchPicker): ?>
                <div class="eyebrow reveal d2">Almost there, <?= htmlspecialchars(explode(' ', $_SESSION['pending_cashier']['full_name'])[0]) ?></div>
                <h1 class="reveal d2">Which branch are you at?</h1>
                <p class="auth__desc reveal d3">Pick your location to start your shift. This decides which products and sales you'll see today.</p>

                <div class="banner <?= $error_state === 'invalid_branch' ? 'active' : '' ?>">
                    <i class="ph-fill ph-warning-circle"></i>
                    <div>
                        <h4>Select a branch</h4>
                        <p>Please choose a valid branch to continue.</p>
                    </div>
                </div>
                <div class="banner <?= $error_state === 'branch_closed' ? 'active' : '' ?>">
                    <i class="ph-fill ph-warning-circle"></i>
                    <div>
                        <h4>That shop is closed</h4>
                        <p>This branch is closed for the day. Pick another branch, or check with your admin.</p>
                    </div>
                </div>

                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="branch-form">
                    <input type="hidden" name="confirm_branch" value="1">
                    <div class="branch-grid">
                        <?php foreach ($branches as $i => $b): ?>
                            <button type="submit" name="branch_id" value="<?= (int)$b['id'] ?>" class="branch-card reveal d<?= min(4 + $i, 6) ?>" <?= $b['is_open'] ? '' : 'disabled style="opacity:0.5; cursor:not-allowed;"' ?>>
                                <span class="branch-card__icon"><i class="ph-fill ph-storefront"></i></span>
                                <span class="branch-card__name"><?= htmlspecialchars($b['name']) ?></span>
                                <?php if ($b['is_main']): ?><span class="branch-card__tag">Main</span><?php endif; ?>
                                <?php if (!$b['is_open']): ?><span class="branch-card__tag" style="background:transparent;border:1px solid var(--border-strong);color:var(--text-faint);">Closed today</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>

                <p class="signup reveal d6">Not <?= htmlspecialchars($_SESSION['pending_cashier']['full_name']) ?>? <a href="login.php?cancel=1">Sign in again</a></p>
            <?php else: ?>
                <div class="eyebrow reveal d2"><?= htmlspecialchars($greeting) ?></div>
                <h1 class="reveal d2">Welcome back</h1>
                <p class="auth__desc reveal d3">Sign in to access the Ozone control center. Your session is encrypted and monitored for security.</p>

                <div class="banner" id="error-banner">
                    <i class="ph-fill ph-warning-circle"></i>
                    <div>
                        <h4 id="error-banner-title">Access denied</h4>
                        <p id="error-banner-body">The credentials you entered don't match our records. Please try again.</p>
                    </div>
                </div>

                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="login-form" novalidate>
                    <div class="field reveal d4" id="f-username">
                        <input type="text" name="username" id="username" class="field__input" placeholder=" " required autocomplete="username">
                        <label class="field__label" for="username">Username</label>
                    </div>

                    <div class="field field--pw reveal d4" id="f-password">
                        <input type="password" name="password" id="password" class="field__input" placeholder=" " required autocomplete="current-password">
                        <label class="field__label" for="password">Password</label>
                        <button type="button" class="pw-toggle" id="toggle-password" aria-label="Show password">
                            <i class="ph ph-eye"></i>
                        </button>
                    </div>

                    <div class="row-between reveal d5">
                        <label class="check">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="check__box"><i class="ph-bold ph-check"></i></span>
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="link">Forgot password?</a>
                    </div>

                    <button type="submit" class="submit reveal d5" id="submit-btn">
                        <span>Sign in</span>
                        <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </form>

                <div class="divider reveal d6">or</div>

                <div class="sso reveal d6">
                    <button type="button" class="sso-btn">
                        <i class="ph-bold ph-google-logo"></i><span>Google</span>
                    </button>
                    <button type="button" class="sso-btn">
                        <i class="ph-bold ph-microsoft-outlook-logo"></i><span>Microsoft</span>
                    </button>
                </div>

                <p class="signup reveal d6">Need access? <a href="#">Request an account</a></p>
            <?php endif; ?>
            </div>

            <div class="auth__foot reveal d6">
                <span>© <?= date('Y') ?> Ozone</span>
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <span class="spacer"><i class="ph ph-lock-simple"></i> Secured connection</span>
            </div>
        </main>

        <!-- ============ RIGHT: VISUAL ============ -->
        <aside class="visual">
            <div class="visual__img"></div>
            <div class="visual__scrim"></div>

            <div class="visual__top reveal d2">
                <i class="ph-fill ph-seal-check"></i>
                <span>Enterprise-grade security</span>
            </div>

            <div class="visual__content">
                <h2 class="reveal d3">Operate with <span class="accent">total</span> clarity.</h2>
                <p class="visual__lede reveal d4">One unified terminal for your team — real-time telemetry, role-based access, and the controls you need to run operations with confidence.</p>

                <div class="stats">
                    <div class="stat reveal d5">
                        <div class="stat__num">99.9<span class="unit">%</span></div>
                        <div class="stat__label">Uptime reliability</div>
                    </div>
                    <div class="stat reveal d5">
                        <div class="stat__num">256<span class="unit">-bit</span></div>
                        <div class="stat__label">Encrypted sessions</div>
                    </div>
                    <div class="stat reveal d6">
                        <div class="stat__num">24<span class="unit">/7</span></div>
                        <div class="stat__label">Monitored access</div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.documentElement;

            // Theme toggle (with localStorage persistence)
            const themeToggle = document.getElementById('theme-toggle');
            const applyTheme = (t) => {
                root.setAttribute('data-theme', t);
                themeToggle.innerHTML = t === 'dark'
                    ? '<i class="ph ph-moon"></i>'
                    : '<i class="ph ph-sun"></i>';
            };
            try {
                const saved = localStorage.getItem('ozone-theme');
                if (saved) applyTheme(saved);
            } catch (e) {}
            themeToggle.addEventListener('click', () => {
                const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
                try { localStorage.setItem('ozone-theme', next); } catch (e) {}
            });

            // Password visibility toggle (only present on the login form, not the branch picker)
            const pw = document.getElementById('password');
            const pwToggle = document.getElementById('toggle-password');
            if (pw && pwToggle) {
                pwToggle.addEventListener('click', () => {
                    const show = pw.type === 'password';
                    pw.type = show ? 'text' : 'password';
                    pwToggle.innerHTML = show ? '<i class="ph ph-eye-slash"></i>' : '<i class="ph ph-eye"></i>';
                    pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            }

            // Inline validation styling (login form only)
            const form = document.getElementById('login-form');
            if (form) {
                const submitBtn = document.getElementById('submit-btn');
                const fUser = document.getElementById('f-username');
                const fPass = document.getElementById('f-password');
                const user = document.getElementById('username');

                [ [user, fUser], [pw, fPass] ].forEach(([input, wrap]) => {
                    input.addEventListener('input', () => wrap.classList.remove('field--error'));
                });

                form.addEventListener('submit', (e) => {
                    let valid = true;
                    if (!user.value.trim()) { fUser.classList.add('field--error'); valid = false; }
                    if (!pw.value.trim())   { fPass.classList.add('field--error'); valid = false; }
                    if (!valid) { e.preventDefault(); return; }

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="ph-bold ph-spinner-gap ph-spin"></i><span>Signing in…</span>';
                });

                // Server-side error feedback
                const phpError = "<?= $error_state ?? '' ?>";
                if (phpError) {
                    const banner = document.getElementById('error-banner');
                    banner.classList.add('active');
                    if (phpError === 'invalid_credentials') {
                        fUser.classList.add('field--error');
                        fPass.classList.add('field--error');
                    }
                    if (phpError === 'branch_closed') {
                        document.getElementById('error-banner-title').textContent = 'Shop closed';
                        document.getElementById('error-banner-body').textContent = 'This branch is closed for the day. Check with your admin.';
                    }
                    const body = document.querySelector('.auth__body');
                    body.classList.add('shake');
                    setTimeout(() => body.classList.remove('shake'), 460);
                }
            }
        });
    </script>
</body>
</html>