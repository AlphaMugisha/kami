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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once 'config/db.php'; 

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
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['logged_in'] = true;

                // INTELLIGENT ROUTER 2: The moment they click "Unlock System"
                if ($user['role'] === 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: cashier/index.php"); 
                }
                exit();
            } else {
                $error_state = 'invalid_credentials';
            }
        } catch (PDOException $e) {
            error_log("Authentication Error: " . $e->getMessage());
            $error_state = 'server_error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ozone Admin | Secure Entry Portal</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            /* Dark Theme Variables */
            --bg-grad-1: #0a0a14;
            --bg-grad-2: #020205;
            --card-bg: rgba(15, 15, 20, 0.6);
            --text-primary: #ffffff;
            --kami-text-muted: #8e8e9f;
            --kami-accent: #FFB000;
            --kami-danger: #FF453A;
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.1);
            --grid-color: rgba(255, 255, 255, 0.03);
            --kami-spring: cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --kami-transition: 0.3s ease;
        }

        html.light-mode {
            /* Light Theme Variables */
            --bg-grad-1: #f0f0f5;
            --bg-grad-2: #e0e0e8;
            --card-bg: rgba(255, 255, 255, 0.8);
            --text-primary: #111118;
            --kami-text-muted: #6e6e7f;
            --kami-accent: #FF9500;
            --input-bg: rgba(0, 0, 0, 0.03);
            --input-border: rgba(0, 0, 0, 0.1);
            --grid-color: rgba(0, 0, 0, 0.03);
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top right, var(--bg-grad-1), var(--bg-grad-2));
            overflow: hidden;
            padding: 20px;
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text-primary);
            transition: background 0.5s ease, color 0.5s ease;
            perspective: 1000px;
        }

        /* Animated Cyber Grid Background */
        body::before {
            content: '';
            position: absolute;
            inset: -50%;
            background-image: 
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 50px 50px;
            transform: rotateX(60deg) translateY(-100px) translateZ(-200px);
            animation: gridMove 20s linear infinite;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes gridMove {
            0% { transform: rotateX(60deg) translateY(0) translateZ(-200px); }
            100% { transform: rotateX(60deg) translateY(50px) translateZ(-200px); }
        }

        /* Desktop-First Login Card */
        .login-card-wrapper {
            width: 100%;
            max-width: 480px;
            transform-style: preserve-3d;
            transition: transform 0.1s ease-out; 
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--input-border);
            border-radius: 24px;
            box-shadow: 0 45px 120px rgba(0,0,0,0.4), 
                        inset 0 0 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            transform: translateZ(20px); 
        }

        /* Staggered entrance animations */
        .stagger-1 { animation: slideUpFade 0.6s 0.1s both var(--kami-spring); }
        .stagger-2 { animation: slideUpFade 0.6s 0.2s both var(--kami-spring); }
        .stagger-3 { animation: slideUpFade 0.6s 0.3s both var(--kami-spring); }
        .stagger-4 { animation: slideUpFade 0.6s 0.4s both var(--kami-spring); }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Return to Lounge Button */
        .return-lounge-btn {
            position: fixed;
            top: 24px;
            left: 24px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 700;
            color: var(--kami-text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .return-lounge-btn:hover {
            color: var(--text-primary);
            border-color: rgba(255, 176, 0, 0.4);
            transform: translateX(-4px);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Top Header */
        .lock-title-block {
            text-align: center;
            margin-bottom: 32px;
            width: 100%;
        }

        .lock-title-block h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, var(--text-primary) 30%, var(--kami-text-muted) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 8px 0;
        }

        /* Typewriter Effect for dynamic greeting */
        .typing-effect {
            color: var(--kami-accent);
            font-size: 14px;
            font-weight: 700;
            margin: 0 auto;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            overflow: hidden;
            white-space: nowrap;
            border-right: 2px solid var(--kami-accent);
            width: 0;
            animation: typing 1.5s steps(30, end) forwards 0.3s, blink 0.75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        @keyframes blink {
            from, to { border-color: transparent }
            50% { border-color: var(--kami-accent) }
        }

        /* Alert Banner */
        .alert-banner {
            width: 100%;
            background: rgba(255, 69, 58, 0.1);
            border: 1px solid rgba(255, 69, 58, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            display: none;
            box-sizing: border-box;
        }

        .alert-banner.active {
            display: flex;
            animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-icon, .alert-text h4 { color: var(--kami-danger); }
        .alert-text h4 { margin: 0 0 4px 0; font-size: 14px; font-weight: 700; }
        .alert-text p { color: var(--kami-text-muted); margin: 0; font-size: 12px; }

        /* Giant Holographic FaceID Scanner */
        .hologram-face-container {
            width: 100px;
            height: 100px;
            margin-bottom: 32px;
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hologram-face-ring {
            position: absolute;
            inset: 0;
            border: 2px dashed rgba(255, 176, 0, 0.3);
            border-radius: 50%;
            animation: ringRotate 20s linear infinite;
            transition: border-color 0.3s;
        }

        .hologram-face-ring-active {
            position: absolute;
            inset: -4px;
            border: 2.5px solid var(--kami-accent);
            border-radius: 50%;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.4s var(--kami-spring);
        }

        .hologram-face-container:hover .hologram-face-ring {
            border-color: var(--kami-accent);
            animation-duration: 8s;
        }

        .hologram-face-icon {
            font-size: 48px;
            color: var(--kami-accent);
            transition: all var(--kami-spring);
        }

        .hologram-face-container:hover .hologram-face-icon {
            transform: scale(1.1);
            text-shadow: 0 0 15px var(--kami-accent);
        }

        /* Pulse animation for when inputs are focused */
        @keyframes scannerPulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }
        .scanner-watching .hologram-face-icon {
            animation: scannerPulse 2s infinite ease-in-out;
        }

        .hologram-laser {
            position: absolute;
            left: 10%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--kami-accent), transparent);
            box-shadow: 0 0 10px var(--kami-accent);
            top: 20%;
            opacity: 0;
            pointer-events: none;
        }

        .scanning-active .hologram-laser {
            animation: laserScan 1.2s infinite ease-in-out;
            opacity: 1;
        }

        .scanning-active .hologram-face-ring-active {
            opacity: 1;
            transform: scale(1);
        }

        @keyframes ringRotate { to { transform: rotate(360deg); } }
        @keyframes laserScan {
            0% { top: 15%; opacity: 0; }
            30% { opacity: 1; }
            70% { opacity: 1; }
            100% { top: 85%; opacity: 0; }
        }

        .scanner-feedback {
            font-size: 13px;
            color: var(--kami-text-muted);
            font-weight: 600;
            margin-top: -16px;
            margin-bottom: 32px;
            height: 20px;
            transition: color 0.3s;
        }

        .scanner-feedback.scanning { color: var(--kami-accent); }

        /* Form Elements - Floating Labels */
        .hologram-form { width: 100%; }

        .hologram-form-group {
            margin-bottom: 24px;
            position: relative;
            width: 100%;
        }

        .hologram-form-input {
            width: 100%;
            padding: 22px 20px 10px 20px;
            padding-right: 56px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            color: var(--text-primary);
            outline: none;
            transition: all var(--kami-transition);
            box-sizing: border-box;
        }

        .hologram-form-input:focus {
            background: rgba(255, 176, 0, 0.05);
            border-color: var(--kami-accent);
            box-shadow: 0 0 0 4px rgba(255, 176, 0, 0.15);
        }

        .hologram-form-label {
            position: absolute;
            left: 20px;
            top: 18px;
            font-size: 15px;
            color: var(--kami-text-muted);
            pointer-events: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hologram-form-input:focus ~ .hologram-form-label,
        .hologram-form-input:not(:placeholder-shown) ~ .hologram-form-label {
            top: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--kami-accent);
        }

        .hologram-toggle-btn {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--kami-text-muted);
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s, transform 0.2s;
        }

        .hologram-toggle-btn:hover {
            color: var(--text-primary);
            transform: translateY(-50%) scale(1.1);
        }

        /* Submit Button with Shine Effect */
        .hologram-submit-btn {
            width: 100%;
            height: 54px;
            border-radius: 12px;
            background: var(--kami-accent);
            color: #000;
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border: none;
            cursor: pointer;
            margin-top: 16px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .hologram-submit-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: skewX(-20deg);
            transition: 0.5s;
        }

        .hologram-submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 176, 0, 0.35);
        }

        .hologram-submit-btn:hover::after {
            left: 200%;
        }

        .hologram-submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 5px 10px rgba(255, 176, 0, 0.3);
        }

        /* Theme controls at top */
        .theme-switcher-badge {
            position: fixed;
            top: 24px;
            right: 24px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            color: var(--kami-text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.2s;
        }

        .theme-switcher-badge:hover {
            color: var(--text-primary);
            border-color: rgba(255, 176, 0, 0.4);
            transform: translateY(-2px);
        }
        
        /* Shake animation for errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-6px); }
            20%, 40%, 60%, 80% { transform: translateX(6px); }
        }
        .shake-card { animation: shake 0.5s ease-in-out; }
    </style>
</head>
<body>
    
    <a href="index.php" class="return-lounge-btn stagger-1">
        <i class="ph-bold ph-arrow-left"></i>
        <span>Exit Terminal</span>
    </a>

    <div class="theme-switcher-badge stagger-1" id="theme-switch-btn">
        <i class="ph ph-moon"></i>
        <span>Dark Cyber Mode</span>
    </div>

    <div class="login-card-wrapper" id="card-wrapper">
        <div class="login-card" id="main-login-card">
            
            <div class="lock-title-block stagger-1">
                <h1>Ozone Admin</h1>
                <div class="typing-effect"><?= htmlspecialchars($greeting) ?></div>
            </div>

            <div class="alert-banner" id="error-banner">
                <div class="alert-icon"><i class="ph-fill ph-warning-circle"></i></div>
                <div class="alert-text">
                    <h4>Access Denied</h4>
                    <p>Invalid credentials provided. Please try again.</p>
                </div>
            </div>

            <div class="hologram-face-container stagger-2" id="face-id-container" title="Click to Scan Face Signal">
                <div class="hologram-face-ring"></div>
                <div class="hologram-face-ring-active"></div>
                <div class="hologram-laser"></div>
                <i class="ph ph-scan-face hologram-face-icon" id="face-id-icon"></i>
            </div>
            
            <div class="scanner-feedback stagger-2" id="biometric-status">Tap scanner or focus input to verify</div>

            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="hologram-form" id="login-form">
                
                <div class="hologram-form-group stagger-3">
                    <input type="text" name="username" id="username" class="hologram-form-input" placeholder=" " required autocomplete="username">
                    <label class="hologram-form-label">Clearance ID (Username)</label>
                </div>
                
                <div class="hologram-form-group stagger-3">
                    <input type="password" id="password" name="password" class="hologram-form-input" placeholder=" " required autocomplete="current-password">
                    <label class="hologram-form-label">Access Credentials (Password)</label>
                    <button type="button" id="toggle-password" class="hologram-toggle-btn" title="Toggle visibility">
                        <i class="ph ph-eye"></i>
                    </button>
                </div>

                <button type="submit" class="hologram-submit-btn stagger-4" id="submit-btn">
                    <i class="ph-bold ph-lock-key-open"></i>
                    <span>Unlock System</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Interactive 3D Tilt Effect on Card
            const wrapper = document.getElementById('card-wrapper');
            document.addEventListener('mousemove', (e) => {
                const xAxis = (window.innerWidth / 2 - e.pageX) / 40;
                const yAxis = (window.innerHeight / 2 - e.pageY) / 40;
                wrapper.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            // Password Toggle Micro-interaction
            const pwField = document.getElementById('password');
            const pwToggle = document.getElementById('toggle-password');
            if (pwField && pwToggle) {
                pwToggle.addEventListener('click', () => {
                    const isPw = pwField.type === 'password';
                    pwField.type = isPw ? 'text' : 'password';
                    pwToggle.innerHTML = isPw ? '<i class="ph ph-eye-slash"></i>' : '<i class="ph ph-eye"></i>';
                });
            }

            // Make FaceID pulse when typing
            const inputs = document.querySelectorAll('.hologram-form-input');
            const faceContainer = document.getElementById('face-id-container');
            inputs.forEach(input => {
                input.addEventListener('focus', () => faceContainer.classList.add('scanner-watching'));
                input.addEventListener('blur', () => faceContainer.classList.remove('scanner-watching'));
            });

            // Biometrics Scan Simulation
            const faceIdContainer = document.getElementById('face-id-container');
            const faceIdIcon = document.getElementById('face-id-icon');
            const biometricStatus = document.getElementById('biometric-status');
            const usernameField = document.getElementById('username');

            if (faceIdContainer && faceIdIcon && biometricStatus) {
                faceIdContainer.addEventListener('click', () => {
                    faceIdContainer.classList.add('scanning-active');
                    faceIdIcon.className = 'ph ph-spinner-gap hologram-face-icon ph-spin';
                    biometricStatus.innerText = 'Locking onto biometric signal...';
                    biometricStatus.classList.add('scanning');

                    setTimeout(() => {
                        faceIdContainer.classList.remove('scanning-active');
                        faceIdIcon.className = 'ph ph-check-circle hologram-face-icon'; 
                        biometricStatus.innerText = 'Verification complete. Fill form details below.';
                        biometricStatus.classList.remove('scanning');
                        
                        setTimeout(() => {
                            faceIdIcon.className = 'ph ph-scan-face hologram-face-icon'; 
                            if (usernameField) usernameField.focus();
                        }, 1000);
                    }, 1800);
                });
            }

            // Functional Theme Switcher
            const themeBtn = document.getElementById('theme-switch-btn');
            themeBtn.addEventListener('click', () => {
                const htmlDoc = document.documentElement;
                const isLight = htmlDoc.classList.contains('light-mode');
                
                if (isLight) {
                    htmlDoc.classList.remove('light-mode');
                    themeBtn.innerHTML = '<i class="ph ph-moon"></i> <span>Dark Cyber Mode</span>';
                } else {
                    htmlDoc.classList.add('light-mode');
                    themeBtn.innerHTML = '<i class="ph ph-sun"></i> <span>Light Solar Mode</span>';
                }
            });

            // PHP Error handling (Shake effect triggers on wrapper)
            const phpError = "<?= $error_state ?? '' ?>";
            const errorBanner = document.getElementById('error-banner');
            
            if (phpError && phpError !== "") {
                setTimeout(() => {
                    errorBanner.classList.add('active');
                    wrapper.classList.add('shake-card');
                    
                    setTimeout(() => {
                        wrapper.classList.remove('shake-card');
                    }, 500);
                }, 100);
            }

            // Form Submit FaceID Scan micro-interaction
            const form = document.getElementById('login-form');
            const submitBtn = document.getElementById('submit-btn');

            if (form && submitBtn) {
                form.addEventListener('submit', (e) => {
                    if (!form.dataset.scanned) {
                        e.preventDefault();
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ph-bold ph-spinner-gap ph-spin"></i> <span>Verifying Secure Key...</span>';
                        
                        faceIdContainer.classList.add('scanning-active');
                        faceIdIcon.className = 'ph ph-spinner-gap hologram-face-icon ph-spin';
                        biometricStatus.innerText = 'Locking onto biometric credentials...';
                        biometricStatus.classList.add('scanning');

                        setTimeout(() => {
                            form.dataset.scanned = "true";
                            form.submit();
                        }, 1200);
                    }
                });
            }
        });
    </script>
</body>
</html>