<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in as admin
if (isLoggedIn() && isAdmin()) {
    redirect('admin/dashboard.php');
}

$error = '';
$success = '';

// Rate limiting — track failed attempts in session
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_login_lockout_until'] = 0;
}

$is_locked_out = time() < $_SESSION['admin_login_lockout_until'];
$lockout_remaining = max(0, $_SESSION['admin_login_lockout_until'] - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked_out) {
    // Validate CSRF token
    $csrf_token_valid = !empty($_POST['csrf_token']) && 
                        hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    
    if (!$csrf_token_valid) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $db   = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT id, full_name, email, password, user_type, is_active, status
                    FROM users
                    WHERE email = ? AND user_type = 'admin'
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    if (!$admin['is_active'] || $admin['status'] !== 'active') {
                        $error = 'This admin account has been deactivated.';
                    } else {
                        // Successful login
                        $_SESSION['admin_login_attempts'] = 0;
                        $_SESSION['admin_login_lockout_until'] = 0;

                        $_SESSION['user_id']    = $admin['id'];
                        $_SESSION['user_role']  = 'admin';
                        $_SESSION['user_name']  = $admin['full_name'];
                        $_SESSION['user_email'] = $admin['email'];
                        $_SESSION['CREATED']    = time();
                        $_SESSION['LAST_ACTIVITY'] = time();

                        session_regenerate_id(true);

                        // Log login activity
                        $logStmt = $db->prepare("
                            INSERT INTO admin_activity_logs (admin_id, activity_type, activity_details, ip_address, user_agent)
                            VALUES (?, 'login', 'Admin logged in', ?, ?)
                        ");
                        $logStmt->execute([
                            $admin['id'],
                            $_SERVER['REMOTE_ADDR'] ?: '127.0.0.1',
                            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                        ]);

                        redirect('admin/dashboard.php');
                    }
                } else {
                    $_SESSION['admin_login_attempts']++;
                    if ($_SESSION['admin_login_attempts'] >= 5) {
                        $_SESSION['admin_login_lockout_until'] = time() + 900; // 15 min lockout
                        $error = 'Too many failed attempts. Account locked for 15 minutes.';
                    } else {
                        $remaining = 5 - $_SESSION['admin_login_attempts'];
                        $error = "Invalid credentials. {$remaining} attempt(s) remaining.";
                    }
                }
            } catch (Exception $e) {
                error_log('Admin login error: ' . $e->getMessage());
                $error = 'A system error occurred. Please try again.';
            }
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get platform name
$platform_name = 'BII LocalFinder';
try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'platform_name' LIMIT 1");
    $stmt->execute();
    $row  = $stmt->fetch();
    if ($row) $platform_name = $row['setting_value'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access — <?php echo htmlspecialchars($platform_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:    #0b1120;
            --navy-2:  #101827;
            --navy-3:  #1a2640;
            --blue:    #2563eb;
            --blue-2:  #1d4ed8;
            --blue-glow: rgba(37, 99, 235, 0.35);
            --gold:    #f59e0b;
            --gold-2:  #d97706;
            --cream:   #fafaf7;
            --muted:   #94a3b8;
            --border:  rgba(255,255,255,0.07);
            --success: #10b981;
            --danger:  #ef4444;
        }

        html, body {
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--navy);
            color: #e2e8f0;
            overflow: hidden;
        }

        /* ── BACKGROUND ── */
        .bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        /* Grid lines */
        .bg-canvas::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(37,99,235,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37,99,235,0.06) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Radial glow top-right */
        .bg-canvas::after {
            content: '';
            position: absolute;
            top: -200px; right: -200px;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(37,99,235,0.18) 0%, transparent 65%);
            animation: pulseGlow 6s ease-in-out infinite alternate;
        }

        @keyframes pulseGlow {
            from { opacity: 0.7; transform: scale(1); }
            to   { opacity: 1;   transform: scale(1.12); }
        }

        /* Floating orb bottom-left */
        .orb {
            position: fixed;
            bottom: -120px; left: -80px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(245,158,11,0.08) 0%, transparent 65%);
            z-index: 0;
            animation: orbDrift 8s ease-in-out infinite alternate;
        }

        @keyframes orbDrift {
            from { transform: translate(0, 0); }
            to   { transform: translate(30px, -40px); }
        }

        /* Scanlines overlay */
        .scanlines {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0,0,0,0.03) 2px,
                rgba(0,0,0,0.03) 4px
            );
        }

        /* ── LAYOUT ── */
        .page {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr 480px;
            height: 100vh;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem 4rem;
            position: relative;
            overflow: hidden;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .brand-icon {
            width: 42px; height: 42px;
            background: var(--blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            box-shadow: 0 0 20px var(--blue-glow);
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: #e2e8f0;
            letter-spacing: -0.3px;
        }

        .brand-badge {
            font-family: 'DM Mono', monospace;
            font-size: 0.6rem;
            color: var(--gold);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.25rem;
        }

        .hero-text {
            max-width: 520px;
        }

        .hero-eyebrow {
            font-family: 'DM Mono', monospace;
            font-size: 0.7rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .hero-eyebrow::before {
            content: '';
            width: 24px; height: 1px;
            background: var(--gold);
        }

        .hero-title {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(2.8rem, 4vw, 4rem);
            line-height: 1.08;
            letter-spacing: -0.02em;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
        }

        .hero-title em {
            font-style: italic;
            color: var(--blue);
            text-shadow: 0 0 40px var(--blue-glow);
        }

        .hero-subtitle {
            font-size: 1rem;
            color: var(--muted);
            line-height: 1.7;
            max-width: 400px;
        }

        .stat-row {
            display: flex;
            gap: 2.5rem;
        }

        .stat { display: flex; flex-direction: column; gap: 0.2rem; }

        .stat-num {
            font-family: 'DM Serif Display', serif;
            font-size: 1.75rem;
            color: #f1f5f9;
            letter-spacing: -0.04em;
        }

        .stat-label {
            font-size: 0.72rem;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .left-footer {
            font-size: 0.72rem;
            color: rgba(148,163,184,0.5);
            font-family: 'DM Mono', monospace;
        }

        /* Decorative vertical line */
        .left-panel::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 1px; height: 100%;
            background: linear-gradient(to bottom, transparent, var(--border) 30%, var(--border) 70%, transparent);
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: rgba(16, 24, 39, 0.6);
            backdrop-filter: blur(24px);
        }

        .login-card {
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-kicker {
            font-family: 'DM Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.625rem;
        }

        .login-title {
            font-family: 'DM Serif Display', serif;
            font-size: 1.75rem;
            color: #f1f5f9;
            letter-spacing: -0.02em;
            margin-bottom: 0.375rem;
        }

        .login-desc {
            font-size: 0.85rem;
            color: var(--muted);
        }

        /* ── FORM ── */
        .form-group {
            margin-bottom: 1.125rem;
        }

        .form-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.5rem;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.875rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Outfit', sans-serif;
            color: #e2e8f0;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input::placeholder { color: rgba(148,163,184,0.4); }

        .form-input:focus {
            background: rgba(37,99,235,0.06);
            border-color: rgba(37,99,235,0.5);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12), inset 0 1px 0 rgba(255,255,255,0.04);
        }

        .form-input:focus + i,
        .input-wrap:focus-within i { color: var(--blue); }

        /* Reorder icon after input for CSS sibling trick */
        .input-wrap input { order: 2; }
        .input-wrap i { order: 1; }

        .toggle-password {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 0.875rem;
            padding: 0.25rem;
            transition: color 0.2s;
        }

        .toggle-password:hover { color: #e2e8f0; }

        /* ── ALERTS ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            font-size: 0.83rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
            animation: alertSlide 0.3s ease;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
        }

        .alert-lockout {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.25);
            color: #fcd34d;
        }

        .alert i { flex-shrink: 0; margin-top: 1px; }

        /* ── SUBMIT BUTTON ── */
        .submit-btn {
            width: 100%;
            padding: 0.9375rem;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, transparent 50%);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .submit-btn:hover { background: var(--blue-2); transform: translateY(-1px); box-shadow: 0 8px 24px var(--blue-glow); }
        .submit-btn:hover::before { opacity: 1; }
        .submit-btn:active { transform: translateY(0); }
        .submit-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── DIVIDER ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
            color: rgba(148,163,184,0.35);
            font-size: 0.72rem;
            font-family: 'DM Mono', monospace;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.07);
        }

        /* ── SECURITY BADGES ── */
        .security-row {
            display: flex;
            gap: 0.625rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .sec-badge {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.68rem;
            color: rgba(148,163,184,0.5);
            font-family: 'DM Mono', monospace;
        }

        .sec-badge i { font-size: 0.6rem; color: var(--success); }

        /* ── COUNTDOWN ── */
        .countdown-num {
            font-family: 'DM Mono', monospace;
            font-weight: 500;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .page { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { background: var(--navy-2); backdrop-filter: none; }
            body { overflow: auto; }
        }
    </style>
</head>
<body>

<!-- Background -->
<div class="bg-canvas"></div>
<div class="orb"></div>
<div class="scanlines"></div>

<div class="page">

    <!-- Left Panel -->
    <div class="left-panel">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-map-marked-alt"></i></div>
            <div>
                <div class="brand-name"><?php echo htmlspecialchars($platform_name); ?></div>
            </div>
            <span class="brand-badge">Admin</span>
        </div>

        <div class="hero-text">
            <div class="hero-eyebrow">Control Center</div>
            <h1 class="hero-title">
                Manage with<br>
                <em>full control.</em>
            </h1>
            <p class="hero-subtitle">
                Access the administrative dashboard to oversee providers, clients, bookings, and platform settings across Rwanda.
            </p>
        </div>

        <div>
            <div class="stat-row" style="margin-bottom: 2rem;">
                <div class="stat">
                    <span class="stat-num">500+</span>
                    <span class="stat-label">Providers</span>
                </div>
                <div class="stat">
                    <span class="stat-num">30+</span>
                    <span class="stat-label">Districts</span>
                </div>
                <div class="stat">
                    <span class="stat-num">2K+</span>
                    <span class="stat-label">Bookings</span>
                </div>
            </div>
            <div class="left-footer">
                &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($platform_name); ?> · Restricted access
            </div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
        <div class="login-card">
            <div class="login-header">
                <div class="login-kicker">Restricted Area</div>
                <h2 class="login-title">Admin Sign In</h2>
                <p class="login-desc">Authorized personnel only. All access is logged.</p>
            </div>

            <?php if ($is_locked_out): ?>
                <div class="alert alert-lockout">
                    <i class="fas fa-lock"></i>
                    <div>Account temporarily locked. Try again in <span class="countdown-num" id="countdown"><?php echo $lockout_remaining; ?>s</span>.</div>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label class="form-label" for="email">
                        <span>Email address</span>
                    </label>
                    <div class="input-wrap">
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-input"
                            placeholder="admin@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            autocomplete="username"
                            required
                            <?php if ($is_locked_out) echo 'disabled'; ?>
                        >
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">
                        <span>Password</span>
                    </label>
                    <div class="input-wrap">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-input"
                            placeholder="••••••••••••"
                            autocomplete="current-password"
                            required
                            <?php if ($is_locked_out) echo 'disabled'; ?>
                        >
                        <i class="fas fa-key"></i>
                        <button type="button" class="toggle-password" id="togglePw" aria-label="Show password">
                            <i class="fas fa-eye" id="pwEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="submit-btn"
                    id="submitBtn"
                    <?php if ($is_locked_out) echo 'disabled'; ?>
                >
                    <div class="spinner" id="spinner"></div>
                    <span id="btnLabel">
                        <i class="fas fa-shield-alt"></i>
                        Sign in securely
                    </span>
                </button>
            </form>

            <div class="divider">secured connection</div>

            <div class="security-row">
                <div class="sec-badge"><i class="fas fa-circle"></i> AES-256</div>
                <div class="sec-badge"><i class="fas fa-circle"></i> Rate Limited</div>
                <div class="sec-badge"><i class="fas fa-circle"></i> Audit Logged</div>
                <div class="sec-badge"><i class="fas fa-circle"></i> Session Protected</div>
            </div>
        </div>
    </div>

</div>

<script>
    // ── Toggle password visibility
    const toggleBtn   = document.getElementById('togglePw');
    const passwordInput = document.getElementById('password');
    const eyeIcon     = document.getElementById('pwEyeIcon');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const isText = passwordInput.type === 'text';
            passwordInput.type = isText ? 'password' : 'text';
            eyeIcon.className  = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    }

    // ── Submit loading state
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const spinner   = document.getElementById('spinner');
    const btnLabel  = document.getElementById('btnLabel');

    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            if (this.checkValidity()) {
                spinner.style.display = 'block';
                btnLabel.style.opacity = '0.6';
                submitBtn.disabled = true;
            }
        });
    }

    // ── Lockout countdown
    <?php if ($is_locked_out): ?>
    let remaining = <?php echo $lockout_remaining; ?>;
    const el = document.getElementById('countdown');

    const tick = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(tick);
            window.location.reload();
        } else {
            if (el) el.textContent = remaining + 's';
        }
    }, 1000);
    <?php endif; ?>

    // ── Auto-focus email on load
    document.getElementById('email')?.focus();
</script>
</body>
</html>