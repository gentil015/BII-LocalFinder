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

            // Insert user profile for tracking metrics
            $profileStmt = $db->prepare("INSERT IGNORE INTO user_profiles (user_id, user_avg_price, user_avg_response_time, user_total_bookings) VALUES (?, 0, 24, 0)");
            $profileStmt->execute([$user_id]);

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
    <title>Create Account - <?php echo htmlspecialchars($platform_name); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
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
            --radius-xl: 28px;
            --shadow-sm: 0 1px 3px rgba(15,23,42,0.06), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-md: 0 4px 16px rgba(15,23,42,0.08);
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

        h1,h2,h3,h4,h5,h6,.navbar-brand {
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

        /* ── REGISTER LAYOUT ── */
        .register-wrapper {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 3rem 1rem 4rem;
        }

        .register-panel {
            width: 100%;
            max-width: 520px;
            animation: slideUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── BRAND MARK ── */
        .register-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-brand-icon {
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

        .register-title {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--dark);
            margin-bottom: 0.35rem;
        }

        .register-subtitle {
            font-size: 0.92rem;
            color: var(--text-muted);
        }

        /* ── REGISTER CARD ── */
        .register-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .register-body {
            padding: 2.25rem 2.25rem 2rem;
        }

        /* ── ACCOUNT TYPE SELECTOR ── */
        .account-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .account-type-card {
            text-align: center;
            padding: 1.75rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
            background: var(--surface-2);
            position: relative;
            overflow: hidden;
        }

        .account-type-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .account-type-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-md), 0 0 0 1px rgba(37,99,235,0.08);
            background: var(--surface);
        }

        .account-type-card:hover::before { opacity: 1; }

        .account-type-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            box-shadow: var(--shadow-primary);
        }

        .account-type-card.selected::before { opacity: 1; }

        .account-type-card.disabled {
            opacity: 0.48;
            cursor: not-allowed;
        }

        .account-type-card.disabled:hover {
            transform: none;
            box-shadow: none;
            border-color: var(--border);
            background: var(--surface-2);
        }

        .account-type-card.disabled:hover::before { opacity: 0; }

        .account-type-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(30,64,175,0.06));
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.4rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .account-type-card:hover .account-type-icon,
        .account-type-card.selected .account-type-icon {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: scale(1.08);
            box-shadow: 0 6px 16px rgba(37,99,235,0.28);
        }

        .account-type-card h5 {
            font-size: 0.97rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 0.3rem;
            color: var(--dark);
        }

        .account-type-card p {
            font-size: 0.82rem;
            color: var(--secondary);
            margin-bottom: 0.4rem;
        }

        .account-type-card small { font-size: 0.78rem; color: var(--text-muted); }

        /* ── FORM ELEMENTS ── */
        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--secondary);
            margin-bottom: 0.4rem;
        }

        .input-wrapper { position: relative; }

        .input-icon {
            position: absolute;
            left: 1rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.88rem;
            z-index: 5;
            pointer-events: none;
        }

        .input-icon-top {
            position: absolute;
            left: 1rem; top: 0.88rem;
            color: var(--text-muted);
            font-size: 0.88rem;
            z-index: 5;
            pointer-events: none;
        }

        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.8rem 1rem 0.8rem 2.75rem;
            font-size: 0.92rem;
            font-weight: 500;
            background: var(--surface-2);
            color: var(--dark);
            transition: all 0.22s ease;
            width: 100%;
        }

        .form-control:not(.has-icon),
        .form-select:not(.has-icon) {
            padding-left: 1rem;
        }

        .form-control:hover, .form-select:hover { border-color: #cbd5e1; background: var(--surface); }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
            background: var(--surface);
            outline: none;
        }

        textarea.form-control {
            padding-left: 2.75rem;
            resize: vertical;
        }

        /* password toggle */
        .password-toggle {
            position: absolute;
            right: 1rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.88rem;
            cursor: pointer;
            z-index: 5;
            background: none; border: none; padding: 0;
            transition: color 0.2s ease;
        }

        .password-toggle:hover { color: var(--primary); }

        .form-text {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }

        /* file upload */
        input[type="file"].form-control {
            padding: 0.65rem 1rem;
            font-size: 0.88rem;
        }

        /* ── PROVIDER FIELDS SECTION ── */
        .provider-fields {
            background: linear-gradient(135deg, rgba(37,99,235,0.04), rgba(30,64,175,0.02));
            border: 1.5px solid rgba(37,99,235,0.16);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .provider-fields-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.02em;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
        }

        .provider-fields-header i {
            width: 28px; height: 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 0.78rem;
        }

        /* ── USER TYPE BADGE ── */
        .user-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.45rem 1rem;
            border-radius: 100px;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: 0.01em;
        }

        /* ── BACK LINK ── */
        .back-to-selection {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--secondary);
            cursor: pointer;
            margin-bottom: 1.25rem;
            font-size: 0.87rem;
            font-weight: 600;
            transition: color 0.2s ease;
            background: none;
            border: none;
            padding: 0;
        }

        .back-to-selection:hover { color: var(--primary); }

        /* ── SUBMIT BUTTON ── */
        .btn-register {
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

        .btn-register::after {
            content: '';
            position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.14), transparent);
            transition: left 0.5s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(37,99,235,0.35);
            color: white;
        }

        .btn-register:hover::after { left: 100%; }

        /* ── REGISTRATION FORM TOGGLE ── */
        .registration-form { display: none; }
        .registration-form.active { display: block; animation: slideUp 0.4s cubic-bezier(0.16,1,0.3,1) both; }

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

        .alert-success {
            background: rgba(16,185,129,0.08);
            border: 1.5px solid rgba(16,185,129,0.22);
            color: #065f46;
        }

        .alert-warning {
            background: rgba(245,158,11,0.08);
            border: 1.5px solid rgba(245,158,11,0.25);
            color: #92400e;
        }

        .alert-info {
            background: rgba(37,99,235,0.07);
            border: 1.5px solid rgba(37,99,235,0.2);
            color: var(--primary-dark);
        }

        /* ── DIVIDER & LINKS ── */
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

        .form-divider::before, .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .register-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .register-link:hover { color: var(--primary-dark); text-decoration: underline; }

        .register-footer-text {
            font-size: 0.88rem;
            color: var(--secondary);
            text-align: center;
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
        }

        .footer h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
        }

        .footer .text-muted { color: #64748b !important; font-size: 0.85rem; }

        /* ── STEP INDICATOR ── */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
        }

        .step-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            width: 24px;
            border-radius: 4px;
            background: var(--primary);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 480px) {
            .register-body { padding: 1.75rem 1.5rem; }
            .register-title { font-size: 1.4rem; }
            .account-type-selector { grid-template-columns: 1fr; }
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
                        <li class="nav-item"><a class="nav-link" href="login.php">Sign In</a></li>
                        <li class="nav-item ms-1">
                            <a class="btn btn-primary" href="register.php">Get Started &rarr;</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Register Form -->
    <div class="register-wrapper">
        <div class="register-panel">

            <!-- Brand mark -->
            <div class="register-brand">
                <div class="register-brand-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h1 class="register-title">Create your account</h1>
                <p class="register-subtitle">Join <?php echo htmlspecialchars($platform_name); ?> — free forever</p>
            </div>

            <!-- Card -->
            <div class="register-card">
                <div class="register-body">

                    <?php if (!$client_registration_enabled && !$provider_registration_enabled): ?>
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Registration Temporarily Closed</strong>
                            <p class="mb-0 mt-1">New account registration is currently disabled. Please check back later.</p>
                        </div>
                    <?php else: ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <?php if ($email_verification_enabled): ?>
                                <div class="mt-3">
                                    <a href="verify_otp.php" class="btn btn-primary" style="border-radius:var(--radius-md);font-weight:700;font-size:0.9rem;">
                                        <i class="fas fa-shield-alt me-2"></i>Verify Account
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-primary" style="border-radius:var(--radius-md);font-weight:700;font-size:0.9rem;">
                                        <i class="fas fa-sign-in-alt me-2"></i>Sign In Now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>

                    <!-- Step indicator -->
                    <div class="step-indicator" id="stepIndicator">
                        <div class="step-dot active" id="step1dot"></div>
                        <div class="step-dot" id="step2dot"></div>
                    </div>

                    <!-- Account Type Selection -->
                    <div id="accountTypeSelection" class="<?php echo isset($_POST['user_type']) ? 'd-none' : ''; ?>">
                        <p class="text-center fw-bold mb-3" style="font-size:0.9rem;color:var(--dark);letter-spacing:-0.01em;">Choose your account type</p>

                        <div class="account-type-selector">
                            <!-- Client Card -->
                            <div class="account-type-card <?php echo !$client_registration_enabled ? 'disabled' : ''; ?>"
                                 onclick="<?php echo $client_registration_enabled ? "selectAccountType('client')" : ''; ?>"
                                 id="clientCard">
                                <div class="account-type-icon">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h5>Client</h5>
                                <p class="text-muted mb-1">Looking for Services</p>
                                <small>Hire skilled professionals</small>
                                <?php if (!$client_registration_enabled): ?>
                                    <div class="mt-2"><span class="badge" style="background:rgba(245,158,11,0.12);color:#92400e;font-size:0.7rem;padding:0.3rem 0.65rem;border-radius:100px;">Unavailable</span></div>
                                <?php endif; ?>
                            </div>

                            <!-- Provider Card -->
                            <div class="account-type-card <?php echo !$provider_registration_enabled ? 'disabled' : ''; ?>"
                                 onclick="<?php echo $provider_registration_enabled ? "selectAccountType('provider')" : ''; ?>"
                                 id="providerCard">
                                <div class="account-type-icon">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h5>Provider</h5>
                                <p class="text-muted mb-1">Offering Services</p>
                                <small>Grow your client base</small>
                                <?php if (!$provider_registration_enabled): ?>
                                    <div class="mt-2"><span class="badge" style="background:rgba(245,158,11,0.12);color:#92400e;font-size:0.7rem;padding:0.3rem 0.65rem;border-radius:100px;">Unavailable</span></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-divider">or</div>
                        <p class="register-footer-text">
                            Already have an account? <a href="login.php" class="register-link">Sign in &rarr;</a>
                        </p>
                    </div>

                    <!-- Registration Forms -->
                    <div id="registrationForms">

                        <!-- ── CLIENT FORM ── -->
                        <form method="POST" enctype="multipart/form-data" id="clientForm" class="registration-form" data-user-type="client">
                            <button type="button" class="back-to-selection" onclick="goBackToSelection()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>

                            <div class="user-type-badge">
                                <i class="fas fa-user-tie"></i> Client Account
                            </div>

                            <input type="hidden" name="user_type" value="client">

                            <div class="mb-3">
                                <label for="client_full_name" class="form-label">Full Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control has-icon" id="client_full_name" name="full_name"
                                           required placeholder="Your full name"
                                           value="<?php echo isset($_POST['full_name']) && isset($_POST['user_type']) && $_POST['user_type']==='client' ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="client_email" class="form-label">Email Address</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" class="form-control has-icon" id="client_email" name="email"
                                           required placeholder="your@email.com" autocomplete="email"
                                           value="<?php echo isset($_POST['email']) && isset($_POST['user_type']) && $_POST['user_type']==='client' ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="client_phone" class="form-label">Phone Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control has-icon" id="client_phone" name="phone"
                                           required placeholder="+250 7XX XXX XXX">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="client_profile_image" class="form-label">Profile Photo <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                                <input type="file" class="form-control" id="client_profile_image" name="profile_image" accept="image/*">
                            </div>

                            <div class="mb-3">
                                <label for="client_password" class="form-label">Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" class="form-control has-icon" id="client_password" name="password"
                                           required placeholder="Min <?php echo $min_password_length; ?> characters" autocomplete="new-password">
                                    <button type="button" class="password-toggle" onclick="togglePw('client_password', this)" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">At least <?php echo $min_password_length; ?> characters<?php if ($require_special_chars): ?> with a special character<?php endif; ?></div>
                            </div>

                            <div class="mb-4">
                                <label for="client_confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" class="form-control has-icon" id="client_confirm_password" name="confirm_password"
                                           required placeholder="Re-enter password" autocomplete="new-password">
                                </div>
                            </div>

                            <button type="submit" class="btn-register">
                                <i class="fas fa-user-plus me-2"></i>Create Client Account
                            </button>
                        </form>

                        <!-- ── PROVIDER FORM ── -->
                        <form method="POST" enctype="multipart/form-data" id="providerForm" class="registration-form" data-user-type="provider">
                            <button type="button" class="back-to-selection" onclick="goBackToSelection()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>

                            <div class="user-type-badge">
                                <i class="fas fa-tools"></i> Service Provider Account
                            </div>

                            <input type="hidden" name="user_type" value="provider">

                            <div class="mb-3">
                                <label for="provider_full_name" class="form-label">Full Name</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control has-icon" id="provider_full_name" name="full_name"
                                           required placeholder="Your full name"
                                           value="<?php echo isset($_POST['full_name']) && isset($_POST['user_type']) && $_POST['user_type']==='provider' ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_email" class="form-label">Email Address</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" class="form-control has-icon" id="provider_email" name="email"
                                           required placeholder="your@email.com" autocomplete="email"
                                           value="<?php echo isset($_POST['email']) && isset($_POST['user_type']) && $_POST['user_type']==='provider' ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_phone" class="form-label">Phone Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone input-icon"></i>
                                    <input type="tel" class="form-control has-icon" id="provider_phone" name="phone"
                                           required placeholder="+250 7XX XXX XXX">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_profile_image" class="form-label">Profile Photo <span style="color:var(--danger);font-size:0.85em;">*</span></label>
                                <input type="file" class="form-control" id="provider_profile_image" name="profile_image" accept="image/*" required>
                            </div>

                            <!-- Provider details section -->
                            <div class="provider-fields">
                                <div class="provider-fields-header">
                                    <i class="fas fa-tools"></i>
                                    Provider Details
                                </div>

                                <div class="mb-3">
                                    <label for="profession" class="form-label">Profession <span style="color:var(--danger);font-size:0.85em;">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-briefcase input-icon"></i>
                                        <input type="text" class="form-control has-icon" id="profession" name="profession"
                                               required placeholder="e.g., Electrician, Plumber, Cleaner">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Location <span style="color:var(--danger);font-size:0.85em;">*</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-map-marker-alt input-icon"></i>
                                        <input type="text" class="form-control has-icon" id="location" name="location"
                                               required placeholder="e.g., Kigali, Rubavu, Musanze">
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="district" class="form-label">District</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-city input-icon"></i>
                                            <input list="districtsList" class="form-control has-icon" id="district" name="district"
                                                   placeholder="Type to search…">
                                            <datalist id="districtsList">
                                                <?php foreach ($districts as $district): ?>
                                                    <option value="<?php echo htmlspecialchars($district['name']); ?>"></option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sector" class="form-label">Sector</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-map-pin input-icon"></i>
                                            <input type="text" class="form-control has-icon" id="sector" name="sector"
                                                   placeholder="Your sector">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 mb-0">
                                    <label for="bio" class="form-label">Bio <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-align-left input-icon-top"></i>
                                        <textarea class="form-control has-icon" id="bio" name="bio" rows="3"
                                                  placeholder="Briefly describe your experience and skills…"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="provider_password" class="form-label">Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" class="form-control has-icon" id="provider_password" name="password"
                                           required placeholder="Min <?php echo $min_password_length; ?> characters" autocomplete="new-password">
                                    <button type="button" class="password-toggle" onclick="togglePw('provider_password', this)" tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">At least <?php echo $min_password_length; ?> characters<?php if ($require_special_chars): ?> with a special character<?php endif; ?></div>
                            </div>

                            <div class="mb-4">
                                <label for="provider_confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" class="form-control has-icon" id="provider_confirm_password" name="confirm_password"
                                           required placeholder="Re-enter password" autocomplete="new-password">
                                </div>
                            </div>

                            <button type="submit" class="btn-register">
                                <i class="fas fa-tools me-2"></i>Create Provider Account
                            </button>
                        </form>

                    </div><!-- /registrationForms -->

                    <div class="mt-3" id="loginLink">
                        <div class="form-divider">already have an account?</div>
                        <p class="register-footer-text">
                            <a href="login.php" class="register-link">Sign in to your account &rarr;</a>
                        </p>
                    </div>

                    <?php endif; ?>
                    <?php endif; ?>

                </div><!-- /register-body -->
            </div><!-- /register-card -->

            <!-- Trust badges -->
            <div class="trust-badges">
                <span class="trust-badge"><i class="fas fa-shield-alt"></i> Secure Registration</span>
                <span class="trust-badge"><i class="fas fa-lock"></i> Data Encrypted</span>
                <span class="trust-badge"><i class="fas fa-check-circle"></i> Free to Join</span>
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
                <div class="text-muted" style="font-size:0.82rem;">
                    <a href="index.php" style="color:#64748b;text-decoration:none;margin-right:1rem;">Home</a>
                    <a href="about.php" style="color:#64748b;text-decoration:none;margin-right:1rem;">About</a>
                    <a href="contact.php" style="color:#64748b;text-decoration:none;">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Restore form on POST error
        const userType = '<?php echo addslashes($_POST['user_type'] ?? ''); ?>';
        if (userType) {
            document.getElementById('accountTypeSelection').classList.add('d-none');
            document.getElementById('loginLink').classList.add('d-none');
            showRegistrationForm(userType);
        }

        function selectAccountType(type) {
            document.getElementById('clientCard').classList.remove('selected');
            document.getElementById('providerCard').classList.remove('selected');
            document.getElementById(type + 'Card').classList.add('selected');
            document.getElementById('accountTypeSelection').classList.add('d-none');
            document.getElementById('loginLink').classList.add('d-none');
            showRegistrationForm(type);
        }

        function showRegistrationForm(type) {
            document.querySelectorAll('.registration-form').forEach(f => f.classList.remove('active'));
            document.getElementById(type + 'Form').classList.add('active');
            // advance step indicator
            document.getElementById('step1dot').classList.remove('active');
            document.getElementById('step2dot').classList.add('active');
            // focus first field
            setTimeout(() => {
                const first = document.getElementById(type + '_full_name');
                if (first) first.focus();
            }, 80);
        }

        function goBackToSelection() {
            document.querySelectorAll('.registration-form').forEach(f => f.classList.remove('active'));
            document.getElementById('accountTypeSelection').classList.remove('d-none');
            document.getElementById('loginLink').classList.remove('d-none');
            document.getElementById('clientCard').classList.remove('selected');
            document.getElementById('providerCard').classList.remove('selected');
            // reset step indicator
            document.getElementById('step1dot').classList.add('active');
            document.getElementById('step2dot').classList.remove('active');
        }

        // Password show/hide
        function togglePw(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            const isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        // Load districts lazily
        document.getElementById('providerForm').addEventListener('mouseenter', function () {
            const datalist = document.getElementById('districtsList');
            if (datalist && datalist.options.length === 0) {
                fetch('api/get-districts.php')
                    .then(r => r.ok ? r.json() : Promise.reject())
                    .then(data => {
                        if (!Array.isArray(data)) return;
                        data.forEach(d => {
                            const opt = document.createElement('option');
                            opt.value = d.name;
                            datalist.appendChild(opt);
                        });
                    })
                    .catch(() => {});
            }
        }, { once: true });

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-6px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 6000);

        // Navbar scroll shadow
        const navbar = document.querySelector('.navbar');
        window.addEventListener('scroll', () => {
            navbar.style.boxShadow = window.scrollY > 10
                ? '0 4px 24px rgba(15,23,42,0.10)'
                : 'none';
        }, { passive: true });

        // Submit loading state
        document.querySelectorAll('.registration-form').forEach(form => {
            form.addEventListener('submit', function () {
                const btn = this.querySelector('.btn-register');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating account…';
                    btn.style.opacity = '0.8';
                    btn.style.pointerEvents = 'none';
                }
            });
        });
    </script>
</body>
</html>