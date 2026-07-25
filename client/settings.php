<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/includes/client_header.php';
require_once '../controllers/pages/client/ClientSettingsController.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isProvider()) {
    redirect('../provider/settings.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

$controller = new ClientSettingsController();
$viewData = $controller->index($db, (int) $_SESSION['user_id'], isset($_GET['section']) ? sanitize($_GET['section']) : 'account');

// Get settings section from URL
$settings_section = isset($_GET['section']) ? sanitize($_GET['section']) : 'account';
$valid_sections = ['account', 'profile', 'preferences', 'bookings', 'security', 'control'];
if (!in_array($settings_section, $valid_sections)) {
    $settings_section = 'account';
}

// Get user data
$user = $viewData['user'] ?? [];
$system_settings = $viewData['system_settings'] ?? [];
$user_notifications = $viewData['user_notifications'] ?? [];
$user_privacy = $viewData['user_privacy'] ?? [];
$user_booking_prefs = $viewData['user_booking_prefs'] ?? [];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif (strlen($new_password) < $system_settings['min_password_length']) {
            $errors[] = "Password must be at least {$system_settings['min_password_length']} characters";
        } elseif ($system_settings['require_special_chars'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            $errors[] = "Password must contain at least one special character";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
                $success = "✓ Password changed successfully!";
                logActivity($db, $_SESSION['user_id'], 'password_change', 'User changed password');
            } else {
                $errors[] = "Failed to change password";
            }
        }
    } elseif (isset($_POST['update_notifications'])) {
        try {
            $settings = [
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                'booking_notifications' => isset($_POST['booking_notifications']) ? 1 : 0,
                'review_notifications' => isset($_POST['review_notifications']) ? 1 : 0,
                'marketing_notifications' => isset($_POST['marketing_notifications']) ? 1 : 0,
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$_SESSION['user_id'], $key, $value, $value]);
            }
            
            $success = "✓ Notification preferences updated!";
            logActivity($db, $_SESSION['user_id'], 'notification_update', 'Updated notification settings');
            
            // Refresh preferences
            foreach ($settings as $key => $value) {
                $user_notifications[$key] = $value;
            }
        } catch (Exception $e) {
            $errors[] = "Failed to update preferences";
        }
    } elseif (isset($_POST['update_privacy'])) {
        try {
            $privacy_settings = [
                'profile_visibility' => $_POST['profile_visibility'],
                'show_contact_info' => isset($_POST['show_contact_info']) ? 1 : 0,
                'data_sharing' => isset($_POST['data_sharing']) ? 1 : 0,
            ];
            
            foreach ($privacy_settings as $key => $value) {
                $stmt = $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$_SESSION['user_id'], $key, $value, $value]);
            }
            
            $success = "✓ Privacy settings updated!";
            logActivity($db, $_SESSION['user_id'], 'privacy_update', 'Updated privacy settings');
        } catch (Exception $e) {
            $errors[] = "Failed to update privacy settings";
        }
    } elseif (isset($_POST['update_booking_preferences'])) {
        try {
            $booking_preferences = [
                'auto_confirm_bookings' => isset($_POST['auto_confirm_bookings']) ? 1 : 0,
                'advance_booking_days' => intval($_POST['advance_booking_days']),
                'preferred_notice_hours' => intval($_POST['preferred_notice_hours']),
            ];
            
            foreach ($booking_preferences as $key => $value) {
                $stmt = $db->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$_SESSION['user_id'], $key, $value, $value]);
            }
            
            $success = "✓ Booking preferences saved!";
            logActivity($db, $_SESSION['user_id'], 'booking_preferences_update', 'Updated booking preferences');
        } catch (Exception $e) {
            $errors[] = "Failed to update preferences";
        }
    } elseif (isset($_POST['deactivate_account'])) {
        $stmt = $db->prepare("UPDATE users SET is_active = 0, deactivated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$_SESSION['user_id']])) {
            logActivity($db, $_SESSION['user_id'], 'account_deactivation', 'User deactivated account');
            session_destroy();
            redirect('../index.php?deactivated=1');
        }
    } elseif (isset($_POST['delete_account'])) {
        $password_confirm = $_POST['password_confirm'];
        if (password_verify($password_confirm, $user['password'])) {
            try {
                $db->beginTransaction();
                $user_id = $_SESSION['user_id'];
                
                if ($system_settings['archive_deleted_accounts']) {
                    $db->prepare("UPDATE users SET status = 'archived', deleted_at = NOW() WHERE id = ?")->execute([$user_id]);
                    $db->prepare("UPDATE user_settings SET status = 'archived' WHERE user_id = ?")->execute([$user_id]);
                } else {
                    $db->prepare("DELETE FROM user_settings WHERE user_id = ?")->execute([$user_id]);
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                }
                
                $db->commit();
                logActivity($db, $user_id, 'account_deletion', 'User deleted account');
                session_destroy();
                redirect('../index.php?deleted=1');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Failed to delete account";
            }
        } else {
            $errors[] = "Incorrect password";
        }
    }
}

