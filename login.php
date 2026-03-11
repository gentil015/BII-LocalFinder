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
    // Check if IP is blocked
    if (isIPBlocked($ip)) {
        $errors[] = 'Access denied. Your IP address has been blocked.';
    }

    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please enter both email and password.';
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
                    // Check if user is active
                    if (!$user['is_active']) {
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
                            } elseif ($user['user_type'] === 'admin') {
                                header('Location: admin/dashboard.php');
                                exit;
                            } else {
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
    // do not sanitize/comment out here; store raw encoded path and validate before redirect
    $nextParam = $_REQUEST['next'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-..." crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-radius: 8px 0 0 8px;
        }
        
        .btn-login {
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .footer {
            margin-top: auto;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .brand-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .login-info {
            background-color: #e7f3ff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .login-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .maintenance-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="index.php">
                <i class="fas fa-map-marked-alt me-2"></i>
                <?php echo htmlspecialchars($platform_name); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="providers.php">Find Providers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isProvider() ? 'provider/dashboard.php' : 'client/dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="login.php">Login</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="brand-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h4 class="mb-0">Login to Your Account</h4>
            </div>
            
            <div class="login-body">
                <?php if (isset($maintenance_warning) && $maintenance_warning): ?>
                    <div class="alert maintenance-warning">
                        <i class="fas fa-tools me-2"></i>
                        <strong>Maintenance Mode</strong>
                        <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be unavailable.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$client_registration_enabled && !$provider_registration_enabled): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Registration Notice</strong>
                        <p class="mb-0 mt-2">New account registration is currently disabled. Existing users can still login.</p>
                    </div>
                <?php endif; ?>

                <?php if ($email_verification_enabled && $show_verify_notice): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-envelope me-2"></i>
                        <strong>Email Verification Required</strong>
                        <p class="mb-0 mt-2">Your email is not verified. Please check your email for the verification link.</p>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <input type="hidden" name="next" value="<?php echo isset($_GET['next']) ? htmlspecialchars($_GET['next']) : (isset($nextParam) ? htmlspecialchars($nextParam) : ''); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="your@email.com" 
                                   value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" 
                                   required
                                   <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" 
                                   required
                                   <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login w-100 mb-3" 
                        <?php echo isMaintenanceMode() && !isAdmin() ? 'disabled' : ''; ?>>
                        <i class="fas fa-sign-in-alt me-2"></i> 
                        <?php echo isMaintenanceMode() && !isAdmin() ? 'Login Disabled' : 'Login'; ?>
                    </button>
                </form>

                <div class="text-center">
                    <p class="mb-2">
                        Don't have an account? 
                        <?php if ($client_registration_enabled || $provider_registration_enabled): ?>
                            <a href="register.php" class="text-primary text-decoration-none fw-semibold">Register</a>
                        <?php else: ?>
                            <span class="text-muted">Registration temporarily closed</span>
                        <?php endif; ?>
                    </p>
                    <p class="mb-0">
                        Forgot your password? 
                        <a href="forgot-password.php" class="text-primary text-decoration-none fw-semibold">Reset it</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3"><?php echo htmlspecialchars($platform_name); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($platform_description); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
                    <?php if (isDebugMode()): ?>
                        <small class="text-muted">Debug Mode: ON | IP: <?php echo htmlspecialchars($ip); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Focus on email field when page loads (if not in maintenance mode)
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!isMaintenanceMode() || isAdmin()): ?>
                document.getElementById('email').focus();
            <?php endif; ?>
        });

        // Login attempt limiting disabled

        // Prevent form submission if in maintenance mode (for non-admins)
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            <?php if (isMaintenanceMode() && !isAdmin()): ?>
                e.preventDefault();
                alert('Login is temporarily disabled during maintenance. Please try again later.');
            <?php endif; ?>
        });
    </script>
</body>
</html>