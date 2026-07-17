<?php
// enable gzip if available (reduces payload)
if (!headers_sent()) {
    ob_start('ob_gzhandler');
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize session with security settings
initSession();

// Check if already logged in and redirect
if (isLoggedIn()) {
    if (isProvider()) {
        redirect('provider/dashboard.php');
    } elseif (isClient()) {
        redirect('client/dashboard.php');
    } elseif (isAdmin()) {
        redirect('admin/dashboard.php');
    }
}

// Check maintenance mode (except for login page)
if (isMaintenanceMode() && !isAdmin()) {
    // Allow access to login page even in maintenance mode
    // but show a warning
    $maintenance_warning = true;
}

// Get platform settings using the new functions
$platform_name = getPlatformName();
$contact_email = getContactEmail();
$copyright_text = getCopyrightText();
$platform_description = getSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

// Check registration settings using new functions
$client_registration_enabled = isClientRegistrationEnabled();
$provider_registration_enabled = isProviderRegistrationEnabled();
$email_verification_enabled = isEmailVerificationEnabled();

$errors = [];
$show_verify_notice = false; // <-- new: only show verification notice when login fails due to verification

// Login attempt limiting disabled
$sessionTimeout = getSessionTimeout() * 60; // Convert minutes to seconds

$ip = getClientIP();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: indicate POST request received
    error_log("POST request received to login.php");

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }
    
    // Check if IP is blocked
    if (isIPBlocked($ip)) {
        $errors[] = 'Access denied. Your IP address has been blocked.';
    }

    $email = getRequestValue('email', '', 'POST');
    $password = $_POST['password'] ?? '';

    // Debug: log the POST data
    error_log("Login attempt: email=$email, password=" . (empty($password) ? 'empty' : 'provided'));

    if (!validateEmailField($email, 'email', $errors)) {
        // error already added
    }

    if (trim((string) $password) === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        // improved error handling & explicit PDO connection check
        $fatalError = false;

        // release session lock while we do DB work (we don't need session yet)
        session_write_close();

        try {
            $db = Database::getInstance()->getConnection();
        } catch (PDOException $pdoe) {
            error_log('DB connection error (login): ' . $pdoe->getMessage());
            if (function_exists('isDebugMode') && isDebugMode()) {
                $errors[] = 'Database connection error: ' . $pdoe->getMessage();
            } else {
                $errors[] = 'An internal error occurred. Please try again later.';
            }
            $fatalError = true;
        }

        if (!$fatalError) {
            try {
                $stmt = $db->prepare('
                    SELECT u.id, u.full_name, u.email, u.password, u.user_type, u.is_verified, 
                           u.is_active, sp.is_active as provider_active, sp.is_banned
                    FROM users u 
                    LEFT JOIN service_providers sp ON u.id = sp.user_id 
                    WHERE u.email = ? LIMIT 1
                ');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $errors[] = 'Invalid email or password.';
                } else {
                    // Admin accounts must login through admin/login.php
                    if ($user['user_type'] === 'admin') {
                        $errors[] = 'Unauthorized access. Admin accounts are not permitted to use this login form.';
                    }
                    // Check if user is active
                    elseif (!$user['is_active']) {
                        $errors[] = 'Your account has been deactivated. Please contact support.';
                    }
                    // Check if provider is banned
                    elseif ($user['user_type'] === 'provider' && $user['is_banned']) {
                        $errors[] = 'Your provider account has been banned. Please contact support.';
                    }
                    // Check if provider is active (if verification is required)
                    elseif ($user['user_type'] === 'provider' && isProviderVerificationRequired() && !$user['provider_active']) {
                        $errors[] = 'Your provider account is pending approval. Please wait for admin approval.';
                    }
                    // Check password
                    elseif (!password_verify($password, $user['password'])) {
                        $errors[] = 'Invalid email or password.';
                    } else {
                        // Check verification if enabled
                        if ($email_verification_enabled && (int)$user['is_verified'] !== 1) {
                            $errors[] = 'Your account is not verified. Please check your email for the verification link.';
                            $show_verify_notice = true; // <-- new: mark to show verification notice
                        } else {
                            // Auth success: reopen session to set session vars and then close
                            session_start();
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_role'] = $user['user_type'];
                            $_SESSION['user_name'] = $user['full_name'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['user_email'] = $user['email'];
                            
                            // Create user session record for tracking
                            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $device = 'Unknown';
                            if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
                                $device = 'Mobile';
                            } elseif (preg_match('/Tablet|iPad/i', $userAgent)) {
                                $device = 'Tablet';
                            } else {
                                $device = 'Desktop';
                            }
                            $sessionId = session_id();
                            
                            try {
                                $sessionStmt = $db->prepare('
                                    INSERT INTO user_sessions (user_id, session_id, device, ip_address, user_agent, login_time, is_active) 
                                    VALUES (?, ?, ?, ?, ?, NOW(), 1)
                                ');
                                $result = $sessionStmt->execute([$user['id'], $sessionId, $device, $ip, $userAgent]);
                                error_log("User session created for user {$user['id']}: " . ($result ? 'SUCCESS' : 'FAILED'));
                            } catch (Exception $e) {
                                error_log("Failed to create user session: " . $e->getMessage());
                            }
                            
                            // Update last login timestamp
                            $updateStmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                            $updateStmt->execute([$user['id']]);
                            
                            session_write_close();

                            // Log login activity
                            error_log("User {$user['email']} logged in successfully from IP: {$ip}");

                            // Safe redirect: if a "next" param exists, validate it's a local/internal path (no scheme/host)
                            $nextRaw = '';
                            if (!empty($_POST['next'])) {
                                $nextRaw = $_POST['next'];
                            } elseif (!empty($_GET['next'])) {
                                $nextRaw = $_GET['next'];
                            } elseif (!empty($nextParam)) {
                                $nextRaw = $nextParam;
                            }

                            if (!empty($nextRaw)) {
                                // decode and normalize
                                $decoded = rawurldecode($nextRaw);
                                $parsed = parse_url($decoded);
                                $isLocal = !isset($parsed['scheme']) && !isset($parsed['host']) && strpos($decoded, '..') === false;
                                if ($isLocal) {
                                    header('Location: ' . $decoded);
                                    exit;
                                }
                            }

                            // Redirect to appropriate dashboard based on user type (fallback)
                            if ($user['user_type'] === 'provider') {
                                header('Location: provider/dashboard.php');
                                exit;
                            } else {
                                // Default for client user type
                                header('Location: client/dashboard.php');
                                exit;
                            }
                        }
                    }
                }

            } catch (Throwable $e) {
                // log full trace for debugging
                error_log('Login error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                if (function_exists('isDebugMode') && isDebugMode()) {
                    $errors[] = 'An internal error occurred: ' . $e->getMessage();
                } else {
                    $errors[] = 'An internal error occurred. Please try again later.';
                }
            }
        }
    }
} else {
    // for GET requests we can release session lock early to avoid blocking other requests
    session_write_close();
}

