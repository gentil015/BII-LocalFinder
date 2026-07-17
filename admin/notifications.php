<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Load notification settings from system_settings
function getNotificationSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_COLUMN);
    return $result !== false ? $result : $default;
}

// Check if notifications are enabled
$email_enabled = getNotificationSetting($db, 'enable_email_notifications', '1');
$sms_enabled = getNotificationSetting($db, 'enable_sms_notifications', '0');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 🔵 1. System Notifications
    if (isset($_POST['send_system_notification'])) {
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);
        $notification_type = sanitize($_POST['notification_type']);
        $target_audience = sanitize($_POST['target_audience']);
        $target_category = intval($_POST['target_category'] ?? 0);
        $target_district = sanitize($_POST['target_district'] ?? '');
        $schedule_time = sanitize($_POST['schedule_time'] ?? '');
        $is_emergency = isset($_POST['is_emergency']) ? 1 : 0;
        $send_email = isset($_POST['send_email']) && $email_enabled ? 1 : 0;
        $send_sms = isset($_POST['send_sms']) && $sms_enabled ? 1 : 0;
        
        try {
            // Build query based on target audience
            $query = "SELECT u.id, u.email, u.phone, u.full_name, u.user_type FROM users u WHERE 1=1";
            $params = [];
            
            switch ($target_audience) {
                case 'all':
                    // No additional filters
                    break;
                case 'clients':
                    $query .= " AND u.user_type = 'client'";
                    break;
                case 'providers':
                    $query .= " AND u.user_type = 'provider'";
                    break;
                case 'category_providers':
                    $query .= " AND u.user_type = 'provider' AND u.id IN (
                        SELECT sp.user_id FROM service_providers sp 
                        JOIN provider_services ps ON sp.id = ps.provider_id 
                        WHERE ps.category_id = ?
                    )";
                    $params[] = $target_category;
                    break;
                case 'district_users':
                    $query .= " AND (u.user_type = 'client' OR u.id IN (
                        SELECT user_id FROM service_providers WHERE location LIKE ?
                    ))";
                    $params[] = "%$target_district%";
                    break;
                case 'verified_providers':
                    $query .= " AND u.user_type = 'provider' AND u.id IN (
                        SELECT user_id FROM service_providers WHERE is_verified = 1
                    )";
                    break;
                case 'premium_providers':
                    $query .= " AND u.user_type = 'provider' AND u.id IN (
                        SELECT user_id FROM service_providers WHERE is_premium = 1
                    )";
                    break;
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            $sent_email_count = 0;
            $sent_sms_count = 0;
            $failed_count = 0;
            
            $notificationEngine = new NotificationEngine();

            foreach ($users as $user) {
                $log_data = [
                    'user_id' => $user['id'],
                    'user_type' => $user['user_type'],
                    'notification_type' => $notification_type,
                    'subject' => $subject,
                    'message' => $message,
                    'target_audience' => $target_audience,
                    'is_emergency' => $is_emergency,
                    'sent_email' => 0,
                    'sent_sms' => 0
                ];
                
                try {
                    $sendOptions = [
                        'force_sms' => (bool) $send_sms,
                        'force_email' => (bool) $send_email
                    ];

                    $result = $notificationEngine->send($user, $subject, $message, $sendOptions);

                    if (!empty($result['sms']['success']) || !empty($result['sms']['demo_mode'])) {
                        $sent_sms_count++;
                        $log_data['sent_sms'] = 1;
                    }

                    if (!empty($result['email']['success'])) {
                        $sent_email_count++;
                        $log_data['sent_email'] = 1;
                    }

                    $log_data['status'] = !empty($result['errors']) ? 'failed' : 'sent';
                    $sentVia = [];
                    if (!empty($result['sms']['success']) || !empty($result['sms']['demo_mode'])) {
                        $sentVia[] = 'sms';
                    }
                    if (!empty($result['email']['success'])) {
                        $sentVia[] = 'email';
                    }
                    $sentVia = $sentVia ? implode(',', $sentVia) : 'failed';

                    // Log the notification
                    $log_stmt = $db->prepare("
                        INSERT INTO notification_logs 
                        (user_id, user_type, notification_type, subject, message, target_audience, 
                         sent_via, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $log_stmt->execute([
                        $log_data['user_id'],
                        $log_data['user_type'],
                        $log_data['notification_type'],
                        $log_data['subject'],
                        $log_data['message'],
                        $log_data['target_audience'],
                        $sentVia,
                        $log_data['status'] ?? 'sent'
                    ]);
                    
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Failed to send notification to user {$user['id']}: " . $e->getMessage());
                }
            }
            
            $success = "Notification sent successfully! ";
            $success .= $sent_email_count ? "Emails: {$sent_email_count}, " : "";
            $success .= $sent_sms_count ? "SMS: {$sent_sms_count}, " : "";
            $success .= $failed_count ? "Failed: {$failed_count}" : "";
            
        } catch (Exception $e) {
            $errors[] = "Failed to send notification: " . $e->getMessage();
        }
    }
    
    // 🟣 2. Provider Account Notifications
    if (isset($_POST['send_provider_notification'])) {
        $provider_id = intval($_POST['provider_id']);
        $notification_type = sanitize($_POST['provider_notification_type']);
        $custom_message = sanitize($_POST['custom_message'] ?? '');
        $send_email = isset($_POST['send_email_provider']) && $email_enabled ? 1 : 0;
        $send_sms = isset($_POST['send_sms_provider']) && $sms_enabled ? 1 : 0;
        
        try {
            // Get provider details
            $stmt = $db->prepare("
                SELECT u.id, u.email, u.phone, u.full_name, u.user_type 
                FROM users u 
                JOIN service_providers sp ON u.id = sp.user_id 
                WHERE sp.id = ?
            ");
            $stmt->execute([$provider_id]);
            $provider = $stmt->fetch();
            
            if ($provider) {
                // Determine message based on type
                $subject = '';
                $message = '';
                
                switch ($notification_type) {
                    case 'account_approved':
                        $subject = "Your BII LocalFinder Account Has Been Approved";
                        $message = "Congratulations! Your provider account has been approved. You can now start receiving booking requests.";
                        break;
                    case 'account_rejected':
                        $subject = "Your BII LocalFinder Application Status";
                        $message = "We regret to inform you that your provider application has been rejected. " . $custom_message;
                        break;
                    case 'account_suspended':
                        $subject = "Account Suspension Notice";
                        $message = "Your provider account has been temporarily suspended. " . $custom_message;
                        break;
                    case 'document_rejected':
                        $subject = "Document Verification Update";
                        $message = "Some of your submitted documents require additional verification. " . $custom_message;
                        break;
                    case 'verification_upgrade':
                        $subject = "Upgrade Your Verification Level";
                        $message = "You're eligible for a verification level upgrade! " . $custom_message;
                        break;
                    case 'warning':
                        $subject = "Important Notice Regarding Your Account";
                        $message = $custom_message;
                        break;
                    case 'new_booking':
                        $subject = "New Booking Request";
                        $message = "You have received a new booking request. Please check your dashboard for details.";
                        break;
                }

                $notificationEngine = new NotificationEngine();
                $sendOptions = [
                    'force_sms' => (bool) $send_sms,
                    'force_email' => (bool) $send_email,
                ];

                $result = $notificationEngine->send($provider, $subject, $message, $sendOptions);
                $sentEmail = !empty($result['email']['success']);
                $sentSms = !empty($result['sms']['success']) || !empty($result['sms']['demo_mode']);

                if ($sentEmail || $sentSms) {
                    $sentVia = [];
                    if ($sentEmail) {
                        $sentVia[] = 'email';
                    }
                    if ($sentSms) {
                        $sentVia[] = 'sms';
                    }
                    $sentVia = $sentVia ? implode(',', $sentVia) : 'failed';

                    // Log the notification
                    $log_stmt = $db->prepare("\n                        INSERT INTO notification_logs \n                        (user_id, user_type, notification_type, subject, message, target_audience, \n                         sent_via, status, created_at) \n                        VALUES (?, ?, ?, ?, ?, 'individual', ?, 'sent', NOW())\n                    ");
                    $log_stmt->execute([
                        $provider['id'],
                        $provider['user_type'],
                        $notification_type,
                        $subject,
                        $message,
                        $sentVia
                    ]);

                    $success = "Provider notification sent successfully";
                } else {
                    $errors[] = 'Provider notification failed (email/sms)';
                }
            }
        } catch (Exception $e) {
            $errors[] = "Failed to send provider notification: " . $e->getMessage();
        }
    }
    
    // 🟢 3. Client Account Notifications
    if (isset($_POST['send_client_notification'])) {
        $client_id = intval($_POST['client_id']);
        $notification_type = sanitize($_POST['client_notification_type']);
        $custom_message = sanitize($_POST['client_custom_message'] ?? '');
        $send_email = isset($_POST['send_email_client']) && $email_enabled ? 1 : 0;
        $send_sms = isset($_POST['send_sms_client']) && $sms_enabled ? 1 : 0;
        
        try {
            // Get client details
            $stmt = $db->prepare("SELECT id, email, phone, full_name, user_type FROM users WHERE id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();
            
            if ($client) {
                $subject = '';
                $message = '';
                
                switch ($notification_type) {
                    case 'welcome':
                        $subject = "Welcome to BII LocalFinder!";
                        $message = "Thank you for joining BII LocalFinder! We're excited to help you find the best service providers.";
                        break;
                    case 'security_alert':
                        $subject = "Security Alert - BII LocalFinder";
                        $message = "We've detected suspicious activity on your account. " . $custom_message;
                        break;
                    case 'promotional':
                        $subject = "Special Offer - BII LocalFinder";
                        $message = $custom_message;
                        break;
                    case 'complaint_update':
                        $subject = "Update on Your Complaint";
                        $message = "There's an update regarding your recent complaint. " . $custom_message;
                        break;
                    case 'booking_confirmed':
                        $subject = "Booking Confirmed";
                        $message = "Your booking has been confirmed by the service provider.";
                        break;
                    case 'booking_completed':
                        $subject = "Booking Completed";
                        $message = "Your service has been completed. Please rate your experience.";
                        break;
                }

                $notificationEngine = new NotificationEngine();
                $sendOptions = [
                    'force_sms' => (bool) $send_sms,
                    'force_email' => (bool) $send_email,
                ];

                $result = $notificationEngine->send($client, $subject, $message, $sendOptions);
                $sentEmail = !empty($result['email']['success']);
                $sentSms = !empty($result['sms']['success']) || !empty($result['sms']['demo_mode']);

                if ($sentEmail || $sentSms) {
                    $sentVia = [];
                    if ($sentEmail) {
                        $sentVia[] = 'email';
                    }
                    if ($sentSms) {
                        $sentVia[] = 'sms';
                    }
                    $sentVia = $sentVia ? implode(',', $sentVia) : 'failed';

                    // Log the notification
                    $log_stmt = $db->prepare("\n                        INSERT INTO notification_logs \n                        (user_id, user_type, notification_type, subject, message, target_audience, \n                         sent_via, status, created_at) \n                        VALUES (?, ?, ?, ?, ?, 'individual', ?, 'sent', NOW())\n                    ");
                    $log_stmt->execute([
                        $client['id'],
                        $client['user_type'],
                        $notification_type,
                        $subject,
                        $message,
                        $sentVia
                    ]);

                    $success = "Client notification sent successfully";
                } else {
                    $errors[] = 'Client notification failed (email/sms)';
                }
            }
        } catch (Exception $e) {
            $errors[] = "Failed to send client notification: " . $e->getMessage();
        }
    }
    
    // 🟠 4. Template Management
    if (isset($_POST['save_template'])) {
        $template_name = sanitize($_POST['template_name']);
        $template_subject = sanitize($_POST['template_subject']);
        $template_message = sanitize($_POST['template_message']);
        $template_type = sanitize($_POST['template_type']);
        
        try {
            $stmt = $db->prepare("
                INSERT INTO notification_templates (name, subject, message, template_type, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$template_name, $template_subject, $template_message, $template_type]);
            $success = "Template saved successfully";
        } catch (Exception $e) {
            $errors[] = "Failed to save template: " . $e->getMessage();
        }
    }
    
    // 🟡 5. Broadcast System
    if (isset($_POST['send_broadcast'])) {
        $campaign_name = sanitize($_POST['campaign_name']);
        $subject = sanitize($_POST['broadcast_subject']);
        $message = sanitize($_POST['broadcast_message']);
        $target_criteria = $_POST['target_criteria'] ?? [];
        $send_email = isset($_POST['send_email_broadcast']) && $email_enabled ? 1 : 0;
        $send_sms = isset($_POST['send_sms_broadcast']) && $sms_enabled ? 1 : 0;
        
        try {
            // Build query based on campaign criteria
            $query = "SELECT id, email, phone, full_name, user_type FROM users WHERE 1=1";
            $params = [];
            
            if (in_array('active_users', $target_criteria)) {
                $query .= " AND id IN (
                    SELECT DISTINCT client_id FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    UNION
                    SELECT DISTINCT sp.user_id FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )";
            }
            
            if (in_array('top_providers', $target_criteria)) {
                $query .= " AND user_type = 'provider' AND id IN (
                    SELECT user_id FROM service_providers WHERE average_rating >= 4.0
                )";
            }
            
            if (in_array('new_users', $target_criteria)) {
                $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            }
            
            if (in_array('inactive_users', $target_criteria)) {
                $query .= " AND id NOT IN (
                    SELECT DISTINCT client_id FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                    UNION
                    SELECT DISTINCT sp.user_id FROM bookings b JOIN service_providers sp ON b.provider_id = sp.id WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                )";
            }

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            $sent_count = 0;
            $notificationEngine = new NotificationEngine();

            foreach ($users as $user) {
                $sendOptions = [
                    'force_sms' => (bool) $send_sms,
                    'force_email' => (bool) $send_email,
                ];

                $result = $notificationEngine->send($user, $subject, $message, $sendOptions);
                $sentEmail = !empty($result['email']['success']);
                $sentSms = !empty($result['sms']['success']) || !empty($result['sms']['demo_mode']);

                if ($sentEmail || $sentSms) {
                    $sentVia = [];
                    if ($sentEmail) {
                        $sentVia[] = 'email';
                    }
                    if ($sentSms) {
                        $sentVia[] = 'sms';
                    }
                    $sentVia = $sentVia ? implode(',', $sentVia) : 'failed';

                    // Log the notification
                    $log_stmt = $db->prepare("\n                        INSERT INTO notification_logs \n                        (user_id, user_type, notification_type, subject, message, target_audience, \n                         sent_via, status, created_at) \n                        VALUES (?, ?, ?, ?, ?, 'broadcast', ?, 'sent', NOW())\n                    ");
                    $log_stmt->execute([
                        $user['id'],
                        $user['user_type'],
                        'broadcast',
                        $subject,
                        $message,
                        $sentVia
                    ]);

                    $sent_count++;
                }
            }
            
            $success = "Broadcast campaign '{$campaign_name}' sent to {$sent_count} users";
            
        } catch (Exception $e) {
            $errors[] = "Failed to send broadcast: " . $e->getMessage();
        }
    }
    
    // 🔵 6. Notification Settings Update
    if (isset($_POST['update_notification_settings'])) {
        try {
            $notification_settings = [
                'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
                'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
                'smtp_host' => sanitize($_POST['smtp_host']),
                'smtp_port' => intval($_POST['smtp_port']),
                'smtp_username' => sanitize($_POST['smtp_username']),
                'smtp_password' => sanitize($_POST['smtp_password']),
                'smtp_encryption' => sanitize($_POST['smtp_encryption']),
                'sms_provider' => sanitize($_POST['sms_provider']),
                'sms_api_key' => sanitize($_POST['sms_api_key']),
                'sms_api_url' => sanitize($_POST['sms_api_url']),
                'default_notification_email' => sanitize($_POST['default_notification_email']),
                'auto_send_welcome_emails' => isset($_POST['auto_send_welcome_emails']) ? 1 : 0,
                'notify_admin_new_registrations' => isset($_POST['notify_admin_new_registrations']) ? 1 : 0
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
            
            // Reload settings
            $email_enabled = $notification_settings['enable_email_notifications'];
            $sms_enabled = $notification_settings['enable_sms_notifications'];
            
        } catch (Exception $e) {
            $errors[] = "Failed to update notification settings: " . $e->getMessage();
        }
    }
}

