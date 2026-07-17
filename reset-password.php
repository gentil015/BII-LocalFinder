<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Enhanced security checks
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified'])) {
    header('Location: forgot-password.php');
    exit;
}

// Check if OTP is still valid (15-minute expiry)
if (isset($_SESSION['otp_verified_at'])) {
    $otp_verified_at = $_SESSION['otp_verified_at'];
    $current_time = time();
    $time_diff = $current_time - $otp_verified_at;
    
    // OTP expires after 15 minutes
    if ($time_diff > 900) { // 15 minutes in seconds
        unset($_SESSION['reset_email']);
        unset($_SESSION['otp_verified']);
        unset($_SESSION['otp_verified_at']);
        unset($_SESSION['reset_attempts']);
        unset($_SESSION['reset_last_attempt']);
        
        header('Location: forgot-password.php?error=otp_expired');
        exit;
    }
}

// Get system settings for platform name and password requirements
$platform_name = "BII LocalFinder";
$min_password_length = 6;
$require_uppercase = true;
$require_lowercase = true;
$require_numbers = true;
$require_special_chars = false;

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('platform_name', 'min_password_length', 'require_uppercase', 'require_lowercase', 'require_numbers', 'require_special_chars')");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        switch ($row['setting_key']) {
            case 'platform_name':
                $platform_name = $row['setting_value'];
                break;
            case 'min_password_length':
                $min_password_length = intval($row['setting_value']);
                break;
            case 'require_uppercase':
                $require_uppercase = (bool)$row['setting_value'];
                break;
            case 'require_lowercase':
                $require_lowercase = (bool)$row['setting_value'];
                break;
            case 'require_numbers':
                $require_numbers = (bool)$row['setting_value'];
                break;
            case 'require_special_chars':
                $require_special_chars = (bool)$row['setting_value'];
                break;
        }
    }
} catch (Exception $e) {
    // Use defaults if error
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = $_SESSION['reset_email'];
    
    // Enhanced validation
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Validate password strength
        if (strlen($password) < $min_password_length) {
            $error = "Password must be at least {$min_password_length} characters long.";
        } elseif ($require_special_chars && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $error = "Password must contain at least one special character.";
        } elseif ($require_uppercase && !preg_match('/[A-Z]/', $password)) {
            $error = "Password must contain at least one uppercase letter.";
        } elseif ($require_lowercase && !preg_match('/[a-z]/', $password)) {
            $error = "Password must contain at least one lowercase letter.";
        } elseif ($require_numbers && !preg_match('/[0-9]/', $password)) {
            $error = "Password must contain at least one number.";
        } else {
            try {
                // Hash new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password and clear OTP fields
                $stmt = $db->prepare('UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL, updated_at = NOW() WHERE email = ?');
                $stmt->execute([$hashed_password, $email]);
                
                // Clear session
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_verified']);
                unset($_SESSION['otp_verified_at']);
                unset($_SESSION['reset_attempts']);
                unset($_SESSION['reset_last_attempt']);
                
                $success = 'Password reset successfully! You can now login with your new password.';
                
                // Log the password reset activity (non-blocking - don't fail if logging fails)
                try {
                    $user_id = null;
                    $user_stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
                    $user_stmt->execute([$email]);
                    $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user_result) {
                        $user_id = $user_result['id'];
                        
                        // Log the activity
                        $log_stmt = $db->prepare('INSERT INTO user_activity (user_id, activity_type, description, ip_address) VALUES (?, ?, ?, ?)');
                        $log_stmt->execute([
                            $user_id,
                            'password_reset',
                            'Password reset successfully via OTP verification',
                            $_SERVER['REMOTE_ADDR']
                        ]);
                    }
                } catch (Exception $log_error) {
                    // Log activity logging failure but don't prevent password reset success
                    error_log("Activity logging error during password reset: " . $log_error->getMessage());
                }
                
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'An error occurred while resetting your password. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95) !important;
        }
        
        .reset-password-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .reset-password-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            background: white;
            backdrop-filter: blur(10px);
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .form-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .form-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .form-header h2 {
            margin-bottom: 0.5rem;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }
        
        .form-header p {
            margin: 0;
            opacity: 0.9;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-top: 1rem;
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }
        
        .form-body {
            padding: 2.5rem 2rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-primary {
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            border: none;
            font-size: 1.1rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .requirement-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .requirement-list li.valid {
            color: var(--success);
        }
        
        .requirement-list li.invalid {
            color: var(--secondary);
        }
        
        .requirement-list i {
            font-size: 0.8rem;
        }
        
        .password-strength {
            margin-top: 1rem;
        }
        
        .strength-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            margin-top: 0.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .strength-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .strength-fill.strength-weak {
            width: 25%;
            background: linear-gradient(135deg, var(--danger), #e74c3c);
        }
        
        .strength-fill.strength-medium {
            width: 50%;
            background: linear-gradient(135deg, var(--warning), #f39c12);
        }
        
        .strength-fill.strength-strong {
            width: 75%;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
        }
        
        .strength-fill.strength-very-strong {
            width: 100%;
            background: linear-gradient(135deg, var(--success), #27ae60);
        }
        
        #strength-text {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        #password-match {
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .login-link a:hover {
            color: #0a58ca;
            background-color: #f8f9fa;
            transform: translateX(-5px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }
        
        .timer-warning {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .timer-warning i {
            color: var(--warning);
            font-size: 1.2rem;
        }
        
        .footer {
            margin-top: auto;
            background: rgba(0, 0, 0, 0.8) !important;
            backdrop-filter: blur(10px);
        }
        
        /* Loading animation */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Password toggle */
        .password-toggle {
            background: none;
            border: none;
            color: var(--secondary);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .form-header {
                padding: 2rem 1.5rem;
            }
            
            .form-body {
                padding: 2rem 1.5rem;
            }
            
            .form-icon {
                font-size: 3rem;
            }
            
            .password-requirements {
                padding: 1rem;
            }
        }
        
        @media (max-width: 400px) {
            .form-body {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center fw-bold text-primary" href="index.php">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    <?php echo htmlspecialchars($platform_name); ?>
                </a>
            </div>
        </nav>

        <!-- Reset Password Form -->
        <div class="form-wrapper">
            <div class="reset-password-card">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h2>Create New Password</h2>
                    <p>Enter your new password below. Make sure it's strong and secure.</p>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Password Reset</span>
                    </div>
                </div>
                
                <div class="form-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle me-3 fa-2x"></i>
                                <div>
                                    <h5 class="alert-heading mb-2">Password Reset Successful!</h5>
                                    <?php echo $success; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Continue to Login
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Session Timer Warning -->
                        <div class="timer-warning">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Session expires in: <span id="session-timer">15:00</span></strong>
                                <br>
                                <small>Complete the password reset before the session expires for security reasons.</small>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle me-3"></i>
                                    <div><?php echo $error; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="resetPasswordForm" 
                              data-min-length="<?php echo $min_password_length; ?>"
                              data-require-uppercase="<?php echo $require_uppercase ? 'true' : 'false'; ?>"
                              data-require-lowercase="<?php echo $require_lowercase ? 'true' : 'false'; ?>"
                              data-require-numbers="<?php echo $require_numbers ? 'true' : 'false'; ?>"
                              data-require-special="<?php echo $require_special_chars ? 'true' : 'false'; ?>">
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your new password" minlength="6" required
                                           autocomplete="new-password">
                                    <button type="button" class="btn password-toggle input-group-text" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                                <!-- Password Strength Indicator -->
                                <div class="password-strength">
                                    <div>Password strength: <span id="strength-text">None</span></div>
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strength-fill"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm your new password" minlength="6" required
                                           autocomplete="new-password">
                                    <button type="button" class="btn password-toggle input-group-text" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match" class="mt-2"></div>
                            </div>

                            <!-- Password Requirements -->
                            <div class="password-requirements">
                                <h6 class="fw-semibold mb-3"><i class="fas fa-list-check me-2"></i>Password Requirements</h6>
                                <ul class="requirement-list" id="requirement-list">
                                    <li class="invalid" id="req-length" style="<?php echo $min_password_length > 0 ? '' : 'display:none;'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <span>At least <?php echo $min_password_length; ?> characters</span>
                                    </li>
                                    <li class="invalid" id="req-uppercase" style="<?php echo $require_uppercase ? '' : 'display:none;'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <span>One uppercase letter</span>
                                    </li>
                                    <li class="invalid" id="req-lowercase" style="<?php echo $require_lowercase ? '' : 'display:none;'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <span>One lowercase letter</span>
                                    </li>
                                    <li class="invalid" id="req-number" style="<?php echo $require_numbers ? '' : 'display:none;'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <span>One number</span>
                                    </li>
                                    <li class="invalid" id="req-special" style="<?php echo $require_special_chars ? '' : 'display:none;'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <span>One special character</span>
                                    </li>
                                </ul>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 mb-4">
                                <i class="fas fa-key me-2"></i>
                                <span class="btn-text">Reset Password</span>
                                <span class="loading-spinner" style="display: none;">
                                    <i class="fas fa-spinner fa-spin me-2"></i>Processing...
                                </span>
                            </button>
                        </form>

                        <div class="login-link">
                            <a href="login.php">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer text-white py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-2"><?php echo htmlspecialchars($platform_name); ?></h5>
                        <p class="text-light mb-0 opacity-75">Connecting skilled professionals with clients across Rwanda</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="text-light mb-0 opacity-75">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($platform_name); ?>. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthText = document.getElementById('strength-text');
            const strengthFill = document.getElementById('strength-fill');
            const passwordMatch = document.getElementById('password-match');
            const form = document.getElementById('resetPasswordForm');
            const submitBtn = form?.querySelector('button[type="submit"]');
            const btnText = submitBtn?.querySelector('.btn-text');
            const loadingSpinner = submitBtn?.querySelector('.loading-spinner');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

            // Get password requirements from form data attributes
            const minLength = parseInt(form?.dataset.minLength || '6');
            const requireUppercase = form?.dataset.requireUppercase === 'true';
            const requireLowercase = form?.dataset.requireLowercase === 'true';
            const requireNumbers = form?.dataset.requireNumbers === 'true';
            const requireSpecial = form?.dataset.requireSpecial === 'true';

            // Session timer
            let sessionTime = 15 * 60; // 15 minutes in seconds
            const timerElement = document.getElementById('session-timer');
            
            function updateTimer() {
                if (sessionTime <= 0) {
                    timerElement.textContent = '00:00';
                    alert('Your session has expired. Please restart the password reset process.');
                    window.location.href = 'forgot-password.php?error=session_expired';
                    return;
                }
                
                const minutes = Math.floor(sessionTime / 60);
                const seconds = sessionTime % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                sessionTime--;
            }
            
            // Update timer every second
            const timerInterval = setInterval(updateTimer, 1000);
            updateTimer(); // Initial call

            // Password toggle functionality
            function setupPasswordToggle(button, input) {
                button.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    button.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            }

            if (togglePasswordBtn && passwordInput) {
                setupPasswordToggle(togglePasswordBtn, passwordInput);
            }

            if (toggleConfirmPasswordBtn && confirmInput) {
                setupPasswordToggle(toggleConfirmPasswordBtn, confirmInput);
            }

            // Password strength checker with dynamic requirements
            function checkPasswordRequirements(password) {
                const requirements = {
                    length: password.length >= minLength,
                    uppercase: !requireUppercase || /[A-Z]/.test(password),
                    lowercase: !requireLowercase || /[a-z]/.test(password),
                    number: !requireNumbers || /[0-9]/.test(password),
                    special: !requireSpecial || /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                // Update requirement list
                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById(`req-${req}`);
                    if (element && element.style.display !== 'none') {
                        if (requirements[req]) {
                            element.className = 'valid';
                            element.innerHTML = '<i class="fas fa-check-circle"></i><span>' + element.textContent + '</span>';
                        } else {
                            element.className = 'invalid';
                            element.innerHTML = '<i class="fas fa-circle"></i><span>' + element.textContent + '</span>';
                        }
                    }
                });

                // Calculate strength based on met requirements
                const totalRequirements = Object.values(requirements).length;
                const metRequirements = Object.values(requirements).filter(Boolean).length;
                const strength = Math.round((metRequirements / totalRequirements) * 5);
                
                let text = 'None';
                let fillClass = '';

                switch (strength) {
                    case 0:
                    case 1:
                        text = 'Weak';
                        fillClass = 'strength-weak';
                        break;
                    case 2:
                    case 3:
                        text = 'Medium';
                        fillClass = 'strength-medium';
                        break;
                    case 4:
                        text = 'Strong';
                        fillClass = 'strength-strong';
                        break;
                    case 5:
                        text = 'Very Strong';
                        fillClass = 'strength-very-strong';
                        break;
                }

                strengthText.textContent = text;
                strengthText.className = '';
                
                switch (strength) {
                    case 0:
                    case 1:
                        strengthText.classList.add('text-danger');
                        break;
                    case 2:
                    case 3:
                        strengthText.classList.add('text-warning');
                        break;
                    case 4:
                    case 5:
                        strengthText.classList.add('text-success');
                        break;
                }
                
                strengthFill.className = 'strength-fill ' + fillClass;

                return requirements;
            }

            passwordInput.addEventListener('input', function() {
                checkPasswordRequirements(this.value);
            });

            // Password match checker
            confirmInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirm = this.value;

                if (!confirm) {
                    passwordMatch.textContent = '';
                    passwordMatch.className = '';
                    this.style.borderColor = '';
                } else if (password === confirm) {
                    passwordMatch.innerHTML = '<i class="fas fa-check-circle me-2"></i> Passwords match';
                    passwordMatch.className = 'text-success fw-semibold';
                    this.style.borderColor = 'var(--bs-success)';
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-times-circle me-2"></i> Passwords do not match';
                    passwordMatch.className = 'text-danger fw-semibold';
                    this.style.borderColor = 'var(--bs-danger)';
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                const requirements = checkPasswordRequirements(password);

                // Check if all active requirements are met
                const allRequirementsMet = Object.values(requirements).every(Boolean);

                if (!allRequirementsMet) {
                    e.preventDefault();
                    alert('Please ensure your password meets all the requirements listed below.');
                    passwordInput.focus();
                    return;
                }

                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match. Please confirm your password.');
                    confirmInput.focus();
                    return;
                }

                // Add loading state
                if (submitBtn && btnText && loadingSpinner) {
                    submitBtn.disabled = true;
                    btnText.style.display = 'none';
                    loadingSpinner.style.display = 'inline';
                }

                // Clear timer when form is submitted
                clearInterval(timerInterval);
            });

            // Auto-focus password input
            setTimeout(() => {
                if (passwordInput) {
                    passwordInput.focus();
                    // Trigger initial requirement check
                    checkPasswordRequirements('');
                }
            }, 500);
        });

        // Auto-dismiss alerts after 8 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                try {
                    bsAlert.close();
                } catch (e) {
                    // Ignore errors if alert is already closed
                }
            });
        }, 8000);
    </script>
</body>
</html>