// preserve incoming next param for form and processing
$nextParam = '';
if (isset($_REQUEST['next'])) {
    $nextParam = $_REQUEST['next'];
}

$csrf_token = ensureCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-..." crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text-muted: #94a3b8;
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --shadow-sm: 0 1px 3px rgba(15,23,42,0.06), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-md: 0 4px 16px rgba(15,23,42,0.08), 0 2px 6px rgba(15,23,42,0.05);
            --shadow-lg: 0 20px 60px rgba(15,23,42,0.12), 0 8px 20px rgba(15,23,42,0.07);
            --shadow-primary: 0 8px 24px rgba(37,99,235,0.28);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--dark);
            background: var(--surface-2);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        h1,h2,h3,h4,h5,h6,.navbar-brand,.login-title {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226,232,240,0.8);
            padding: 0.85rem 0;
        }

        .navbar-brand {
            font-size: 1.35rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            text-decoration: none;
        }

        .navbar .nav-link {
            font-weight: 500;
            font-size: 0.93rem;
            color: var(--secondary) !important;
            padding: 0.5rem 0.9rem !important;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .navbar .nav-link:hover, .navbar .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }

        .navbar .btn-primary {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.5rem 1.3rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-primary);
            border: none;
            transition: all 0.25s ease;
        }

        .navbar .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(37,99,235,0.30);
        }

        .navbar-toggler {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0.4rem 0.65rem;
        }

        /* ── LOGIN LAYOUT ── */
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }

        .login-panel {
            width: 100%;
            max-width: 460px;
            animation: slideUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── BRAND MARK (above card) ── */
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: var(--shadow-primary);
            margin-bottom: 1rem;
        }

        .login-title {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--dark);
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            font-size: 0.92rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        /* ── LOGIN CARD ── */
        .login-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .login-body {
            padding: 2.25rem 2.25rem 2rem;
        }

        /* ── FORM ELEMENTS ── */
        .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--secondary);
            margin-bottom: 0.45rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            z-index: 5;
            pointer-events: none;
        }

        .form-control {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.82rem 1rem 0.82rem 2.75rem;
            font-size: 0.95rem;
            font-weight: 500;
            background: var(--surface-2);
            color: var(--dark);
            transition: all 0.22s ease;
            width: 100%;
        }

        .form-control:hover {
            border-color: #cbd5e1;
            background: var(--surface);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
            background: var(--surface);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger);
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
        }

        /* password toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.88rem;
            cursor: pointer;
            z-index: 5;
            transition: color 0.2s ease;
            background: none;
            border: none;
            padding: 0;
        }

        .password-toggle:hover { color: var(--primary); }

        /* ── SUBMIT BUTTON ── */
        .btn-login {
            width: 100%;
            padding: 0.9rem;
            border-radius: var(--radius-md);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 0.97rem;
            letter-spacing: 0.01em;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-primary);
            position: relative;
            overflow: hidden;
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.14), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(37,99,235,0.35);
            color: white;
        }

        .btn-login:hover::after { left: 100%; }

        .btn-login:active { transform: translateY(0); }

        .btn-login:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── DIVIDER ── */
        .form-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.06em;
        }

        .form-divider::before,
        .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── LINKS ── */
        .login-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .login-link:hover { color: var(--primary-dark); text-decoration: underline; }

        .login-footer-text {
            font-size: 0.88rem;
            color: var(--secondary);
            text-align: center;
            margin-bottom: 0.6rem;
        }

        .login-footer-text:last-child { margin-bottom: 0; }

        /* ── ALERTS ── */
        .alert {
            border-radius: var(--radius-md);
            border: none;
            padding: 0.9rem 1.1rem;
            font-size: 0.9rem;
            line-height: 1.55;
            margin-bottom: 1.25rem;
        }

        .alert p { margin-bottom: 0.3rem; }
        .alert p:last-child { margin-bottom: 0; }

        .alert-danger {
            background: rgba(239,68,68,0.08);
            border: 1.5px solid rgba(239,68,68,0.22);
            color: #b91c1c;
        }

        .alert-info {
            background: rgba(37,99,235,0.07);
            border: 1.5px solid rgba(37,99,235,0.2);
            color: var(--primary-dark);
        }

        .maintenance-warning {
            background: rgba(245,158,11,0.1);
            border: 1.5px solid rgba(245,158,11,0.28);
            color: #92400e;
        }

        /* ── TRUST BADGES ── */
        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            margin-top: 1.75rem;
            flex-wrap: wrap;
        }

        .trust-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .trust-badge i { color: var(--success); font-size: 0.82rem; }

        /* ── FOOTER ── */
        .footer {
            background: var(--dark) !important;
            padding: 1.75rem 0;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: 0;
        }

        .footer h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
        }

        .footer .text-muted { color: #64748b !important; font-size: 0.85rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 480px) {
            .login-body { padding: 1.75rem 1.5rem; }
            .login-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <span style="width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:9px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-map-marked-alt text-white" style="font-size:0.85rem;"></i>
                </span>
                <?php echo htmlspecialchars($platform_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-1">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="providers.php">Find Providers</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link active" href="login.php">Sign In</a></li>
                        <li class="nav-item ms-1">
                            <a class="btn btn-primary" href="register.php">Get Started &rarr;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="login-wrapper">
        <div class="login-panel">

            <!-- Brand mark -->
            <div class="login-brand">
                <div class="login-brand-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h1 class="login-title">Welcome back</h1>
                <p class="login-subtitle">Sign in to your <?php echo htmlspecialchars($platform_name); ?> account</p>
            </div>

            <!-- Card -->
            <div class="login-card">
                <div class="login-body">

                    <?php if (isset($maintenance_warning) && $maintenance_warning): ?>
                        <div class="alert maintenance-warning">
                            <i class="fas fa-tools me-2"></i>
                            <strong>Maintenance Mode Active</strong>
                            <p class="mb-0 mt-1">The platform is under maintenance. Some features may be unavailable.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$client_registration_enabled && !$provider_registration_enabled): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Registration closed</strong>
                            <p class="mb-0 mt-1">New account registration is currently disabled. Existing users can still sign in.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($email_verification_enabled && $show_verify_notice): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-envelope me-2"></i>
                            <strong>Check your inbox</strong>
                            <p class="mb-0 mt-1">Your email isn't verified yet. Please click the verification link we sent you.</p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="next" value="<?php echo isset($_GET['next']) ? htmlspecialchars($_GET['next']) : (isset($nextParam) ? htmlspecialchars($nextParam) : ''); ?>">

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="your@email.com"
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                                       required autocomplete="email"
                                       <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label mb-0">Password</label>
                                <a href="forgot-password.php" class="login-link" style="font-size:0.8rem;">Forgot password?</a>
                            </div>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Enter your password"
                                       required autocomplete="current-password"
                                       <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                                <button type="button" class="password-toggle" id="togglePassword" tabindex="-1" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4" style="margin-top:1.5rem;">
                            <button type="submit" class="btn-login"
                                <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                                <?php if (isMaintenanceMode() && !isAdmin()): ?>
                                    <i class="fas fa-ban me-2"></i>Login Disabled During Maintenance
                                <?php else: ?>
                                    <i class="fas fa-arrow-right-to-bracket me-2"></i>Sign In
                                <?php endif; ?>
                            </button>
                        </div>
                    </form>

                    <!-- Footer links -->
                    <div class="form-divider">or</div>

                    <p class="login-footer-text">
                        Don't have an account?
                        <?php if ($client_registration_enabled || $provider_registration_enabled): ?>
                            <a href="register.php" class="login-link">Create one free &rarr;</a>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">Registration temporarily closed</span>
                        <?php endif; ?>
                    </p>

                </div>
            </div>

            <!-- Trust badges -->
            <div class="trust-badges">
                <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure Login</span>
                <span class="trust-badge"><i class="fas fa-lock"></i> Encrypted</span>
                <span class="trust-badge"><i class="fas fa-check-circle"></i> Verified Platform</span>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted mb-0" style="font-size:0.82rem;"><?php echo htmlspecialchars($copyright_text); ?></p>
                </div>
                <div class="text-muted" style="font-size:0.82rem; text-align:right;">
                    <a href="index.php" style="color:#64748b;text-decoration:none;margin-right:1rem;">Home</a>
                    <a href="about.php" style="color:#64748b;text-decoration:none;margin-right:1rem;">About</a>
                    <a href="contact.php" style="color:#64748b;text-decoration:none;">Contact</a>
                    <?php if (isDebugMode()): ?>
                        <br><small class="text-muted">Debug Mode: ON | IP: <?php echo htmlspecialchars($ip); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
    <script>
        // Navbar scroll shadow
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 10
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleIcon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }

        // Focus email on load
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (!isMaintenanceMode() || isAdmin()): ?>
                const emailField = document.getElementById('email');
                if (emailField && !emailField.value) emailField.focus();
            <?php endif; ?>
        });

        // Auto-dismiss alerts after 6 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease, max-height 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-6px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 6000);

        // Prevent maintenance mode submission
        document.getElementById('loginForm')?.addEventListener('submit', function (e) {
            <?php if (isMaintenanceMode() && !isAdmin()): ?>
                e.preventDefault();
            <?php endif; ?>
        });

        // Subtle button loading state
        document.getElementById('loginForm')?.addEventListener('submit', function () {
            const btn = this.querySelector('.btn-login');
            if (btn && !btn.disabled) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in…';
                btn.style.opacity = '0.8';
                btn.style.pointerEvents = 'none';
            }
        });
    </script>
</body>
</html>