// Get data for filters and selects
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$districts = $db->query("SELECT DISTINCT location FROM service_providers WHERE location IS NOT NULL ORDER BY location")->fetchAll();
$providers = $db->query("
    SELECT sp.id, u.full_name, u.email, sp.profession, sp.is_verified, sp.is_premium
    FROM service_providers sp 
    JOIN users u ON sp.user_id = u.id 
    ORDER BY u.full_name
")->fetchAll();
$clients = $db->query("
    SELECT id, full_name, email 
    FROM users 
    WHERE user_type = 'client' 
    ORDER BY full_name
")->fetchAll();

// Get notification logs with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$notification_logs = $db->query("
    SELECT nl.*, u.full_name as user_name, u.email as user_email
    FROM notification_logs nl
    LEFT JOIN users u ON nl.user_id = u.id
    ORDER BY nl.created_at DESC
    LIMIT $limit OFFSET $offset
")->fetchAll();

// Get total count for pagination
$total_logs = $db->query("SELECT COUNT(*) FROM notification_logs")->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Get message logs (in-app messaging)
$message_logs = $db->query("
    SELECT m.*, 
           u1.full_name as sender_name,
           u2.full_name as receiver_name,
           u1.email as sender_email,
           u2.email as receiver_email
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
    ORDER BY m.created_at DESC
    LIMIT 50
")->fetchAll();

// Get templates
$templates = $db->query("SELECT * FROM notification_templates ORDER BY created_at DESC")->fetchAll();

// Get SMTP settings for the form
$smtp_host = getNotificationSetting($db, 'smtp_host', 'smtp.gmail.com');
$smtp_port = getNotificationSetting($db, 'smtp_port', '587');
$smtp_username = getNotificationSetting($db, 'smtp_username', 'dushimegentil0@gmail.com');
$smtp_encryption = getNotificationSetting($db, 'smtp_encryption', 'tls');
$sms_provider = getNotificationSetting($db, 'sms_provider', 'twilio');
$sms_api_key = getNotificationSetting($db, 'sms_api_key', '');
$default_email = getNotificationSetting($db, 'default_notification_email', 'noreply@biilocalfinder.com');
$auto_welcome = getNotificationSetting($db, 'auto_send_welcome_emails', '1');
$notify_admin = getNotificationSetting($db, 'notify_admin_new_registrations', '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management - BII LocalFinder</title>
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
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        /* Notification Sections */
        .notification-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        /* Quick Actions Grid */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .quick-action-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .quick-action-card h4 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .quick-action-card p {
            color: var(--secondary);
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            color: var(--secondary);
        }
        
        .tab-button:hover {
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
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--secondary);
            font-size: 0.8rem;
        }
        
        /* Checkbox Groups */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        
        .checkbox-item label {
            margin-bottom: 0;
            font-weight: normal;
        }
        
        /* Template Cards */
        .template-card {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .template-card:hover {
            background: #f1f5f9;
        }
        
        .template-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .template-subject {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .template-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Message Preview */
        .message-preview {
            max-height: 200px;
            overflow-y: auto;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-sent {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Button Styles */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0b5ed7;
            color: white;
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .btn-edit {
            background: var(--info);
            color: white;
        }
        
        .btn-delete {
            background: var(--danger);
            color: white;
        }
        
        /* Form Buttons */
        .form-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        
        .empty-state h3 {
            color: var(--secondary);
            margin-bottom: 0.5rem;
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
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .tab-button {
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-group {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .template-actions {
                flex-direction: column;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
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
        
        /* Emergency Alert Styling */
        .emergency-alert {
            border-left-color: var(--danger) !important;
        }
        
        .emergency-alert .section-title {
            color: var(--danger);
        }
        
        /* Filter Sections */
        .filter-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }
        
        /* Log Items */
        .log-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: start;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-info {
            flex: 1;
        }
        
        .log-user {
            font-weight: 600;
            color: var(--dark);
        }
        
        .log-subject {
            color: var(--secondary);
            margin: 0.25rem 0;
        }
        
        .log-meta {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: var(--primary);
            color: white;
        }
        
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Status Indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-bell me-2"></i> Notification & Communication Management</h1>
                        <p>Manage all platform communications, notifications, and messaging</p>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="status-indicator <?php echo $email_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                            <i class="fas <?php echo $email_enabled ? 'fa-check' : 'fa-times'; ?>"></i>
                            Email <?php echo $email_enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                        <span class="status-indicator <?php echo $sms_enabled ? 'status-enabled' : 'status-disabled'; ?>">
                            <i class="fas <?php echo $sms_enabled ? 'fa-check' : 'fa-times'; ?>"></i>
                            SMS <?php echo $sms_enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </div>
                </div>
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

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="quick-action-card" onclick="switchTab('system')">
                    <div class="quick-action-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <h4>System Broadcast</h4>
                    <p>Send announcements to all users</p>
                </div>
                
                <div class="quick-action-card" onclick="switchTab('providers')">
                    <div class="quick-action-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h4>Provider Notifications</h4>
                    <p>Send account updates to providers</p>
                </div>
                
                <div class="quick-action-card" onclick="switchTab('clients')">
                    <div class="quick-action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Client Notifications</h4>
                    <p>Send updates to clients</p>
                </div>
                
                <div class="quick-action-card" onclick="switchTab('templates')">
                    <div class="quick-action-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h4>Message Templates</h4>
                    <p>Manage notification templates</p>
                </div>
                
                <div class="quick-action-card" onclick="switchTab('settings')">
                    <div class="quick-action-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h4>Settings</h4>
                    <p>Configure notification system</p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-button active" onclick="switchTab('system')">System Broadcast</button>
                <button class="tab-button" onclick="switchTab('providers')">Provider Notifications</button>
                <button class="tab-button" onclick="switchTab('clients')">Client Notifications</button>
                <button class="tab-button" onclick="switchTab('broadcast')">Marketing Broadcast</button>
                <button class="tab-button" onclick="switchTab('templates')">Message Templates</button>
                <button class="tab-button" onclick="switchTab('logs')">Communication Logs</button>
                <button class="tab-button" onclick="switchTab('settings')">Settings</button>
            </div>

            <!-- 🔵 System Notifications Tab -->
            <div id="system" class="tab-content active">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-bullhorn me-2"></i> Send System Notification</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Notification Type</label>
                                <select name="notification_type" class="form-select" required>
                                    <option value="announcement">General Announcement</option>
                                    <option value="emergency">Emergency Alert</option>
                                    <option value="maintenance">System Maintenance</option>
                                    <option value="update">Platform Update</option>
                                    <option value="promotion">Promotional</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Target Audience</label>
                                <select name="target_audience" id="target_audience" class="form-select" required onchange="toggleTargetFilters()">
                                    <option value="all">All Users</option>
                                    <option value="clients">All Clients</option>
                                    <option value="providers">All Providers</option>
                                    <option value="verified_providers">Verified Providers Only</option>
                                    <option value="premium_providers">Premium Providers Only</option>
                                    <option value="category_providers">Providers by Category</option>
                                    <option value="district_users">Users by District</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="category_filter" style="display: none;">
                                <label>Category</label>
                                <select name="target_category" class="form-select">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" id="district_filter" style="display: none;">
                                <label>District</label>
                                <select name="target_district" class="form-select">
                                    <option value="">Select District</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option value="<?php echo $district['location']; ?>"><?php echo htmlspecialchars($district['location']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Subject</label>
                                <input type="text" name="subject" class="form-control" required placeholder="Notification subject...">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Message</label>
                                <textarea name="message" class="form-control" rows="8" required placeholder="Enter your notification message..."></textarea>
                                <div class="message-preview" id="message_preview" style="display: none;">
                                    <strong>Preview:</strong>
                                    <div id="preview_content"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Schedule (Optional)</label>
                                <input type="datetime-local" name="schedule_time" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Delivery Methods</label>
                                <div class="checkbox-group">
                                    <?php if ($email_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_email" id="send_email" value="1" checked>
                                        <label for="send_email">Send Email</label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sms_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_sms" id="send_sms" value="1">
                                        <label for="send_sms">Send SMS</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if (!$email_enabled && !$sms_enabled): ?>
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Both email and SMS notifications are disabled in settings.</span>
                                    <?php elseif (!$email_enabled): ?>
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Email notifications are disabled in settings.</span>
                                    <?php elseif (!$sms_enabled): ?>
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> SMS notifications are disabled in settings.</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="is_emergency" value="1">
                                    Mark as Emergency Alert
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="send_system_notification" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Notification
                            </button>
                            
                            <button type="button" class="btn btn-secondary" onclick="previewMessage()">
                                <i class="fas fa-eye me-2"></i> Preview Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 🟣 Provider Notifications Tab -->
            <div id="providers" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-tools me-2"></i> Send Provider Notification</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Select Provider</label>
                                <select name="provider_id" class="form-select" required>
                                    <option value="">Select Provider</option>
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?php echo $provider['id']; ?>">
                                            <?php echo htmlspecialchars($provider['full_name'] . ' - ' . $provider['profession']); ?>
                                            <?php if ($provider['is_verified']): ?> ✓ <?php endif; ?>
                                            <?php if ($provider['is_premium']): ?> ⭐ <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Notification Type</label>
                                <select name="provider_notification_type" class="form-select" required>
                                    <option value="account_approved">Account Approved</option>
                                    <option value="account_rejected">Account Rejected</option>
                                    <option value="account_suspended">Account Suspended</option>
                                    <option value="document_rejected">Document Rejected</option>
                                    <option value="verification_upgrade">Verification Upgrade</option>
                                    <option value="warning">Warning Message</option>
                                    <option value="new_booking">New Booking Alert</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Custom Message (Optional)</label>
                                <textarea name="custom_message" class="form-control" rows="4" placeholder="Add additional details or custom message..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Delivery Methods</label>
                                <div class="checkbox-group">
                                    <?php if ($email_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_email_provider" id="send_email_provider" value="1" checked>
                                        <label for="send_email_provider">Send Email</label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sms_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_sms_provider" id="send_sms_provider" value="1">
                                        <label for="send_sms_provider">Send SMS</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="send_provider_notification" class="btn btn-primary">
                                <i class="fas fa-envelope me-2"></i> Send to Provider
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 🟢 Client Notifications Tab -->
            <div id="clients" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-users me-2"></i> Send Client Notification</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Select Client</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Select Client</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>">
                                            <?php echo htmlspecialchars($client['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Notification Type</label>
                                <select name="client_notification_type" class="form-select" required>
                                    <option value="welcome">Welcome Message</option>
                                    <option value="security_alert">Security Alert</option>
                                    <option value="promotional">Promotional Message</option>
                                    <option value="complaint_update">Complaint Update</option>
                                    <option value="booking_confirmed">Booking Confirmed</option>
                                    <option value="booking_completed">Booking Completed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Custom Message</label>
                                <textarea name="client_custom_message" class="form-control" rows="4" placeholder="Enter your message..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Delivery Methods</label>
                                <div class="checkbox-group">
                                    <?php if ($email_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_email_client" id="send_email_client" value="1" checked>
                                        <label for="send_email_client">Send Email</label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sms_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_sms_client" id="send_sms_client" value="1">
                                        <label for="send_sms_client">Send SMS</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="send_client_notification" class="btn btn-primary">
                                <i class="fas fa-envelope me-2"></i> Send to Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 🟡 Broadcast System Tab -->
            <div id="broadcast" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-broadcast-tower me-2"></i> Marketing Broadcast Campaign</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Campaign Name</label>
                                <input type="text" name="campaign_name" class="form-control" required placeholder="Enter campaign name...">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Target Criteria</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="target_criteria[]" value="active_users" id="active_users">
                                        <label for="active_users">Active Users (Last 30 days)</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="target_criteria[]" value="top_providers" id="top_providers">
                                        <label for="top_providers">Top Rated Providers (4.0+ rating)</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="target_criteria[]" value="new_users" id="new_users">
                                        <label for="new_users">New Users (Last 7 days)</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="target_criteria[]" value="inactive_users" id="inactive_users">
                                        <label for="inactive_users">Inactive Users (60+ days)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Subject</label>
                                <input type="text" name="broadcast_subject" class="form-control" required placeholder="Campaign subject...">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Message</label>
                                <textarea name="broadcast_message" class="form-control" rows="8" required placeholder="Enter your broadcast message..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Delivery Methods</label>
                                <div class="checkbox-group">
                                    <?php if ($email_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_email_broadcast" id="send_email_broadcast" value="1" checked>
                                        <label for="send_email_broadcast">Send Email</label>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($sms_enabled): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="send_sms_broadcast" id="send_sms_broadcast" value="1">
                                        <label for="send_sms_broadcast">Send SMS</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="send_broadcast" class="btn btn-success">
                                <i class="fas fa-bullhorn me-2"></i> Launch Broadcast Campaign
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 🟠 Templates Tab -->
            <div id="templates" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-plus-circle me-2"></i> Create New Template</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Template Name</label>
                                <input type="text" name="template_name" class="form-control" required placeholder="e.g., Account Approval">
                            </div>
                            
                            <div class="form-group">
                                <label>Template Type</label>
                                <select name="template_type" class="form-select" required>
                                    <option value="provider">Provider Notification</option>
                                    <option value="client">Client Notification</option>
                                    <option value="system">System Announcement</option>
                                    <option value="booking">Booking Related</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Subject</label>
                                <input type="text" name="template_subject" class="form-control" required placeholder="Template subject...">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Message Template</label>
                                <textarea name="template_message" class="form-control" rows="8" required placeholder="Enter your template message..."></textarea>
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="save_template" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save Template
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-list me-2"></i> Saved Templates</h3>
                    
                    <?php if (empty($templates)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Templates Found</h3>
                            <p>No templates saved yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card">
                                <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                <div class="template-subject"><?php echo htmlspecialchars($template['subject']); ?></div>
                                <div class="template-actions">
                                    <button class="btn btn-primary btn-sm" onclick="useTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-paper-plane me-1"></i> Use Template
                                    </button>
                                    <button class="btn btn-edit btn-sm" onclick="editTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>
                                    <button class="btn btn-delete btn-sm" onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ⚫ Communication Logs Tab -->
            <div id="logs" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-history me-2"></i> Notification History</h3>
                    
                    <?php if (empty($notification_logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Notification Logs</h3>
                            <p>No notification logs found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Audience</th>
                                        <th>Delivery</th>
                                        <th>Status</th>
                                        <th>Sent At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notification_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['user_email']); ?></small>
                                                <?php else: ?>
                                                    <em>Multiple Users</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['notification_type']); ?></td>
                                            <td><?php echo htmlspecialchars($log['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($log['target_audience']); ?></td>
                                            <td>
                                                <?php if ($log['sent_email']): ?><span class="badge badge-sent">Email</span><?php endif; ?>
                                                <?php if ($log['sent_sms']): ?><span class="badge badge-sent">SMS</span><?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge badge-<?php echo $log['status']; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ⚙️ Settings Tab -->
            <div id="settings" class="tab-content">
                <div class="notification-section">
                    <h3 class="section-title"><i class="fas fa-cogs me-2"></i> Notification System Settings</h3>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="enable_email_notifications" value="1" <?php echo $email_enabled ? 'checked' : ''; ?>>
                                    Enable Email Notifications
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="enable_sms_notifications" value="1" <?php echo $sms_enabled ? 'checked' : ''; ?>>
                                    Enable SMS Notifications
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="auto_send_welcome_emails" value="1" <?php echo $auto_welcome ? 'checked' : ''; ?>>
                                    Auto-send Welcome Emails
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="notify_admin_new_registrations" value="1" <?php echo $notify_admin ? 'checked' : ''; ?>>
                                    Notify Admin of New Registrations
                                </label>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Email Configuration</h5>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtp_host); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>SMTP Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtp_port); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>SMTP Username</label>
                                <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtp_username); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>SMTP Password</label>
                                <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars(getNotificationSetting($db, 'smtp_password', '')); ?>" placeholder="Enter SMTP password">
                            </div>

                            <div class="form-group">
                                <label>SMTP Encryption</label>
                                <select name="smtp_encryption" class="form-select" required>
                                    <option value="tls" <?php echo $smtp_encryption === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $smtp_encryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Default Notification Email</label>
                                <input type="email" name="default_notification_email" class="form-control" value="<?php echo htmlspecialchars($default_email); ?>" required>
                            </div>
                        </div>
                        
                        <h5 class="mb-3 mt-4">SMS Configuration</h5>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>SMS Provider</label>
                                <select name="sms_provider" class="form-select">
                                    <option value="twilio" <?php echo $sms_provider === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                                    <option value="africastalking" <?php echo $sms_provider === 'africastalking' ? 'selected' : ''; ?>>Africa's Talking</option>
                                    <option value="mtn" <?php echo $sms_provider === 'mtn' ? 'selected' : ''; ?>>MTN Rwanda</option>
                                    <option value="airtel" <?php echo $sms_provider === 'airtel' ? 'selected' : ''; ?>>Airtel Money</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>SMS API Key</label>
                                <input type="password" name="sms_api_key" class="form-control" value="<?php echo htmlspecialchars($sms_api_key); ?>" placeholder="Enter SMS API key">
                            </div>
                            
                            <div class="form-group">
                                <label>SMS API URL</label>
                                <input type="text" name="sms_api_url" class="form-control" value="<?php echo htmlspecialchars(getNotificationSetting($db, 'sms_api_url', '')); ?>" placeholder="https://api.provider.com/v1/messages">
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="update_notification_settings" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save Notification Settings
                            </button>
                            
                            <button type="button" class="btn btn-secondary" onclick="testEmailConfiguration()">
                                <i class="fas fa-vial me-2"></i> Test Email Configuration
                            </button>
                        </div>
                    </form>
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
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

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
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }

        // Target audience filters
        function toggleTargetFilters() {
            const targetAudience = document.getElementById('target_audience').value;
            const categoryFilter = document.getElementById('category_filter');
            const districtFilter = document.getElementById('district_filter');
            
            categoryFilter.style.display = 'none';
            districtFilter.style.display = 'none';
            
            if (targetAudience === 'category_providers') {
                categoryFilter.style.display = 'flex';
            } else if (targetAudience === 'district_users') {
                districtFilter.style.display = 'flex';
            }
        }

        // Message preview
        function previewMessage() {
            const message = document.querySelector('textarea[name="message"]').value;
            const preview = document.getElementById('preview_content');
            const previewContainer = document.getElementById('message_preview');
            
            if (message.trim()) {
                preview.innerHTML = message;
                previewContainer.style.display = 'block';
            } else {
                alert('Please enter a message to preview.');
            }
        }

        // Template functions
        function useTemplate(templateId) {
            fetch(`api/get_template.php?id=${templateId}`)
                .then(response => response.json())
                .then(template => {
                    document.querySelector('input[name="subject"]').value = template.subject;
                    document.querySelector('textarea[name="message"]').value = template.message;
                    switchTab('system');
                })
                .catch(error => console.error('Error:', error));
        }

        function editTemplate(templateId) {
            // Implementation for template editing
            alert('Edit template: ' + templateId);
        }

        function deleteTemplate(templateId) {
            if (confirm('Are you sure you want to delete this template?')) {
                fetch(`api/delete_template.php?id=${templateId}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            location.reload();
                        } else {
                            alert('Failed to delete template.');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        // Test email configuration
        function testEmailConfiguration() {
            const email = prompt('Enter email address to test configuration:');
            if (email) {
                fetch('api/test_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Test email sent successfully!');
                    } else {
                        alert('Failed to send test email: ' + result.message);
                    }
                })
                .catch(error => {
                    alert('Error testing email configuration: ' + error.message);
                });
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleTargetFilters(); // Initialize filter visibility
        });
    </script>
</body>
</html>
