<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔵 1. General System Settings
    if (isset($_POST['update_general_settings'])) {
        try {
            $platform_name = sanitize($_POST['platform_name']);
            $contact_email = sanitize($_POST['contact_email']);
            $contact_phone = sanitize($_POST['contact_phone']);
            $platform_description = sanitize($_POST['platform_description']);
            $copyright_text = sanitize($_POST['copyright_text']);
            $timezone = sanitize($_POST['timezone']);
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            
            // Update settings in database
            $settings = [
                'platform_name' => $platform_name,
                'contact_email' => $contact_email,
                'contact_phone' => $contact_phone,
                'platform_description' => $platform_description,
                'copyright_text' => $copyright_text,
                'timezone' => $timezone,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Handle logo upload
            if (isset($_FILES['platform_logo']) && $_FILES['platform_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['platform_logo']['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['platform_logo']['tmp_name'], $file_path)) {
                    $stmt = $db->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('platform_logo', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$filename, $filename]);
                }
            }
            
            $success = "General settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update general settings: " . $e->getMessage();
        }
    }
    
    // 🔴 2. User Account Settings
    if (isset($_POST['update_user_settings'])) {
        try {
            $user_settings = [
                'client_registration' => isset($_POST['client_registration']) ? 1 : 0,
                'provider_registration' => isset($_POST['provider_registration']) ? 1 : 0,
                'provider_verification_required' => isset($_POST['provider_verification_required']) ? 1 : 0,
                'email_verification' => isset($_POST['email_verification']) ? 1 : 0,
                'phone_verification' => isset($_POST['phone_verification']) ? 1 : 0,
                'min_password_length' => intval($_POST['min_password_length']),
                'require_special_chars' => isset($_POST['require_special_chars']) ? 1 : 0,
                'session_timeout' => intval($_POST['session_timeout'])
            ];
            
            foreach ($user_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "User account settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update user settings: " . $e->getMessage();
        }
    }
    
    // 🟣 3. Booking System Settings
    if (isset($_POST['update_booking_settings'])) {
        try {
            $booking_settings = [
                'max_pending_time' => intval($_POST['max_pending_time']),
                'auto_assign_providers' => isset($_POST['auto_assign_providers']) ? 1 : 0,
                'allow_booking_editing' => isset($_POST['allow_booking_editing']) ? 1 : 0,
                'allow_provider_rejection' => isset($_POST['allow_provider_rejection']) ? 1 : 0,
                'auto_cancel_unconfirmed' => isset($_POST['auto_cancel_unconfirmed']) ? 1 : 0,
                'require_rating_after_completion' => isset($_POST['require_rating_after_completion']) ? 1 : 0,
                'max_cancellations_per_month' => intval($_POST['max_cancellations_per_month'])
            ];
            
            foreach ($booking_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "Booking system settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update booking settings: " . $e->getMessage();
        }
    }
    
    // 🟠 4. Notification Settings
    if (isset($_POST['update_notification_settings'])) {
        try {
            $notification_settings = [
                'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
                'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
                'smtp_host' => sanitize($_POST['smtp_host']),
                'smtp_port' => intval($_POST['smtp_port']),
                'smtp_username' => sanitize($_POST['smtp_username']),
                'smtp_encryption' => sanitize($_POST['smtp_encryption']),
                'sms_provider' => sanitize($_POST['sms_provider']),
                'sms_api_key' => sanitize($_POST['sms_api_key'])
            ];
            
            foreach ($notification_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "Notification settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update notification settings: " . $e->getMessage();
        }
    }
    
    // 🟡 5. Location & Category Management
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['category_name']);
        $icon = sanitize($_POST['category_icon']);
        $description = sanitize($_POST['category_description']);
        $is_premium = isset($_POST['is_premium']) ? 1 : 0;
        $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO categories (name, icon, description, is_premium, monthly_fee) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $icon, $description, $is_premium, $monthly_fee]);
            $success = "Category added successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to add category: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_district'])) {
        $name = sanitize($_POST['district_name']);
        $code = sanitize($_POST['district_code']);
        
        try {
            $stmt = $db->prepare("INSERT INTO districts (name, code) VALUES (?, ?)");
            $stmt->execute([$name, $code]);
            $success = "District added successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to add district: " . $e->getMessage();
        }
    }
    
    // 🟢 6. Payment & Monetization Settings
    if (isset($_POST['update_payment_settings'])) {
        try {
            $payment_settings = [
                'enable_commission' => isset($_POST['enable_commission']) ? 1 : 0,
                'commission_rate' => floatval($_POST['commission_rate']),
                'enable_subscriptions' => isset($_POST['enable_subscriptions']) ? 1 : 0,
                'basic_subscription_price' => floatval($_POST['basic_subscription_price']),
                'premium_subscription_price' => floatval($_POST['premium_subscription_price']),
                'featured_listing_price' => floatval($_POST['featured_listing_price']),
                'verification_fee' => floatval($_POST['verification_fee']),
                'enable_payouts' => isset($_POST['enable_payouts']) ? 1 : 0,
                'payment_gateway' => sanitize($_POST['payment_gateway'])
            ];
            
            foreach ($payment_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "Payment settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update payment settings: " . $e->getMessage();
        }
    }
    
    // 🟤 7. Security & Privacy Settings
    if (isset($_POST['update_security_settings'])) {
        try {
            $security_settings = [
                'allowed_file_types' => sanitize($_POST['allowed_file_types']),
                'max_file_size' => intval($_POST['max_file_size']),
                'enable_2fa_admin' => isset($_POST['enable_2fa_admin']) ? 1 : 0,
                'auto_backup' => isset($_POST['auto_backup']) ? 1 : 0,
                'backup_frequency' => sanitize($_POST['backup_frequency']),
                'cookie_consent' => isset($_POST['cookie_consent']) ? 1 : 0
            ];
            
            foreach ($security_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Handle IP blocking
            if (isset($_POST['blocked_ips'])) {
                $blocked_ips = explode(',', sanitize($_POST['blocked_ips']));
                $stmt = $db->prepare("DELETE FROM blocked_ips");
                $stmt->execute();
                
                $stmt = $db->prepare("INSERT INTO blocked_ips (ip_address) VALUES (?)");
                foreach ($blocked_ips as $ip) {
                    $ip = trim($ip);
                    if (!empty($ip)) {
                        $stmt->execute([$ip]);
                    }
                }
            }
            
            $success = "Security settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update security settings: " . $e->getMessage();
        }
    }
    
    // 🟣 8. Developer / System Configuration
    if (isset($_POST['update_developer_settings'])) {
        try {
            $developer_settings = [
                'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
                'api_rate_limit' => intval($_POST['api_rate_limit']),
                'cache_duration' => intval($_POST['cache_duration']),
                'cron_auto_cleanup' => isset($_POST['cron_auto_cleanup']) ? 1 : 0,
                'cron_notifications' => isset($_POST['cron_notifications']) ? 1 : 0
            ];
            
            foreach ($developer_settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Handle webhook URLs
            $webhooks = [
                'payment_webhook' => sanitize($_POST['payment_webhook'] ?? ''),
                'sms_webhook' => sanitize($_POST['sms_webhook'] ?? ''),
                'email_webhook' => sanitize($_POST['email_webhook'] ?? '')
            ];
            
            foreach ($webhooks as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = "Developer settings updated successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to update developer settings: " . $e->getMessage();
        }
    }
    
    // Database optimization
    if (isset($_POST['optimize_database'])) {
        try {
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $db->query("OPTIMIZE TABLE `{$table}`");
            }
            $success = "Database optimized successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to optimize database: " . $e->getMessage();
        }
    }
    
    // Clear cache
    if (isset($_POST['clear_cache'])) {
        try {
            // Clear session files
            $session_files = glob(session_save_path() . '/*');
            foreach ($session_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // Clear temporary files
            $temp_files = glob('../tmp/*');
            foreach ($temp_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            $success = "Cache cleared successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to clear cache: " . $e->getMessage();
        }
    }
}

// Load current settings
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Load categories and districts
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$districts = $db->query("SELECT * FROM districts ORDER BY name")->fetchAll();
$blocked_ips = $db->query("SELECT ip_address FROM blocked_ips")->fetchAll(PDO::FETCH_COLUMN);

// System information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'disk_free_space' => round(disk_free_space('.') / 1024 / 1024 / 1024, 2),
    'disk_total_space' => round(disk_total_space('.') / 1024 / 1024 / 1024, 2),
    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
    'peak_memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - BII LocalFinder</title>
    <!-- Bootstrap CSS -->
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), #0a58ca);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0.2rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem;
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Settings Section */
        .settings-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--secondary);
        }
        
        .tab-button:hover {
            background: #f8f9fa;
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* System Info Grid */
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-label {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Danger Zone */
        .danger-zone {
            border: 2px solid var(--danger);
            background: #fef2f2;
        }
        
        .danger-zone .section-title {
            color: var(--danger);
            border-bottom-color: #fecaca;
        }
        
        /* Lists */
        .category-list, .district-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .category-item, .district-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .category-item:last-child, .district-item:last-child {
            border-bottom: none;
        }
        
        .premium-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* Form Enhancements */
        .form-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                width: 100%;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .tab-button {
                text-align: center;
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
        
        .overlay.active {
            display: block;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Admin Layout -->
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-cogs me-2"></i> System Settings</h1>
                <p>Configure and manage all platform settings and configurations</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-1"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="settings-section">
                <h3 class="section-title">System Information</h3>
                <div class="system-info-grid">
                    <div class="info-card">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?php echo $system_info['php_version']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">MySQL Version</div>
                        <div class="info-value"><?php echo $system_info['mysql_version']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Server Software</div>
                        <div class="info-value"><?php echo $system_info['server_software']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Disk Space</div>
                        <div class="info-value"><?php echo $system_info['disk_free_space']; ?> GB free of <?php echo $system_info['disk_total_space']; ?> GB</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Memory Usage</div>
                        <div class="info-value"><?php echo $system_info['memory_usage']; ?> MB (Peak: <?php echo $system_info['peak_memory_usage']; ?> MB)</div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-button active" onclick="switchTab('general')">General</button>
                <button class="tab-button" onclick="switchTab('users')">User Accounts</button>
                <button class="tab-button" onclick="switchTab('bookings')">Bookings</button>
                <button class="tab-button" onclick="switchTab('notifications')">Notifications</button>
                <button class="tab-button" onclick="switchTab('locations')">Locations & Categories</button>
                <button class="tab-button" onclick="switchTab('payments')">Payments</button>
                <button class="tab-button" onclick="switchTab('security')">Security</button>
                <button class="tab-button" onclick="switchTab('developer')">Developer</button>
            </div>

            <!-- 🔵 General System Settings Tab -->
            <div id="general" class="tab-content active">
                <div class="settings-section">
                    <h3 class="section-title">General System Settings</h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Platform Name</label>
                                <input type="text" class="form-control" name="platform_name" value="<?php echo getSetting($db, 'platform_name', 'BII LocalFinder'); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Contact Email</label>
                                <input type="email" class="form-control" name="contact_email" value="<?php echo getSetting($db, 'contact_email', 'support@biilocalfinder.com'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" class="form-control" name="contact_phone" value="<?php echo getSetting($db, 'contact_phone', '+250 788 123 456'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Platform Logo</label>
                                <input type="file" class="form-control" name="platform_logo" accept="image/*">
                                <small class="form-text text-muted">Current: <?php echo getSetting($db, 'platform_logo', 'default-logo.png'); ?></small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Platform Description</label>
                            <textarea class="form-control" name="platform_description" rows="3"><?php echo getSetting($db, 'platform_description', 'Connecting clients with trusted local service providers'); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Copyright Text</label>
                                <input type="text" class="form-control" name="copyright_text" value="<?php echo getSetting($db, 'copyright_text', '© 2024 BII LocalFinder. All rights reserved.'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <option value="Africa/Kigali" <?php echo getSetting($db, 'timezone') === 'Africa/Kigali' ? 'selected' : ''; ?>>Africa/Kigali</option>
                                    <option value="UTC" <?php echo getSetting($db, 'timezone') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <!-- Add more timezones as needed -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo getSetting($db, 'maintenance_mode') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                        </div>
                        
                        <button type="submit" name="update_general_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save General Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🔴 User Account Settings Tab -->
            <div id="users" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">User Account Settings</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Minimum Password Length</label>
                                <input type="number" class="form-control" name="min_password_length" value="<?php echo getSetting($db, 'min_password_length', '8'); ?>" min="6" max="20">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" name="session_timeout" value="<?php echo getSetting($db, 'session_timeout', '60'); ?>" min="15" max="1440">
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="client_registration" id="client_registration" value="1" <?php echo getSetting($db, 'client_registration', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="client_registration">Enable Client Registration</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="provider_registration" id="provider_registration" value="1" <?php echo getSetting($db, 'provider_registration', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="provider_registration">Enable Provider Registration</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="provider_verification_required" id="provider_verification_required" value="1" <?php echo getSetting($db, 'provider_verification_required') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="provider_verification_required">Require Provider Verification</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="email_verification" id="email_verification" value="1" <?php echo getSetting($db, 'email_verification', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_verification">Enable Email Verification</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="phone_verification" id="phone_verification" value="1" <?php echo getSetting($db, 'phone_verification') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="phone_verification">Enable Phone Verification</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="require_special_chars" id="require_special_chars" value="1" <?php echo getSetting($db, 'require_special_chars') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_special_chars">Require Special Characters in Passwords</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_user_settings" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Save User Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🟣 Booking System Settings Tab -->
            <div id="bookings" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Booking System Settings</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Max Pending Time (minutes)</label>
                                <input type="number" class="form-control" name="max_pending_time" value="<?php echo getSetting($db, 'max_pending_time', '15'); ?>" min="5" max="120">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Max Cancellations per Month</label>
                                <input type="number" class="form-control" name="max_cancellations_per_month" value="<?php echo getSetting($db, 'max_cancellations_per_month', '3'); ?>" min="1" max="10">
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_assign_providers" id="auto_assign_providers" value="1" <?php echo getSetting($db, 'auto_assign_providers') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_assign_providers">Enable Automatic Provider Assignment</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allow_booking_editing" id="allow_booking_editing" value="1" <?php echo getSetting($db, 'allow_booking_editing', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_booking_editing">Allow Clients to Edit Bookings</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allow_provider_rejection" id="allow_provider_rejection" value="1" <?php echo getSetting($db, 'allow_provider_rejection', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_provider_rejection">Allow Providers to Reject Jobs</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_cancel_unconfirmed" id="auto_cancel_unconfirmed" value="1" <?php echo getSetting($db, 'auto_cancel_unconfirmed', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_cancel_unconfirmed">Auto-Cancel Unconfirmed Bookings</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="require_rating_after_completion" id="require_rating_after_completion" value="1" <?php echo getSetting($db, 'require_rating_after_completion') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_rating_after_completion">Require Rating After Completion</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_booking_settings" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Save Booking Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🟠 Notification Settings Tab -->
            <div id="notifications" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Notification Settings</h3>
                    
                    <form method="POST">
                        <div class="checkbox-group mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_email_notifications" id="enable_email_notifications" value="1" <?php echo getSetting($db, 'enable_email_notifications', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_email_notifications">Enable Email Notifications</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_sms_notifications" id="enable_sms_notifications" value="1" <?php echo getSetting($db, 'enable_sms_notifications') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_sms_notifications">Enable SMS Notifications</label>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">Email Configuration</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" value="<?php echo getSetting($db, 'smtp_host', 'smtp.gmail.com'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" name="smtp_port" value="<?php echo getSetting($db, 'smtp_port', '587'); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" name="smtp_username" value="<?php echo getSetting($db, 'smtp_username', 'dushimegentil0@gmail.com'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">SMTP Encryption</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="tls" <?php echo getSetting($db, 'smtp_encryption') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo getSetting($db, 'smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">SMS Configuration</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">SMS Provider</label>
                                <select class="form-select" name="sms_provider">
                                    <option value="twilio" <?php echo getSetting($db, 'sms_provider') === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                    <option value="africastalking" <?php echo getSetting($db, 'sms_provider') === 'africastalking' ? 'selected' : ''; ?>>Africa's Talking</option>
                                    <option value="mtn" <?php echo getSetting($db, 'sms_provider') === 'mtn' ? 'selected' : ''; ?>>MTN Rwanda</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">SMS API Key</label>
                                <input type="password" class="form-control" name="sms_api_key" value="<?php echo getSetting($db, 'sms_api_key'); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_notification_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Notification Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🟡 Location & Category Settings Tab -->
            <div id="locations" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Manage Categories</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Category Name</label>
                                <input type="text" class="form-control" name="category_name" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Icon (Font Awesome)</label>
                                <input type="text" class="form-control" name="category_icon" placeholder="fa-bolt" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Monthly Fee (RWF)</label>
                                <input type="number" class="form-control" name="monthly_fee" value="0" min="0" step="1000">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="category_description" rows="2"></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_premium" id="is_premium" value="1">
                            <label class="form-check-label" for="is_premium">Premium Category (Paid)</label>
                        </div>
                        
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Category
                        </button>
                    </form>
                    
                    <div class="category-list mt-4">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <div>
                                    <strong><i class="fas <?php echo $category['icon']; ?> me-2"></i> <?php echo htmlspecialchars($category['name']); ?></strong>
                                    <?php if ($category['is_premium']): ?>
                                        <span class="premium-badge">PREMIUM</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($category['description']); ?></small>
                                </div>
                                <div>
                                    <button class="btn btn-primary btn-sm">Edit</button>
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3 class="section-title">Manage Districts</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">District Name</label>
                                <input type="text" class="form-control" name="district_name" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">District Code</label>
                                <input type="text" class="form-control" name="district_code" placeholder="KGL" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_district" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add District
                        </button>
                    </form>
                    
                    <div class="district-list mt-4">
                        <?php foreach ($districts as $district): ?>
                            <div class="district-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($district['name']); ?></strong>
                                    <br>
                                    <small class="text-muted">Code: <?php echo htmlspecialchars($district['code']); ?></small>
                                </div>
                                <div>
                                    <button class="btn btn-primary btn-sm">Edit</button>
                                    <button class="btn btn-danger btn-sm">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 🟢 Payment & Monetization Settings Tab -->
            <div id="payments" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Payment & Monetization Settings</h3>
                    
                    <form method="POST">
                        <div class="checkbox-group mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_commission" id="enable_commission" value="1" <?php echo getSetting($db, 'enable_commission') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_commission">Enable Commission System</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_subscriptions" id="enable_subscriptions" value="1" <?php echo getSetting($db, 'enable_subscriptions') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_subscriptions">Enable Provider Subscriptions</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_payouts" id="enable_payouts" value="1" <?php echo getSetting($db, 'enable_payouts') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_payouts">Enable Provider Payouts</label>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">Pricing Configuration</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Commission Rate (%)</label>
                                <input type="number" class="form-control" name="commission_rate" value="<?php echo getSetting($db, 'commission_rate', '10'); ?>" min="0" max="50" step="0.5">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Basic Subscription (RWF/month)</label>
                                <input type="number" class="form-control" name="basic_subscription_price" value="<?php echo getSetting($db, 'basic_subscription_price', '5000'); ?>" min="0" step="1000">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Premium Subscription (RWF/month)</label>
                                <input type="number" class="form-control" name="premium_subscription_price" value="<?php echo getSetting($db, 'premium_subscription_price', '15000'); ?>" min="0" step="1000">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Featured Listing (RWF/month)</label>
                                <input type="number" class="form-control" name="featured_listing_price" value="<?php echo getSetting($db, 'featured_listing_price', '10000'); ?>" min="0" step="1000">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Verification Fee (RWF)</label>
                                <input type="number" class="form-control" name="verification_fee" value="<?php echo getSetting($db, 'verification_fee', '2000'); ?>" min="0" step="1000">
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Payment Gateway</h5>
                        <div class="mb-3">
                            <label class="form-label">Primary Payment Gateway</label>
                            <select class="form-select" name="payment_gateway">
                                <option value="flutterwave" <?php echo getSetting($db, 'payment_gateway') === 'flutterwave' ? 'selected' : ''; ?>>Flutterwave</option>
                                <option value="paypal" <?php echo getSetting($db, 'payment_gateway') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="mtn" <?php echo getSetting($db, 'payment_gateway') === 'mtn' ? 'selected' : ''; ?>>MTN Mobile Money</option>
                                <option value="airtel" <?php echo getSetting($db, 'payment_gateway') === 'airtel' ? 'selected' : ''; ?>>Airtel Money</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_payment_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Payment Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🟤 Security & Privacy Settings Tab -->
            <div id="security" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Security & Privacy Settings</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Allowed File Types</label>
                                <input type="text" class="form-control" name="allowed_file_types" value="<?php echo getSetting($db, 'allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx'); ?>" placeholder="jpg,png,pdf,doc">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Max File Size (MB)</label>
                                <input type="number" class="form-control" name="max_file_size" value="<?php echo getSetting($db, 'max_file_size', '10'); ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Backup Frequency</label>
                                <select class="form-select" name="backup_frequency">
                                    <option value="daily" <?php echo getSetting($db, 'backup_frequency') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo getSetting($db, 'backup_frequency') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo getSetting($db, 'backup_frequency') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Blocked IP Addresses</label>
                            <textarea class="form-control" name="blocked_ips" rows="3" placeholder="Enter IP addresses separated by commas"><?php echo implode(', ', $blocked_ips); ?></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_2fa_admin" id="enable_2fa_admin" value="1" <?php echo getSetting($db, 'enable_2fa_admin') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_2fa_admin">Enable 2FA for Admin Accounts</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_backup" id="auto_backup" value="1" <?php echo getSetting($db, 'auto_backup', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_backup">Enable Automatic Backups</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cookie_consent" id="cookie_consent" value="1" <?php echo getSetting($db, 'cookie_consent', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cookie_consent">Enable Cookie Consent Banner</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_security_settings" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Save Security Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- 🟣 Developer / System Configuration Tab -->
            <div id="developer" class="tab-content">
                <div class="settings-section">
                    <h3 class="section-title">Developer Settings</h3>
                    
                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">API Rate Limit (requests/minute)</label>
                                <input type="number" class="form-control" name="api_rate_limit" value="<?php echo getSetting($db, 'api_rate_limit', '60'); ?>" min="10" max="1000">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Cache Duration (minutes)</label>
                                <input type="number" class="form-control" name="cache_duration" value="<?php echo getSetting($db, 'cache_duration', '30'); ?>" min="1" max="1440">
                            </div>
                        </div>
                        
                        <h5 class="mb-3">Webhook URLs</h5>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Payment Webhook URL</label>
                                <input type="url" class="form-control" name="payment_webhook" value="<?php echo getSetting($db, 'payment_webhook'); ?>" placeholder="https://yourdomain.com/webhooks/payment">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">SMS Webhook URL</label>
                                <input type="url" class="form-control" name="sms_webhook" value="<?php echo getSetting($db, 'sms_webhook'); ?>" placeholder="https://yourdomain.com/webhooks/sms">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Email Webhook URL</label>
                                <input type="url" class="form-control" name="email_webhook" value="<?php echo getSetting($db, 'email_webhook'); ?>" placeholder="https://yourdomain.com/webhooks/email">
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php echo getSetting($db, 'debug_mode') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="debug_mode">Enable Debug Mode</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cron_auto_cleanup" id="cron_auto_cleanup" value="1" <?php echo getSetting($db, 'cron_auto_cleanup', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cron_auto_cleanup">Enable Auto Cleanup Cron</label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cron_notifications" id="cron_notifications" value="1" <?php echo getSetting($db, 'cron_notifications', '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cron_notifications">Enable Notification Cron</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_developer_settings" class="btn btn-primary mt-3">
                            <i class="fas fa-save me-2"></i> Save Developer Settings
                        </button>
                    </form>
                </div>
                
                <div class="settings-section">
                    <h3 class="section-title">System Maintenance</h3>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST">
                            <button type="submit" name="optimize_database" class="btn btn-warning" onclick="return confirm('Optimize database tables?')">
                                <i class="fas fa-database me-2"></i> Optimize Database
                            </button>
                        </form>
                        
                        <form method="POST">
                            <button type="submit" name="clear_cache" class="btn btn-secondary" onclick="return confirm('Clear all cache?')">
                                <i class="fas fa-broom me-2"></i> Clear Cache
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="settings-section danger-zone">
                    <h3 class="section-title">Danger Zone</h3>
                    <p class="text-danger mb-3">These actions are irreversible. Proceed with caution.</p>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-danger" onclick="alert('Backup functionality would be implemented here')">
                            <i class="fas fa-download me-2"></i> Backup Database
                        </button>
                        
                        <button class="btn btn-danger" onclick="alert('Restore functionality would be implemented here')">
                            <i class="fas fa-upload me-2"></i> Restore Database
                        </button>
                        
                        <button class="btn btn-danger" onclick="if(confirm('This will reset ALL settings to defaults. Are you absolutely sure?')) { alert('Reset functionality would be implemented here') }">
                            <i class="fas fa-refresh me-2"></i> Reset All Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // Tab navigation
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate clicked button
            event.target.classList.add('active');
        }

        // Toggle premium category fields
        document.addEventListener('DOMContentLoaded', function() {
            const premiumCheckbox = document.querySelector('input[name="is_premium"]');
            const monthlyFeeField = document.querySelector('input[name="monthly_fee"]');
            
            if (premiumCheckbox && monthlyFeeField) {
                premiumCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        monthlyFeeField.value = monthlyFeeField.value || '5000';
                    } else {
                        monthlyFeeField.value = '0';
                    }
                });
            }
        });

        // Settings validation
        function validateSettings(form) {
            const commissionRate = form.querySelector('input[name="commission_rate"]');
            if (commissionRate && commissionRate.value > 50) {
                alert('Commission rate cannot exceed 50%');
                return false;
            }
            
            const fileSize = form.querySelector('input[name="max_file_size"]');
            if (fileSize && fileSize.value > 100) {
                alert('Maximum file size cannot exceed 100MB');
                return false;
            }
            
            return true;
        }

        // Add validation to all forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateSettings(this)) {
                    e.preventDefault();
                }
            });
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>