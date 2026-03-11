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

// Check registration settings
$client_registration_enabled = getPlatformSetting('client_registration', '1');
$provider_registration_enabled = getPlatformSetting('provider_registration', '1');
$email_verification_enabled = getPlatformSetting('email_verification', '1');
$phone_verification_enabled = getPlatformSetting('phone_verification', '0');
$min_password_length = getPlatformSetting('min_password_length', '6');
$require_special_chars = getPlatformSetting('require_special_chars', '0');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitize($_POST['user_type']);
    // Optional profile image filename (will be set if upload provided)
    $profile_image = '';
    
    // ✅ Check if registration is enabled for this user type
    if ($user_type === 'client' && !$client_registration_enabled) {
        $errors[] = "Client registration is currently disabled";
    }
    
    if ($user_type === 'provider' && !$provider_registration_enabled) {
        $errors[] = "Provider registration is currently disabled";
    }
    
    

    // ✅ Basic validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $errors[] = "All fields are required";
    }

    if (strlen($password) < $min_password_length) {
        $errors[] = "Password must be at least {$min_password_length} characters";
    }

    if ($require_special_chars && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (!in_array($user_type, ['client', 'provider'])) {
        $errors[] = "Invalid user type";
    }

    // ✅ Check for existing email
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = "Email already registered";
        }
    }

    // ✅ Provider fields
    $profession = '';
    $location = '';
    $bio = '';
    $district = '';
    $sector = '';

    // For providers we will collect profile details in a multi-step setup wizard.
    // Preserve submitted values (if any) but do not enforce all provider checks here.
    if ($user_type === 'provider') {
        $profession = sanitize($_POST['profession'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $sector = sanitize($_POST['sector'] ?? '');
        $experience_years = isset($_POST['experience_years']) ? intval($_POST['experience_years']) : 0;
        // services (expect arrays)
        $service_names = $_POST['service_name'] ?? [];
        $service_prices = $_POST['service_price'] ?? [];
        // availability
        $working_days = isset($_POST['working_days']) ? (is_array($_POST['working_days']) ? $_POST['working_days'] : explode(',', $_POST['working_days'])) : [];
        $working_hours_start = isset($_POST['working_hours_start']) ? $_POST['working_hours_start'] : null;
        $working_hours_end = isset($_POST['working_hours_end']) ? $_POST['working_hours_end'] : null;
        // Do not enforce provider-only requirements at initial registration — those will be handled step-by-step in the setup wizard.
    }

    // ✅ Register user with OTP verification
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Handle profile image upload if provided (optional)
        if (!empty($_FILES['profile_image']['name']) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $allowed_types = function_exists('getAllowedFileTypes') ? getAllowedFileTypes() : ['jpg','jpeg','png','gif'];
                $max_file_size = function_exists('getMaxFileSize') ? getMaxFileSize() * 1024 * 1024 : 2 * 1024 * 1024;

                $file_tmp = $_FILES['profile_image']['tmp_name'];
                $file_name = $_FILES['profile_image']['name'];
                $file_size = $_FILES['profile_image']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_types)) {
                    $errors[] = "Invalid image type. Allowed: " . implode(', ', $allowed_types);
                } elseif ($file_size > $max_file_size) {
                    $errors[] = "Profile image must be smaller than " . (int)($max_file_size/1024/1024) . "MB";
                } else {
                    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $new_filename = 'profile_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
                    $dest = $upload_dir . DIRECTORY_SEPARATOR . $new_filename;
                    if (move_uploaded_file($file_tmp, $dest)) {
                        $profile_image = $new_filename;
                    } else {
                        $errors[] = "Failed to save profile image. Please try again.";
                    }
                }
            } catch (Exception $e) {
                error_log('Profile image upload error: ' . $e->getMessage());
                $errors[] = "Failed to process profile image.";
            }
        }

        // Generate 6-digit OTP and expiration (10 minutes)
        $otp = random_int(100000, 999999);
        $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Set verification status based on settings
        $is_verified = $email_verification_enabled ? 0 : 1;
        $is_active = $user_type === 'provider' ? 0 : 1; // Providers need admin approval

        try {
            $db->beginTransaction();

            // Insert user with OTP
            $stmt = $db->prepare("
                INSERT INTO users (full_name, email, phone, profile_image, password, user_type, otp_code, otp_expires_at, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$full_name, $email, $phone, $profile_image, $hashed_password, $user_type, $otp, $otpExpiresAt, $is_verified, $is_active]);
            $user_id = $db->lastInsertId();

                // Do not create the provider profile here. Provider details will be collected in the multi-step setup wizard.

            $db->commit();

            // ✅ Send OTP Email (non-blocking) if email verification is enabled
            if ($email_verification_enabled) {
                try {
                    Mailer::sendVerificationOTP($email, $full_name, $otp, 10);
                } catch (Exception $e) {
                    error_log("OTP email send failed: " . $e->getMessage());
                }
            }

            // If provider, set session and redirect to setup wizard (step 1)
            if ($user_type === 'provider') {
                $_SESSION['user_id'] = $user_id;
                header('Location: provider/setup/step1-profile.php');
                exit;
            }

            // Success message based on verification requirements
            if ($user_type === 'provider') {
                if ($email_verification_enabled) {
                    $success = "Registration successful! A 6-digit OTP has been sent to your email. Please verify your account. Your provider account will be activated after admin approval.";
                } else {
                    $success = "Registration successful! Your provider account is pending admin approval. You will be notified once approved.";
                }
            } else {
                if ($email_verification_enabled) {
                    $success = "Registration successful! A 6-digit OTP has been sent to your email. Please verify your account.";
                } else {
                    $success = "Registration successful! You can now login to your account.";
                }
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Registration failed. Please try again.";
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}

$user_type = $_GET['type'] ?? 'client';
// Ensure the user type is allowed
if ($user_type === 'provider' && !$provider_registration_enabled) {
    $user_type = 'client';
}
if ($user_type === 'client' && !$client_registration_enabled) {
    header('Location: login.php');
    exit;
}

// Get districts for provider registration
$districts = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM districts ORDER BY name");
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load districts: " . $e->getMessage());
    $districts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
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
        
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,.08);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-body {
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
        
        .form-select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .btn-register {
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
        
        .provider-fields {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .user-type-badge {
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .registration-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .account-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .account-type-card {
            flex: 1;
            text-align: center;
            padding: 2rem 1rem;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .account-type-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,.1);
        }
        
        .account-type-card.selected {
            border-color: var(--primary);
            background-color: rgba(13, 110, 253, 0.05);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.1);
        }
        
        .account-type-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .account-type-card.disabled:hover {
            border-color: #dee2e6;
            transform: none;
            box-shadow: none;
        }
        
        .account-type-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .account-type-card.disabled .account-type-icon {
            color: var(--secondary);
        }
        
        .registration-form {
            display: none;
        }
        
        .registration-form.active {
            display: block;
        }
        
        .back-to-selection {
            color: var(--primary);
            cursor: pointer;
            margin-bottom: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-to-selection:hover {
            text-decoration: underline;
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

    <!-- Register Form -->
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="brand-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h4 class="mb-2">Create Your Account</h4>
            </div>
            
            <div class="register-body">
                <?php if (!$client_registration_enabled && !$provider_registration_enabled): ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Registration Temporarily Closed</strong>
                        <p class="mb-0 mt-2">New account registration is currently disabled. Please check back later.</p>
                    </div>
                <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <?php if ($email_verification_enabled): ?>
                            <div class="mt-3">
                                <a href="verify_otp.php" class="btn btn-primary">
                                    <i class="fas fa-shield-alt me-2"></i> Verify Account
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mt-3">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>

                <!-- Account Type Selection (First Step) -->
                <div id="accountTypeSelection" class="<?php echo isset($_POST['user_type']) ? 'd-none' : ''; ?>">
                    <h5 class="text-center mb-4">Choose Your Account Type</h5>
                    
                    <div class="account-type-selector">
                        <!-- Client Card -->
                        <div class="account-type-card <?php echo !$client_registration_enabled ? 'disabled' : ''; ?>" 
                             onclick="<?php echo $client_registration_enabled ? 'selectAccountType(\'client\')' : ''; ?>"
                             id="clientCard">
                            <div class="account-type-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h5>Client</h5>
                            <p class="text-muted mb-2">Looking for Services</p>
                            <small class="text-muted">
                                Browse and hire service providers
                            </small>
                            <?php if (!$client_registration_enabled): ?>
                                <div class="badge bg-warning mt-2">Currently Unavailable</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Provider Card -->
                        <div class="account-type-card <?php echo !$provider_registration_enabled ? 'disabled' : ''; ?>" 
                             onclick="<?php echo $provider_registration_enabled ? 'selectAccountType(\'provider\')' : ''; ?>"
                             id="providerCard">
                            <div class="account-type-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h5>Service Provider</h5>
                            <p class="text-muted mb-2">Offering Services</p>
                            <small class="text-muted">
                                List your services and get hired
                            </small>
                            <?php if (!$provider_registration_enabled): ?>
                                <div class="badge bg-warning mt-2">Currently Unavailable</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            Already have an account? 
                            <a href="login.php" class="text-primary text-decoration-none fw-semibold">Login here</a>
                        </p>
                    </div>
                </div>

                <!-- Registration Forms (Second Step) -->
                <div id="registrationForms">
                    <!-- Client Registration Form -->
                    <form method="POST" enctype="multipart/form-data" id="clientForm" class="registration-form" data-user-type="client">
                        <div class="back-to-selection" onclick="goBackToSelection()">
                            <i class="fas fa-arrow-left"></i> Choose Different Account Type
                        </div>
                        
                        <div class="user-type-badge mb-3">
                            <i class="fas fa-user-tie me-2"></i> Registering as Client
                        </div>
                        
                        <input type="hidden" name="user_type" value="client">
                        
                        <div class="mb-3">
                            <label for="client_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="client_full_name" name="full_name" 
                                   required placeholder="Enter your full name">
                        </div>

                        <div class="mb-3">
                            <label for="client_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="client_email" name="email" 
                                   required placeholder="your@email.com">
                        </div>

                        <div class="mb-3">
                            <label for="client_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="client_phone" name="phone" 
                                   required placeholder="+250 7XX XXX XXX">
                        </div>

                        <div class="mb-3">
                            <label for="client_profile_image" class="form-label">Profile Image (optional)</label>
                            <input type="file" class="form-control" id="client_profile_image" name="profile_image" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label for="client_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="client_password" name="password" 
                                   required placeholder="Minimum <?php echo $min_password_length; ?> characters">
                            <div class="form-text">
                                Password must be at least <?php echo $min_password_length; ?> characters long.
                                <?php if ($require_special_chars): ?>
                                    Must include at least one special character.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="client_confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="client_confirm_password" name="confirm_password" 
                                   required placeholder="Re-enter your password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Create Client Account
                        </button>
                    </form>

                    <!-- Provider Registration Form -->
                    <form method="POST" enctype="multipart/form-data" id="providerForm" class="registration-form" data-user-type="provider">
                        <div class="back-to-selection" onclick="goBackToSelection()">
                            <i class="fas fa-arrow-left"></i> Choose Different Account Type
                        </div>
                        
                        <div class="user-type-badge mb-3">
                            <i class="fas fa-tools me-2"></i> Registering as Service Provider
                        </div>
                        
                        <input type="hidden" name="user_type" value="provider">
                        
                        <div class="mb-3">
                            <label for="provider_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="provider_full_name" name="full_name" 
                                   required placeholder="Enter your full name">
                        </div>

                        <div class="mb-3">
                            <label for="provider_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="provider_email" name="email" 
                                   required placeholder="your@email.com">
                        </div>

                        <div class="mb-3">
                            <label for="provider_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="provider_phone" name="phone" 
                                   required placeholder="+250 7XX XXX XXX">
                        </div>

                        <div class="mb-3">
                            <label for="provider_profile_image" class="form-label">Profile Image (required)</label>
                            <input type="file" class="form-control" id="provider_profile_image" name="profile_image" accept="image/*" required>
                        </div>

                        <div class="provider-fields">
                            <h6 class="mb-3 text-primary"><i class="fas fa-tools me-2"></i> Provider Information</h6>
                            
                            <div class="mb-3">
                                <label for="profession" class="form-label">Profession *</label>
                                <input type="text" class="form-control" id="profession" name="profession" 
                                       required placeholder="e.g., Electrician, Plumber, Cleaner">
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       required placeholder="e.g., Kigali, Rubavu, Musanze">
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="district" class="form-label">District</label>
                                        <input list="districtsList" class="form-control" id="district" name="district" 
                                               placeholder="Type to search district">
                                        <datalist id="districtsList">
                                            <?php foreach ($districts as $district): ?>
                                                <option value="<?php echo htmlspecialchars($district['name']); ?>"></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sector" class="form-label">Sector</label>
                                        <input type="text" class="form-control" id="sector" name="sector" 
                                               placeholder="Enter your sector">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label for="bio" class="form-label">Bio (Optional)</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" 
                                          placeholder="Tell us about your experience and skills..."></textarea>
                            </div>
                        </div>

                        <!-- Provider-specific profile details collected in the setup wizard after registration -->

                        <div class="mb-3">
                            <label for="provider_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="provider_password" name="password" 
                                   required placeholder="Minimum <?php echo $min_password_length; ?> characters">
                            <div class="form-text">
                                Password must be at least <?php echo $min_password_length; ?> characters long.
                                <?php if ($require_special_chars): ?>
                                    Must include at least one special character.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="provider_confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="provider_confirm_password" name="confirm_password" 
                                   required placeholder="Re-enter your password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Create Provider Account
                        </button>
                    </form>
                </div>

                <div class="text-center mt-3" id="loginLink">
                    <p class="mb-0">
                        Already have an account? 
                        <a href="login.php" class="text-primary text-decoration-none fw-semibold">Login here</a>
                    </p>
                </div>

                <?php endif; ?>
                <?php endif; ?>
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
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check if coming from POST (form submission with errors)
        const userType = '<?php echo $_POST['user_type'] ?? ''; ?>';
        
        // If there's a POST error, show the appropriate form
        if (userType) {
            document.getElementById('accountTypeSelection').classList.add('d-none');
            showRegistrationForm(userType);
        }
        
        function selectAccountType(type) {
            // Remove selected class from all cards
            document.getElementById('clientCard').classList.remove('selected');
            document.getElementById('providerCard').classList.remove('selected');
            
            // Add selected class to clicked card
            document.getElementById(type + 'Card').classList.add('selected');
            
            // Hide selection, show registration form
            document.getElementById('accountTypeSelection').classList.add('d-none');
            showRegistrationForm(type);
        }
        
        function showRegistrationForm(type) {
            // Hide all forms
            const forms = document.querySelectorAll('.registration-form');
            forms.forEach(form => {
                form.classList.remove('active');
            });
            
            // Show selected form
            document.getElementById(type + 'Form').classList.add('active');
            
            // Hide login link when form is shown
            document.getElementById('loginLink').classList.add('d-none');
        }
        
        function goBackToSelection() {
            // Hide all forms
            const forms = document.querySelectorAll('.registration-form');
            forms.forEach(form => {
                form.classList.remove('active');
            });
            
            // Show selection
            document.getElementById('accountTypeSelection').classList.remove('d-none');
            
            // Show login link
            document.getElementById('loginLink').classList.remove('d-none');
            
            // Remove selected class from cards
            document.getElementById('clientCard').classList.remove('selected');
            document.getElementById('providerCard').classList.remove('selected');
        }
        
        // Load districts when provider form is shown
        document.getElementById('providerForm').addEventListener('mouseenter', function() {
            const datalist = document.getElementById('districtsList');
            if (datalist && datalist.options.length === 0) {
                fetch('api/get-districts.php')
                    .then(r => {
                        if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        if (!Array.isArray(data)) throw new Error('Invalid JSON response');
                        data.forEach(d => {
                            const opt = document.createElement('option');
                            opt.value = d.name;
                            datalist.appendChild(opt);
                        });
                    })
                    .catch(err => {
                        console.error('Failed to load districts:', err);
                    });
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Focus on first field when form is shown
        function focusOnFirstField(type) {
            setTimeout(() => {
                document.getElementById(type + '_full_name').focus();
            }, 100);
        }
        
        // Update selectAccountType to also focus on field
        const originalSelectAccountType = selectAccountType;
        selectAccountType = function(type) {
            originalSelectAccountType(type);
            focusOnFirstField(type);
        };
        
        // If there's a POST error, focus on the first field
        if (userType) {
            setTimeout(() => {
                focusOnFirstField(userType);
            }, 100);
        }
    </script>
</body>
</html>