$needs_email_verification = $viewData['needs_email_verification'] ?? false;
$needs_phone_verification = $viewData['needs_phone_verification'] ?? false;
$total_bookings = (int) ($viewData['total_bookings'] ?? 0);
$completed_bookings = (int) ($viewData['completed_bookings'] ?? 0);
$total_reviews = (int) ($viewData['total_reviews'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo $system_settings['platform_name']; ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .sidebar-header, .sidebar-menu, .sidebar-menu a {
            padding: 1.5rem 1rem;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 1.5rem 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 2rem;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        .system-status {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        
        .status-item:last-child {
            margin-bottom: 0;
        }
        
        .status-item strong {
            color: #ffc107;
        }
        
        /* View Tabs */
        .view-tabs {
            display: flex;
            gap: 0.75rem;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            flex-wrap: wrap;
        }
        
        .view-tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 8px;
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            cursor: pointer;
        }
        
        .view-tab:hover {
            background: #f8f9fa;
            color: var(--primary);
        }
        
        .view-tab.active {
            background: linear-gradient(135deg, var(--primary), #0dcaf0);
            color: white;
        }
        
        /* Settings Section */
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .section-title {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .setting-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .setting-card:hover {
            border-color: #e9ecef;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .setting-card h3 {
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .account-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .info-value {
            color: var(--secondary);
        }
        
        /* Verification Alert */
        .verification-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 2px solid #fecaca;
            border-radius: 10px;
            padding: 1.5rem;
            background: #fef2f2;
            margin-bottom: 1.5rem;
        }
        
        .danger-zone h3 {
            color: #991b1b;
            margin-bottom: 0.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .danger-zone p {
            color: #64748b;
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-menu-toggle {
                display: flex !important;
            }
            
            .overlay.active {
                display: block;
            }
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
    </style>
<?php client_header_render_styles(); ?>
</head>
<body>
    <?php client_header_render_markup(basename($_SERVER['PHP_SELF'])); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your account, preferences, and security settings</p>
        </div>

        <!-- System Status -->
        <div class="system-status">
            <div class="status-item">
                <i class="fas fa-user-check"></i>
                <span>Account Status: <strong><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></strong></span>
            </div>
            <div class="status-item">
                <i class="fas fa-envelope"></i>
                <span>Email: <strong><?php echo $user['email_verified'] ? '✓ Verified' : '⚠ Pending'; ?></strong></span>
            </div>
            <div class="status-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Member Since: <strong><?php echo date('M d, Y', strtotime($user['created_at'])); ?></strong></span>
            </div>
        </div>

        <?php if ($needs_email_verification || $needs_phone_verification): ?>
            <div class="verification-alert">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Verification Required</h5>
                <?php if ($needs_email_verification): ?>
                    <p class="mb-1">📧 Verify your email: <a href="verify.php" class="alert-link">Verify now</a></p>
                <?php endif; ?>
                <?php if ($needs_phone_verification): ?>
                    <p class="mb-0">📱 Verify your phone: <a href="verify.php" class="alert-link">Verify now</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation Tabs -->
        <div class="view-tabs">
            <a href="?section=account" class="view-tab <?php echo $settings_section === 'account' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Account
            </a>
            <a href="?section=profile" class="view-tab <?php echo $settings_section === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-address-card"></i> Profile
            </a>
            <a href="?section=preferences" class="view-tab <?php echo $settings_section === 'preferences' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i> Preferences
            </a>
            <a href="?section=bookings" class="view-tab <?php echo $settings_section === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Bookings
            </a>
            <a href="?section=security" class="view-tab <?php echo $settings_section === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-lock"></i> Security
            </a>
            <a href="?section=control" class="view-tab <?php echo $settings_section === 'control' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i> Account Control
            </a>
        </div>

        <!-- 👤 Account Section -->
        <?php if ($settings_section === 'account'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> Account Information
                </div>
                
                <div class="setting-card">
                    <h3>Your Account Details</h3>
                    <div class="account-info">
                        <div class="info-item">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($user['email']); ?>
                                <?php echo $user['email_verified'] ? '<span class="badge bg-success ms-2">Verified</span>' : '<span class="badge bg-warning ms-2">Unverified</span>'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($user['phone'] ?: 'Not set'); ?>
                                <?php echo ($user['phone_verified'] ?? 0) ? '<span class="badge bg-success ms-2">Verified</span>' : ''; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Member Since:</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Login:</span>
                            <span class="info-value"><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                        </div>
                    </div>
                    <a href="profile.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                </div>

                <?php if ($needs_email_verification): ?>
                    <div class="setting-card">
                        <h3>Email Verification</h3>
                        <p class="mb-3">Your email address is not yet verified. Complete verification to unlock all platform features.</p>
                        <a href="verify.php" class="btn btn-warning">
                            <i class="fas fa-envelope me-2"></i> Verify Email
                        </a>
                    </div>
                <?php endif; ?>

                <div class="setting-card">
                    <h3>📊 Your Statistics</h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4><?php echo $total_bookings; ?></h4>
                                <small class="text-muted">Total Bookings</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4><?php echo $completed_bookings; ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4><?php echo $total_reviews; ?></h4>
                                <small class="text-muted">Reviews Written</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 📝 Profile Section -->
        <?php if ($settings_section === 'profile'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-address-card"></i> Profile Information
                </div>
                
                <div class="setting-card">
                    <h3>Basic Information</h3>
                    <p class="text-muted">Your profile information is visible to service providers</p>
                    <a href="profile.php" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </a>
                </div>

                <div class="setting-card">
                    <h3>Avatar & Profile Picture</h3>
                    <p class="text-muted">A clear profile picture helps providers get to know you</p>
                    <a href="profile.php" class="btn btn-outline-primary">
                        <i class="fas fa-camera me-2"></i> Upload Picture
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⚙️ Preferences Section -->
        <?php if ($settings_section === 'preferences'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-sliders-h"></i> Display & Privacy Preferences
                </div>
                
                <div class="setting-card">
                    <h3>📬 Notification Preferences</h3>
                    <form method="POST">
                        <div class="notification-item">
                            <div>
                                <h5>Email Notifications</h5>
                                <small class="text-muted">Receive booking updates via email</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_notifications" 
                                    <?php echo $user_notifications['email_notifications'] ? 'checked' : ''; ?> 
                                    <?php echo !$system_settings['enable_email_notifications'] ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div>
                                <h5>Text Message Notifications</h5>
                                <small class="text-muted">Get SMS alerts for urgent matters</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sms_notifications" 
                                    <?php echo $user_notifications['sms_notifications'] ? 'checked' : ''; ?>
                                    <?php echo !$system_settings['enable_sms_notifications'] ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div>
                                <h5>Booking Updates</h5>
                                <small class="text-muted">Notifications for booking confirmations and changes</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="booking_notifications" 
                                    <?php echo $user_notifications['booking_notifications'] ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div>
                                <h5>Review Notifications</h5>
                                <small class="text-muted">Get notified when you receive reviews</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="review_notifications" 
                                    <?php echo $user_notifications['review_notifications'] ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div>
                                <h5>Marketing & Promotions</h5>
                                <small class="text-muted">Receive special offers and announcements</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="marketing_notifications" 
                                    <?php echo $user_notifications['marketing_notifications'] ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <button type="submit" name="update_notifications" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Save Preferences
                        </button>
                    </form>
                </div>

                <div class="setting-card">
                    <h3>🔐 Privacy Settings</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Profile Visibility</label>
                            <select name="profile_visibility" class="form-select">
                                <option value="public" <?php echo $user_privacy['profile_visibility'] === 'public' ? 'selected' : ''; ?>>Public - Everyone can see</option>
                                <option value="private" <?php echo $user_privacy['profile_visibility'] === 'private' ? 'selected' : ''; ?>>Private - Providers only</option>
                            </select>
                            <small class="form-text">Control who can view your profile</small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="show_contact_info" 
                                <?php echo $user_privacy['show_contact_info'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">
                                Show phone number to providers
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="data_sharing" 
                                <?php echo $user_privacy['data_sharing'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">
                                Allow anonymous data sharing for platform improvement
                            </label>
                        </div>
                        
                        <button type="submit" name="update_privacy" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Privacy Settings
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- 📅 Bookings Section -->
        <?php if ($settings_section === 'bookings'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-calendar-check"></i> Booking Preferences
                </div>
                
                <div class="setting-card">
                    <h3>📋 Your Booking Settings</h3>
                    <form method="POST">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_confirm_bookings" 
                                <?php echo $user_booking_prefs['auto_confirm_bookings'] ? 'checked' : ''; ?>>
                            <label class="form-check-label">
                                Auto-confirm bookings
                            </label>
                            <small class="form-text d-block">Automatically confirm new bookings without manual approval</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Advance Booking Days</label>
                            <input type="number" name="advance_booking_days" class="form-control" 
                                value="<?php echo $user_booking_prefs['advance_booking_days']; ?>" min="1" max="90">
                            <small class="form-text">Allow booking up to this many days in advance</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Preferred Advance Notice (Hours)</label>
                            <input type="number" name="preferred_notice_hours" class="form-control" 
                                value="<?php echo $user_booking_prefs['preferred_notice_hours']; ?>" min="0" max="168" step="1">
                            <small class="form-text">Request providers to contact you at least this many hours before service</small>
                        </div>

                        <button type="submit" name="update_booking_preferences" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Preferences
                        </button>
                    </form>
                </div>

                <div class="setting-card">
                    <h3>ℹ️ Booking Information</h3>
                    <div class="alert alert-info">
                        <strong>Max Pending Time:</strong> Bookings automatically cancel after <?php echo $system_settings['max_pending_time']; ?> minutes if not confirmed<br>
                        <strong>Max Cancellations:</strong> Limited to <?php echo $system_settings['max_cancellations_per_month']; ?> per month
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 🔒 Security Section -->
        <?php if ($settings_section === 'security'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-lock"></i> Security & Password
                </div>
                
                <div class="setting-card">
                    <h3>🔑 Change Password</h3>
                    <p class="text-muted">Update your password to keep your account secure</p>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="<?php echo $system_settings['min_password_length']; ?>">
                            <small class="form-text">Minimum <?php echo $system_settings['min_password_length']; ?> characters</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-lock me-2"></i> Update Password
                        </button>
                    </form>
                </div>

                <div class="setting-card">
                    <h3>🛡️ Security Information</h3>
                    <div class="alert alert-info mb-0">
                        <strong>Account Status:</strong> <?php echo $user['is_active'] ? '✓ Active' : '⚠ Inactive'; ?><br>
                        <strong>Email Verified:</strong> <?php echo $user['email_verified'] ? '✓ Yes' : '✗ No'; ?><br>
                        <strong>Last Password Change:</strong> <?php echo $user['password_changed_at'] ? date('M d, Y', strtotime($user['password_changed_at'])) : 'Never'; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ⚠️ Account Control Section -->
        <?php if ($settings_section === 'control'): ?>
            <div class="settings-section active">
                <div class="section-title">
                    <i class="fas fa-exclamation-triangle"></i> Account Control
                </div>
                
                <div class="danger-zone">
                    <h3><i class="fas fa-pause-circle"></i> Deactivate Account</h3>
                    <p>
                        Temporarily disable your account. You can reactivate it anytime by logging in.
                        Your profile will be hidden from search results, but your data will be preserved.
                    </p>
                    <form method="POST" onsubmit="return confirm('⏸️ Are you sure? You can reactivate anytime.');">
                        <button type="submit" name="deactivate_account" class="btn btn-outline-danger">
                            <i class="fas fa-pause-circle me-2"></i> Deactivate Account
                        </button>
                    </form>
                </div>

                <div class="danger-zone">
                    <h3><i class="fas fa-trash-alt"></i> Delete Account</h3>
                    <p>
                        <?php if ($system_settings['archive_deleted_accounts']): ?>
                            Your account and data will be archived for <?php echo $system_settings['data_retention_days']; ?> days. After this period, everything will be permanently deleted. <strong>This action cannot be undone.</strong>
                        <?php else: ?>
                            <strong>Permanently delete</strong> your account and all associated data. This action cannot be undone.
                        <?php endif; ?>
                    </p>
                    <form method="POST" onsubmit="return confirm('🚨 WARNING: This will permanently delete your account and all data. Are you absolutely sure?');">
                        <div class="mb-3">
                            <label class="form-label">Confirm with your password:</label>
                            <input type="password" name="password_confirm" class="form-control" required placeholder="Enter your password">
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i> Delete Account Permanently
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);
    </script>
<?php client_header_render_scripts(); ?>
</body>
</html>
