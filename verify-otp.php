<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Load platform settings
function getPlatformSetting($key, $default = '') {
    global $db;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    return $settings[$key] ?? $default;
}

$db = Database::getInstance()->getConnection();

// Get platform settings
$platform_name = getPlatformSetting('platform_name', 'BII LocalFinder');
$contact_email = getPlatformSetting('contact_email', 'info@biilocalfinder.com');
$copyright_text = getPlatformSetting('copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
$platform_description = getPlatformSetting('platform_description', 'Connecting skilled professionals with clients across Rwanda');

$message = '';
$error = '';
$success = '';

// Check if user came from password reset
$is_password_reset = isset($_SESSION['reset_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $otp = sanitize($_POST['otp']);

    // Validate OTP format
    if (!preg_match('/^\d{6}$/', $otp)) {
        $error = 'Invalid OTP format. Please enter a 6-digit code.';
    } else {
        $stmt = $db->prepare("SELECT id, otp_code, otp_expires_at, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if account is active
            if (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact support.';
            } elseif ($user['otp_code'] === $otp) {
                if (strtotime($user['otp_expires_at']) > time()) {
                    if ($is_password_reset) {
                        // For password reset - store verification in session and redirect to reset password
                        $_SESSION['otp_verified'] = true;
                        $_SESSION['reset_user_id'] = $user['id'];
                        $_SESSION['reset_email'] = $email;
                        
                        // Clear OTP after successful verification
                        $update = $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                        $update->execute([$user['id']]);
                        
                        header('Location: reset-password.php');
                        exit;
                    } else {
                        // For account verification - mark as verified
                        $update = $db->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                        if ($update->execute([$user['id']])) {
                            $success = 'Account verified successfully! You can now login to your account.';
                            
                            // Log verification
                            $logStmt = $db->prepare('INSERT INTO security_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)');
                            $logStmt->execute([$user['id'], 'account_verified', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                        } else {
                            $error = 'Failed to verify account. Please try again.';
                        }
                    }
                } else {
                    $error = 'OTP has expired. Please request a new verification code.';
                }
            } else {
                $error = 'Invalid OTP code. Please check and try again.';
                
                // Track failed attempts for security
                if ($is_password_reset && isset($_SESSION['reset_attempts'])) {
                    $_SESSION['reset_attempts']++;
                    $_SESSION['reset_last_attempt'] = time();
                    
                    if ($_SESSION['reset_attempts'] >= 5) {
                        $error = 'Too many failed attempts. Please request a new OTP.';
                        unset($_SESSION['reset_email']);
                        unset($_SESSION['reset_attempts']);
                    }
                }
            }
        } else {
            $error = 'Email address not found.';
        }
    }
}

// Pre-fill email from session if available
$prefilled_email = '';
if ($is_password_reset && isset($_SESSION['reset_email'])) {
    $prefilled_email = $_SESSION['reset_email'];
} elseif (isset($_POST['email'])) {
    $prefilled_email = $_POST['email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - <?php echo htmlspecialchars($platform_name); ?></title>
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
        
        .otp-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .otp-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            background: white;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .form-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .form-header h2 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-header p {
            margin: 0;
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
            font-size: 1.1rem;
            text-align: center;
            letter-spacing: 0.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-radius: 8px 0 0 8px;
        }
        
        .btn-primary {
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .back-to-login a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
        }
        
        .back-to-login a:hover {
            color: #0a58ca;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.25rem;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .footer {
            margin-top: auto;
        }
        
        .otp-info {
            background-color: #e7f3ff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .otp-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        .resend-otp {
            text-align: center;
            margin-top: 1rem;
        }
        
        .resend-otp a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .resend-otp a:hover {
            text-decoration: underline;
        }
        
        /* Loading animation */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* OTP input styling */
        .otp-input {
            font-size: 1.5rem !important;
            font-weight: 600;
            letter-spacing: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .form-header {
                padding: 2rem 1.5rem;
            }
            
            .form-body {
                padding: 1.5rem;
            }
            
            .form-icon {
                font-size: 2.5rem;
            }
            
            .form-control {
                font-size: 1rem;
                letter-spacing: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="otp-container">
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
                                <a class="nav-link" href="login.php">Login</a>
                            </li>
                            <li class="nav-item ms-2">
                                <a class="btn btn-primary" href="register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- OTP Verification Form -->
        <div class="form-wrapper">
            <div class="otp-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2>Verify Your Account</h2>
                    <p>
                        <?php echo $is_password_reset ? 'Enter the 6-digit code sent to your email to reset your password.' : 'Enter the 6-digit verification code sent to your email.'; ?>
                    </p>
                </div>
                
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <div class="mt-2">
                                <a href="login.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="otp-info">
                        <p><strong><i class="fas fa-info-circle me-2"></i>Verification Code:</strong></p>
                        <p class="mb-1">• Check your email for the 6-digit code</p>
                        <p class="mb-1">• The code expires in 30 minutes</p>
                        <p class="mb-0">• Enter the code exactly as received</p>
                    </div>

                    <form method="POST" id="otpForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="your@email.com" 
                                       value="<?php echo htmlspecialchars($prefilled_email); ?>" 
                                       <?php echo $is_password_reset ? 'readonly' : 'required'; ?>>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="otp" class="form-label">Verification Code</label>
                            <input type="text" class="form-control otp-input" id="otp" name="otp" 
                                   required maxlength="6" placeholder="000000"
                                   pattern="\d{6}" title="Please enter a 6-digit code">
                            <div class="form-text">
                                Enter the 6-digit code sent to your email address.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $is_password_reset ? 'Verify & Reset Password' : 'Verify Account'; ?>
                        </button>
                    </form>

                    <div class="resend-otp">
                        <p class="text-muted mb-2">
                            Didn't receive the code?
                            <a href="<?php echo $is_password_reset ? 'forgot-password.php' : 'register.php'; ?>" class="ms-1">
                                Request a new one
                            </a>
                        </p>
                    </div>

                    <div class="back-to-login">
                        <a href="login.php">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer bg-dark text-white py-4">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3"><?php echo htmlspecialchars($platform_name); ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($platform_description); ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($copyright_text); ?></p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-..." crossorigin="anonymous"></script>
    <script>
        // Form validation and enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('otpForm');
            const otpInput = document.getElementById('otp');
            const emailInput = document.getElementById('email');
            const submitBtn = form.querySelector('button[type="submit"]');

            // Auto-advance OTP input
            otpInput.addEventListener('input', function() {
                // Remove non-digit characters
                this.value = this.value.replace(/\D/g, '');
                
                // Auto-format with spaces (optional)
                if (this.value.length === 6) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = '';
                }
            });

            // Allow only numbers in OTP field
            otpInput.addEventListener('keypress', function(e) {
                const charCode = e.which ? e.which : e.keyCode;
                if (charCode < 48 || charCode > 57) {
                    e.preventDefault();
                    return false;
                }
            });

            // Form submission enhancement
            form.addEventListener('submit', function(e) {
                const otp = otpInput.value.trim();
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email || !emailRegex.test(email)) {
                    e.preventDefault();
                    emailInput.style.borderColor = 'var(--danger)';
                    emailInput.focus();
                    return;
                }

                if (!otp || otp.length !== 6 || !/^\d+$/.test(otp)) {
                    e.preventDefault();
                    otpInput.style.borderColor = 'var(--danger)';
                    otpInput.focus();
                    return;
                }

                // Add loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying...';
            });

            // Auto-focus OTP input if email is prefilled
            if (emailInput.value) {
                setTimeout(() => otpInput.focus(), 500);
            }
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>