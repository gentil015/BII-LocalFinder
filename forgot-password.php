<?php
session_start();
require_once 'config/database.php';
require_once 'includes/mailer.php';
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            // Check if user exists and is active
            $stmt = $db->prepare('SELECT id, full_name, email, is_active FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if user account is active
                if (!$user['is_active']) {
                    $error = 'This account has been deactivated. Please contact support.';
                } else {
                    // Generate 6-digit OTP
                    $otp = sprintf("%06d", mt_rand(1, 999999));
                    $otp_expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    
                    // Store OTP in database
                    $stmt = $db->prepare('UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE email = ?');
                    $stmt->execute([$otp, $otp_expires, $email]);
                    
                    // Send OTP via email (catch mailer exceptions)
                    try {
                        $mailSent = Mailer::sendPasswordResetOTP($email, $user['full_name'], $otp, 30);
                    } catch (Exception $e) {
                        error_log("Mailer exception while sending OTP to {$email}: " . $e->getMessage());
                        $mailSent = false;
                    }

                    if ($mailSent) {
                        // Store email in session for verification
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_attempts'] = 0;
                        $_SESSION['reset_last_attempt'] = time();
                        
                        // Try to log the password reset request — non-fatal if it fails
                        try {
                            $logStmt = $db->prepare('INSERT INTO security_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)');
                            $logStmt->execute([$user['id'], 'password_reset_request', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                        } catch (Exception $e) {
                            error_log("Failed to insert security log for password reset (user_id={$user['id']}): " . $e->getMessage());
                            // don't convert to user-facing error because email was already sent
                        }
                        
                        header('Location: verify-otp.php');
                        exit;
                    } else {
                        // Revert stored OTP if mail sending failed (optional)
                        try {
                            $stmt = $db->prepare('UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE email = ?');
                            $stmt->execute([$email]);
                        } catch (Exception $e) {
                            error_log("Failed to clear OTP after mail failure for {$email}: " . $e->getMessage());
                        }

                        $error = 'Failed to send verification code. Please try again.';
                    }
                }
            } else {
                $error = 'No account found with this email address.';
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo htmlspecialchars($platform_name); ?></title>
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
        
        .forgot-password-container {
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
        
        .forgot-password-card {
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
        
        .security-info {
            background-color: #e7f3ff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .security-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--dark);
        }
        
        /* Loading animation */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
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

        <!-- Forgot Password Form -->
        <div class="form-wrapper">
            <div class="forgot-password-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h2>Reset Your Password</h2>
                    <p>Enter your email address and we'll send you a verification code to reset your password.</p>
                </div>
                
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="security-info">
                        <p><strong><i class="fas fa-shield-alt me-2"></i>Security Notice:</strong></p>
                        <p class="mb-1">• A 6-digit verification code will be sent to your email</p>
                        <p class="mb-1">• The code expires in 30 minutes</p>
                        <p class="mb-0">• If you don't receive the email, check your spam folder</p>
                    </div>

                    <form method="POST" id="forgotPasswordForm">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="your@email.com" 
                                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                            </div>
                            <div class="form-text">
                                Enter the email address associated with your <?php echo htmlspecialchars($platform_name); ?> account.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-4">
                            <i class="fas fa-paper-plane me-2"></i>
                            Send Verification Code
                        </button>
                    </form>

                    <div class="back-to-login">
                        <a href="login.php">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Login
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Need help? <a href="contact.php" class="text-decoration-none">Contact Support</a>
                        </small>
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
            const form = document.getElementById('forgotPasswordForm');
            const emailInput = document.getElementById('email');
            const submitBtn = form.querySelector('button[type="submit"]');

            // Real-time email validation
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = '';
                }
            });

            // Form submission enhancement
            form.addEventListener('submit', function(e) {
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email || !emailRegex.test(email)) {
                    e.preventDefault();
                    emailInput.style.borderColor = 'var(--danger)';
                    emailInput.focus();
                    return;
                }

                // Add loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending Code...';
            });

            // Auto-focus email input
            setTimeout(() => emailInput.focus(), 500);
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>