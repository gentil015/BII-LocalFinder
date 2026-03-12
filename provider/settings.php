<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/provider_requirements.php';
require_once '../includes/language.php';

requireProvider();

if (!empty($lastSavedSection)): ?>
    <script>
        if (window.eventBus && typeof window.eventBus.publish === 'function') {
            window.eventBus.publish('settings:updated', {section: '<?php echo htmlspecialchars($lastSavedSection, ENT_QUOTES); ?>'});
        }
    </script>
<?php endif;

// Check maintenance mode
if (isMaintenanceMode() && !isAdmin()) {
    $maintenance_warning = true;
}

$db = Database::getInstance()->getConnection();

// Get settings section from URL (default to 'identity')
$settings_section = isset($_GET['section']) ? sanitize($_GET['section']) : 'identity';
$valid_settings_sections = ['identity', 'visibility', 'pricing', 'availability', 'location', 'ai', 'payment', 'communication', 'notifications', 'reviews', 'security', 'analytics', 'account', 'requirements', 'language'];
if (!in_array($settings_section, $valid_settings_sections)) {
    $settings_section = 'identity';
}

$success = '';
$errors = [];
$warning = '';

// Get provider profile
$stmt = $db->prepare("
    SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name, 
           u.is_verified as email_verified, u.is_active, u.created_at as join_date,
           u.two_factor_enabled, u.login_notifications
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$provider = $stmt->fetch();

// Get provider settings
$providerSettings = [
    'visibility' => [],
    'communication' => [],
    'notifications' => [],
    'ai_features' => [],
    'payment' => [],
    'security' => [],
    'pricing' => [],
    'location' => [],
    'availability' => [],
    'reviews' => []
];

// Load all provider settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider_id = ?");
$stmt->execute([$provider['id']]);
$settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Parse settings
foreach ($settingsData as $key => $value) {
    $parts = explode('_', $key);
    $section = $parts[0];
    $setting = implode('_', array_slice($parts, 1));
    
    if (isset($providerSettings[$section])) {
        $providerSettings[$section][$setting] = $value;
    }
}

// Get verification documents
$stmt = $db->prepare("
    SELECT * FROM verification_documents 
    WHERE provider_id = ? 
    ORDER BY uploaded_at DESC
");
$stmt->execute([$provider['id']]);
$verificationDocs = $stmt->fetchAll();

// Get bank accounts/payment methods
$stmt = $db->prepare("
    SELECT * FROM provider_payment_methods 
    WHERE provider_id = ? AND is_active = 1
    ORDER BY is_default DESC
");
$stmt->execute([$provider['id']]);
$paymentMethods = $stmt->fetchAll();

// Get service categories
$stmt = $db->prepare("
    SELECT c.id, c.name, c.icon, pc.category_id as selected
    FROM categories c
    LEFT JOIN provider_categories pc ON c.id = pc.category_id AND pc.provider_id = ?
    WHERE c.is_active = 1
    ORDER BY c.name
");
$stmt->execute([$provider['id']]);
$allCategories = $stmt->fetchAll();

// Get selected categories
$stmt = $db->prepare("
    SELECT category_id FROM provider_categories WHERE provider_id = ?
");
$stmt->execute([$provider['id']]);
$selectedCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get service areas
$stmt = $db->prepare("
    SELECT * FROM provider_service_areas 
    WHERE provider_id = ? 
    ORDER BY is_primary DESC
");
$stmt->execute([$provider['id']]);
$serviceAreas = $stmt->fetchAll();

// Get analytics data - CORRECTED VERSION (without rating)
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
        COUNT(DISTINCT client_id) as unique_clients,
        COUNT(DISTINCT CASE WHEN status = 'cancelled' THEN id END) as cancelled_jobs,
        COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN id END) as weekly_bookings
    FROM bookings 
    WHERE provider_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$provider['id']]);
$analytics = $stmt->fetch();

// Compute average rating separately (avoid undefined key)
$ratingStmt = $db->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = ?");
$ratingStmt->execute([$provider['id']]);
$ratingRow = $ratingStmt->fetch(PDO::FETCH_ASSOC);
$analytics['avg_rating'] = isset($ratingRow['avg_rating']) && $ratingRow['avg_rating'] !== null ? floatval($ratingRow['avg_rating']) : 0;

// Get session history
$stmt = $db->prepare("
    SELECT device, ip_address, login_time, logout_time, user_agent
    FROM user_sessions 
    WHERE user_id = ? 
    ORDER BY login_time DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$sessionHistory = $stmt->fetchAll();

// Get recent reviews
$stmt = $db->prepare("
    SELECT r.*, u.full_name as client_name, u.profile_image as client_image
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$provider['id']]);
$recentReviews = $stmt->fetchAll();

// Get wallet balance
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as balance
    FROM transactions
    WHERE provider_id = ? AND status = 'completed'
");
$stmt->execute([$provider['id']]);
$walletBalance = 0; // Wallets/withdrawals not supported on this platform

// Get platform settings
$platformSettings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $value) {
    $platformSettings[$key] = $value;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = sanitize($_POST['section'] ?? '');
    
    try {
        $db->beginTransaction();
        
        switch ($section) {
            case 'language':
                // Handle language preference change
                $language = sanitize($_POST['language'] ?? DEFAULT_LANGUAGE);
                
                if (in_array($language, explode(',', implode(',', array_keys(getSupportedLanguages()))))) {
                    if (saveLanguagePreference($_SESSION['user_id'], $language)) {
                        setLanguage($language);
                        $success = __('settings.language.language_saved', [], 'settings');
                    } else {
                        $errors[] = __('settings.language.language_error', [], 'settings');
                    }
                } else {
                    $errors[] = __('settings.language.language_error', [], 'settings');
                }
                break;
            
            case 'identity':
                // Handle identity verification uploads
                $allowedDocs = ['national_id', 'passport', 'driving_license', 'business_registration', 'certificate', 'other'];
                
                foreach ($allowedDocs as $docType) {
                    if (isset($_FILES[$docType]) && $_FILES[$docType]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$docType];
                        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $allowedExt = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                        
                        if (in_array($fileExt, $allowedExt) && $file['size'] <= 5 * 1024 * 1024) {
                            $fileName = "verification_" . $provider['id'] . "_" . $docType . "_" . time() . "." . $fileExt;
                            $uploadPath = "../uploads/verifications/" . $fileName;
                            
                            if (!is_dir('../uploads/verifications')) {
                                mkdir('../uploads/verifications', 0755, true);
                            }
                            
                            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                                // Check if document already exists
                                $stmt = $db->prepare("SELECT id FROM verification_documents WHERE provider_id = ? AND document_type = ?");
                                $stmt->execute([$provider['id'], $docType]);
                                
                                if ($stmt->fetch()) {
                                    $stmt = $db->prepare("UPDATE verification_documents SET document_path = ?, status = 'pending', uploaded_at = NOW() WHERE provider_id = ? AND document_type = ?");
                                    $stmt->execute([$fileName, $provider['id'], $docType]);
                                } else {
                                    $stmt = $db->prepare("INSERT INTO verification_documents (provider_id, document_type, document_path, status, uploaded_at) VALUES (?, ?, ?, 'pending', NOW())");
                                    $stmt->execute([$provider['id'], $docType, $fileName]);
                                }
                                
                                $success .= ucfirst(str_replace('_', ' ', $docType)) . " uploaded successfully. ";
                            }
                        } else {
                            $errors[] = "Invalid file type or size for " . $docType;
                        }
                    }
                }
                
                // Handle selfie verification
                if (isset($_FILES['selfie_verification']) && $_FILES['selfie_verification']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['selfie_verification'];
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png'];
                    
                    if (in_array($fileExt, $allowedExt) && $file['size'] <= 2 * 1024 * 1024) {
                        $fileName = "selfie_" . $provider['id'] . "_" . time() . "." . $fileExt;
                        $uploadPath = "../uploads/verifications/" . $fileName;
                        
                        if (!is_dir('../uploads/verifications')) {
                            mkdir('../uploads/verifications', 0755, true);
                        }
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $stmt = $db->prepare("
                                INSERT INTO verification_documents (provider_id, document_type, document_path, status, uploaded_at)
                                VALUES (?, 'selfie', ?, 'pending', NOW())
                                ON DUPLICATE KEY UPDATE document_path = VALUES(document_path), status = 'pending', uploaded_at = NOW()
                            ");
                            $stmt->execute([$provider['id'], $fileName]);
                            $success .= "Selfie uploaded successfully. ";
                        }
                    } else {
                        $errors[] = "Invalid selfie image. Must be JPG/PNG and under 2MB";
                    }
                }
                break;
                
            case 'visibility':
                $settings = [
                    'show_phone' => isset($_POST['show_phone']) ? 1 : 0,
                    'show_whatsapp' => isset($_POST['show_whatsapp']) ? 1 : 0,
                    'show_exact_location' => isset($_POST['show_exact_location']) ? 1 : 0,
                    'profile_public' => isset($_POST['profile_public']) ? 1 : 0,
                    'appear_in_search' => isset($_POST['appear_in_search']) ? 1 : 0,
                    'appear_available' => isset($_POST['appear_available']) ? 1 : 0,
                    'emergency_service' => isset($_POST['emergency_service']) ? 1 : 0,
                    'night_service' => isset($_POST['night_service']) ? 1 : 0,
                    'weekend_service' => isset($_POST['weekend_service']) ? 1 : 0,
                    'badge_verified' => isset($_POST['badge_verified']) ? 1 : 0,
                    'badge_top_rated' => isset($_POST['badge_top_rated']) ? 1 : 0,
                    'badge_fast_responder' => isset($_POST['badge_fast_responder']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "visibility_" . $key, $value]);
                }
                $success = "Visibility settings updated successfully!";
                break;
                
            case 'communication':
                $settings = [
                    'enable_chat' => isset($_POST['enable_chat']) ? 1 : 0,
                    'enable_whatsapp' => isset($_POST['enable_whatsapp']) ? 1 : 0,
                    'enable_calls' => isset($_POST['enable_calls']) ? 1 : 0,
                    'enable_email' => isset($_POST['enable_email']) ? 1 : 0,
                    'enable_ai_booking' => isset($_POST['enable_ai_booking']) ? 1 : 0,
                    'quiet_hours_start' => sanitize($_POST['quiet_hours_start'] ?? ''),
                    'quiet_hours_end' => sanitize($_POST['quiet_hours_end'] ?? ''),
                    'auto_reply_message' => sanitize($_POST['auto_reply_message'] ?? ''),
                    'busy_message' => sanitize($_POST['busy_message'] ?? ''),
                    'do_not_disturb' => isset($_POST['do_not_disturb']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "communication_" . $key, $value]);
                }
                $success = "Communication settings updated successfully!";
                break;
                
            case 'pricing':
                // Update service categories
                $stmt = $db->prepare("DELETE FROM provider_categories WHERE provider_id = ?");
                $stmt->execute([$provider['id']]);
                
                if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                    $stmt = $db->prepare("INSERT INTO provider_categories (provider_id, category_id) VALUES (?, ?)");
                    foreach ($_POST['categories'] as $categoryId) {
                        $stmt->execute([$provider['id'], intval($categoryId)]);
                    }
                }
                
                // Update pricing settings
                $settings = [
                    'pricing_model' => sanitize($_POST['pricing_model'] ?? 'fixed'),
                    'minimum_price' => floatval($_POST['minimum_price'] ?? 0),
                    'callout_fee' => floatval($_POST['callout_fee'] ?? 0),
                    'emergency_surcharge' => floatval($_POST['emergency_surcharge'] ?? 0),
                    'repeat_client_discount' => floatval($_POST['repeat_client_discount'] ?? 0),
                    'price_negotiable' => isset($_POST['price_negotiable']) ? 1 : 0,
                    'accept_partial_payment' => isset($_POST['accept_partial_payment']) ? 1 : 0,
                    'deposit_percentage' => floatval($_POST['deposit_percentage'] ?? 0),
                    'fixed_price' => floatval($_POST['fixed_price'] ?? 0),
                    'per_hour_rate' => floatval($_POST['per_hour_rate'] ?? 0),
                    'per_day_rate' => floatval($_POST['per_day_rate'] ?? 0)
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "pricing_" . $key, $value]);
                }
                $success = "Pricing settings updated successfully!";
                break;
                
            case 'availability':
                $settings = [
                    'working_days' => isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : '',
                    'working_hours_start' => sanitize($_POST['working_hours_start'] ?? '08:00'),
                    'working_hours_end' => sanitize($_POST['working_hours_end'] ?? '17:00'),
                    'break_start' => sanitize($_POST['break_start'] ?? ''),
                    'break_end' => sanitize($_POST['break_end'] ?? ''),
                    'max_jobs_per_day' => intval($_POST['max_jobs_per_day'] ?? 5),
                    'auto_accept_bookings' => isset($_POST['auto_accept_bookings']) ? 1 : 0,
                    'buffer_time' => intval($_POST['buffer_time'] ?? 15),
                    'google_calendar_sync' => isset($_POST['google_calendar_sync']) ? 1 : 0,
                    'calendar_conflict_prevention' => isset($_POST['calendar_conflict_prevention']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "availability_" . $key, $value]);
                }
                $success = "Availability settings updated successfully!";
                break;
                
            case 'location':
                // Handle service areas
                $stmt = $db->prepare("DELETE FROM provider_service_areas WHERE provider_id = ?");
                $stmt->execute([$provider['id']]);
                
                if (isset($_POST['service_areas']) && is_array($_POST['service_areas'])) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_service_areas (provider_id, area_name, latitude, longitude, radius_km, is_primary)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($_POST['service_areas'] as $index => $area) {
                        if (!empty($area['name']) && !empty($area['lat']) && !empty($area['lng'])) {
                            $isPrimary = ($index == 0) ? 1 : 0;
                            $stmt->execute([
                                $provider['id'],
                                sanitize($area['name']),
                                floatval($area['lat']),
                                floatval($area['lng']),
                                floatval($area['radius'] ?? 5),
                                $isPrimary
                            ]);
                        }
                    }
                }
                
                // Update location settings
                $settings = [
                    'travel_fee_per_km' => floatval($_POST['travel_fee_per_km'] ?? 0),
                    'max_travel_distance' => floatval($_POST['max_travel_distance'] ?? 20),
                    'map_accuracy' => sanitize($_POST['map_accuracy'] ?? 'approximate'),
                    'service_radius' => floatval($_POST['service_radius'] ?? 10),
                    'multiple_areas' => isset($_POST['multiple_areas']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "location_" . $key, $value]);
                }
                $success = "Location settings updated successfully!";
                break;
                
            case 'ai_features':
                $settings = [
                    'enable_ai_assistant' => isset($_POST['enable_ai_assistant']) ? 1 : 0,
                    'ai_auto_reply' => isset($_POST['ai_auto_reply']) ? 1 : 0,
                    'ai_description_improvement' => isset($_POST['ai_description_improvement']) ? 1 : 0,
                    'ai_pricing_suggestions' => isset($_POST['ai_pricing_suggestions']) ? 1 : 0,
                    'ai_availability_optimization' => isset($_POST['ai_availability_optimization']) ? 1 : 0,
                    'ai_fraud_protection' => isset($_POST['ai_fraud_protection']) ? 1 : 0,
                    'ai_auto_select_service' => isset($_POST['ai_auto_select_service']) ? 1 : 0,
                    'ai_auto_schedule' => isset($_POST['ai_auto_schedule']) ? 1 : 0,
                    'ai_auto_quote' => isset($_POST['ai_auto_quote']) ? 1 : 0,
                    'smart_booking_by_prompt' => isset($_POST['smart_booking_by_prompt']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "ai_" . $key, $value]);
                }
                $success = "AI features updated successfully!";
                break;
                
            case 'payment':
                // Update payment methods
                if (isset($_POST['payment_methods']) && is_array($_POST['payment_methods'])) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_payment_methods (provider_id, method_type, account_name, account_number, bank_name, is_default, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            account_name = VALUES(account_name),
                            account_number = VALUES(account_number),
                            bank_name = VALUES(bank_name),
                            is_default = VALUES(is_default)
                    ");
                    
                    foreach ($_POST['payment_methods'] as $method) {
                        if (!empty($method['type']) && !empty($method['account_number'])) {
                            $stmt->execute([
                                $provider['id'],
                                sanitize($method['type']),
                                sanitize($method['account_name']),
                                sanitize($method['account_number']),
                                sanitize($method['bank_name'] ?? ''),
                                isset($method['is_default']) ? 1 : 0
                            ]);
                        }
                    }
                }
                
                // Update payment settings
                $settings = [
                    'payment_methods' => isset($_POST['enabled_payment_methods']) ? implode(',', $_POST['enabled_payment_methods']) : 'cash,mobile_money',
                    'accept_cash' => isset($_POST['accept_cash']) ? 1 : 0,
                    'accept_mobile_money' => isset($_POST['accept_mobile_money']) ? 1 : 0,
                    'accept_wallet' => isset($_POST['accept_wallet']) ? 1 : 0,
                    'pay_after_service' => isset($_POST['pay_after_service']) ? 1 : 0,
                    'commission_transparency' => isset($_POST['commission_transparency']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "payment_" . $key, $value]);
                }
                $success = "Payment settings updated successfully!";
                break;
                
            case 'notifications':
                $settings = [
                    'new_booking_email' => isset($_POST['new_booking_email']) ? 1 : 0,
                    'new_booking_sms' => isset($_POST['new_booking_sms']) ? 1 : 0,
                    'new_booking_push' => isset($_POST['new_booking_push']) ? 1 : 0,
                    'chat_message_email' => isset($_POST['chat_message_email']) ? 1 : 0,
                    'chat_message_sms' => isset($_POST['chat_message_sms']) ? 1 : 0,
                    'chat_message_push' => isset($_POST['chat_message_push']) ? 1 : 0,
                    'payment_received_email' => isset($_POST['payment_received_email']) ? 1 : 0,
                    'payment_received_sms' => isset($_POST['payment_received_sms']) ? 1 : 0,
                    'review_received_email' => isset($_POST['review_received_email']) ? 1 : 0,
                    'review_received_sms' => isset($_POST['review_received_sms']) ? 1 : 0,
                    'review_received_push' => isset($_POST['review_received_push']) ? 1 : 0,
                    'system_announcements_email' => isset($_POST['system_announcements_email']) ? 1 : 0,
                    'system_announcements_sms' => isset($_POST['system_announcements_sms']) ? 1 : 0,
                    'marketing_email' => isset($_POST['marketing_email']) ? 1 : 0,
                    'marketing_sms' => isset($_POST['marketing_sms']) ? 1 : 0
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "notifications_" . $key, $value]);
                }
                $success = "Notification settings updated successfully!";
                break;
                
            case 'reviews':
                $settings = [
                    'public_response' => isset($_POST['public_response']) ? 1 : 0,
                    'thank_you_auto_reply' => isset($_POST['thank_you_auto_reply']) ? 1 : 0,
                    'hide_old_reviews' => isset($_POST['hide_old_reviews']) ? 1 : 0,
                    'review_notifications' => isset($_POST['review_notifications']) ? 1 : 0,
                    'rating_visibility' => sanitize($_POST['rating_visibility'] ?? 'public')
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "reviews_" . $key, $value]);
                }
                $success = "Review settings updated successfully!";
                break;
                
            case 'security':
                // Update security settings
                $settings = [
                    'enable_2fa' => isset($_POST['enable_2fa']) ? 1 : 0,
                    'login_alerts' => isset($_POST['login_alerts']) ? 1 : 0,
                    'emergency_contact' => sanitize($_POST['emergency_contact'] ?? ''),
                    'panic_button_enabled' => isset($_POST['panic_button_enabled']) ? 1 : 0,
                    'report_abusive_clients' => isset($_POST['report_abusive_clients']) ? 1 : 0,
                    'job_cancellation_protection' => isset($_POST['job_cancellation_protection']) ? 1 : 0,
                    'session_timeout' => intval($_POST['session_timeout'] ?? 30)
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$provider['id'], "security_" . $key, $value]);
                }
                
                // Handle password change
                if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
                    // Get current password hash
                    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $currentHash = $stmt->fetchColumn();
                    
                    if (password_verify($_POST['current_password'], $currentHash)) {
                        if ($_POST['new_password'] === $_POST['confirm_password']) {
                            $newPasswordHash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?");
                            $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
                            $success .= " Password changed successfully!";
                        } else {
                            $errors[] = "New passwords do not match";
                        }
                    } else {
                        $errors[] = "Current password is incorrect";
                    }
                }
                
                // Update 2FA settings in users table
                if (isset($_POST['enable_2fa'])) {
                    $stmt = $db->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
                    $stmt->execute([$_POST['enable_2fa'] ? 1 : 0, $_SESSION['user_id']]);
                }
                
                // Update login notifications in users table
                if (isset($_POST['login_alerts'])) {
                    $stmt = $db->prepare("UPDATE users SET login_notifications = ? WHERE id = ?");
                    $stmt->execute([$_POST['login_alerts'] ? 1 : 0, $_SESSION['user_id']]);
                }
                break;
                
            case 'analytics':
                // Analytics settings are view-only, no update needed
                $success = "Analytics preferences saved!";
                break;
                
            case 'account':
                $action = sanitize($_POST['account_action'] ?? '');
                
                switch ($action) {
                    case 'deactivate':
                        $stmt = $db->prepare("UPDATE users SET is_active = 0, deactivated_at = NOW() WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $warning = "Your account has been deactivated. You can reactivate by logging in.";
                        break;
                        
                    case 'export_data':
                        // Generate data export
                        $exportData = [
                            'profile' => $provider,
                            'verifications' => $verificationDocs,
                            'bookings' => [],
                            'reviews' => [],
                            'transactions' => [],
                            'settings' => $providerSettings
                        ];
                        
                        // Add bookings
                        $stmt = $db->prepare("SELECT * FROM bookings WHERE provider_id = ?");
                        $stmt->execute([$provider['id']]);
                        $exportData['bookings'] = $stmt->fetchAll();
                        
                        // Add reviews
                        $stmt = $db->prepare("SELECT * FROM reviews WHERE provider_id = ?");
                        $stmt->execute([$provider['id']]);
                        $exportData['reviews'] = $stmt->fetchAll();
                        
                        // Add transactions
                        $stmt = $db->prepare("SELECT * FROM transactions WHERE provider_id = ?");
                        $stmt->execute([$provider['id']]);
                        $exportData['transactions'] = $stmt->fetchAll();
                        
                        // Create export file
                        $exportFile = "data_export_" . $provider['id'] . "_" . date('Y-m-d_H-i-s') . ".json";
                        $exportPath = "../exports/" . $exportFile;
                        
                        if (!is_dir('../exports')) {
                            mkdir('../exports', 0755, true);
                        }
                        
                        file_put_contents($exportPath, json_encode($exportData, JSON_PRETTY_PRINT));
                        
                        // Create download link
                        $downloadLink = "../exports/" . $exportFile;
                        $success = "Data export created. <a href='$downloadLink' download class='btn btn-sm btn-success mt-2'>Download Now</a>";
                        break;
                        
                    case 'delete_account':
                        if ($_POST['confirm_delete'] === 'DELETE') {
                            // Mark for deletion (soft delete)
                            $stmt = $db->prepare("UPDATE users SET delete_requested_at = NOW(), is_active = 0 WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $warning = "Account deletion requested. Your account will be permanently deleted after 30 days.";
                        } else {
                            $errors[] = "Please type DELETE to confirm account deletion";
                        }
                        break;
                        
                    case 'switch_category':
                        $newCategory = intval($_POST['new_category'] ?? 0);
                        if ($newCategory > 0) {
                            $stmt = $db->prepare("UPDATE service_providers SET category_id = ? WHERE user_id = ?");
                            $stmt->execute([$newCategory, $_SESSION['user_id']]);
                            $success = "Service category switched successfully!";
                        }
                        break;
                }
                break;
        }
        
        $db->commit();

        // Remember which section was saved so we can notify other pages/tabs
        $lastSavedSection = $section;
        
        // Refresh settings
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM provider_settings WHERE provider_id = ?");
        $stmt->execute([$provider['id']]);
        $settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Re-parse settings
        $providerSettings = [
            'visibility' => [], 'communication' => [], 'notifications' => [],
            'ai_features' => [], 'payment' => [], 'security' => [],
            'pricing' => [], 'location' => [], 'availability' => [], 'reviews' => []
        ];
        
        foreach ($settingsData as $key => $value) {
            $parts = explode('_', $key);
            $section = $parts[0];
            $setting = implode('_', array_slice($parts, 1));
            
            if (isset($providerSettings[$section])) {
                $providerSettings[$section][$setting] = $value;
            }
        }
        
        // Refresh provider data
        $stmt = $db->prepare("
            SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name, 
                   u.is_verified as email_verified, u.is_active, u.created_at as join_date,
                   u.two_factor_enabled, u.login_notifications
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $provider = $stmt->fetch();
        // clear section var for later use
        $section = $section ?? '';
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "Failed to update settings: " . $e->getMessage();
        error_log("Settings update error: " . $e->getMessage());
    }
}

// Calculate verification progress
$verificationProgress = 0;
$verificationSteps = [
    'email_verified' => $provider['email_verified'] ? 20 : 0,
    'phone_verified' => 0,
    'national_id' => 0,
    'selfie' => 0,
    'business_reg' => 0,
    'certificate' => 0
];

// Check phone verification (assuming phone_verified field exists in users table)
$stmt = $db->prepare("SELECT phone_verified FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$verificationSteps['phone_verified'] = ($user['phone_verified'] ?? 0) ? 20 : 0;

foreach ($verificationDocs as $doc) {
    if ($doc['status'] === 'approved') {
        switch ($doc['document_type']) {
            case 'national_id':
            case 'passport':
                $verificationSteps['national_id'] = 20;
                break;
            case 'selfie':
                $verificationSteps['selfie'] = 20;
                break;
            case 'business_registration':
                $verificationSteps['business_reg'] = 20;
                break;
            case 'certificate':
            case 'driving_license':
                $verificationSteps['certificate'] = 20;
                break;
        }
    }
}

$verificationProgress = array_sum($verificationSteps);

// Determine verification level
if ($verificationProgress >= 80) {
    $verificationLevel = "Verified";
    $verificationBadge = "verified";
    $verificationColor = "success";
} elseif ($verificationProgress >= 40) {
    $verificationLevel = "Partially Verified";
    $verificationBadge = "partial";
    $verificationColor = "warning";
} else {
    $verificationLevel = "Not Verified";
    $verificationBadge = "unverified";
    $verificationColor = "danger";
}

// Get badge information
$badges = [];
if ($verificationProgress >= 80) $badges[] = ['name' => 'Verified Provider', 'icon' => 'fa-shield-check', 'color' => 'success'];
if ($analytics['avg_rating'] >= 4.0) $badges[] = ['name' => 'Top Rated', 'icon' => 'fa-star', 'color' => 'warning'];
if ($analytics['completed_jobs'] >= 10) $badges[] = ['name' => 'Experienced', 'icon' => 'fa-award', 'color' => 'primary'];
if (strtotime($provider['join_date']) > strtotime('-30 days')) $badges[] = ['name' => 'New Provider', 'icon' => 'fa-sparkles', 'color' => 'info'];
if ($analytics['cancelled_jobs'] < 2 && $analytics['total_jobs'] > 5) $badges[] = ['name' => 'Reliable', 'icon' => 'fa-check-circle', 'color' => 'success'];
if ($providerSettings['communication']['enable_ai_booking'] ?? 0) $badges[] = ['name' => 'AI Assistant', 'icon' => 'fa-robot', 'color' => 'info'];

// Helper function to get setting value with default
function getSetting($section, $key, $default = '') {
    global $providerSettings;
    return $providerSettings[$section][$key] ?? $default;
}

// Get working days array
$workingDays = !empty(getSetting('availability', 'working_days')) ? 
    explode(',', getSetting('availability', 'working_days')) : [1,2,3,4,5];

// Get enabled payment methods
$enabledPaymentMethods = !empty(getSetting('payment', 'payment_methods')) ? 
    explode(',', getSetting('payment', 'payment_methods')) : ['cash', 'mobile_money'];

// Initialize requirements checker
$requirements = new ProviderRequirements($db, $provider['id']);
$completion_pct = $requirements->getCompletionPercentage();
$is_profile_complete = $requirements->isComplete();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('settings.page_title', [], 'dashboard'); ?> - <?php echo getPlatformName(); ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Provider Requirements Checklist CSS -->
    <link rel="stylesheet" href="../assets/css/provider-requirements.css">
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
            --card-radius: 12px;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-hover: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Maintenance Warning */
        .maintenance-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #856404;
            border: none;
            margin-bottom: 1rem;
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
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
            color: var(--secondary);
            margin: 0;
        }
        
        /* Verification Badge */
        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }
        
        .verification-badge.partial {
            background: #fff3cd;
            color: #856404;
        }
        
        .verification-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Settings Navigation / View Tabs */
        .view-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .view-tab {
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: var(--secondary);
            font-weight: 500;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .view-tab:hover {
            color: var(--primary);
        }
        
        .view-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .view-tab i {
            font-size: 1.1rem;
        }
        
        /* Legacy settings-nav - removed (using view-tabs instead) */
        .settings-nav {
            display: none;
        }
        
        .settings-nav a {
            display: none;
        }
        
        .settings-nav a:hover {
            display: none;
        }
        
        .settings-nav a.active {
            display: none;
        }
        
        /* Settings Section */
        .settings-section {
            background: white;
            padding: 2rem;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
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
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        /* Verification Progress */
        .verification-progress {
            background: #f8f9fa;
            border-radius: var(--card-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #e9ecef;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .progress-bar {
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #0a58ca);
            border-radius: 6px;
            transition: width 0.3s ease;
        }
        
        .verification-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .verification-step {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            background: white;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .verification-step.completed {
            border-color: var(--success);
            background: #f8fff9;
            transform: translateY(-2px);
        }
        
        .verification-step i {
            font-size: 1.5rem;
        }
        
        .verification-step.completed i {
            color: var(--success);
        }
        
        /* Document Upload */
        .document-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            margin-bottom: 1rem;
            background: white;
        }
        
        .document-upload:hover {
            border-color: var(--primary);
            background: #f8fafc;
            transform: translateY(-2px);
        }
        
        .document-upload input[type="file"] {
            display: none;
        }
        
        .document-upload label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .document-upload label:hover {
            background: #0a58ca;
            transform: translateY(-2px);
        }
        
        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .category-checkbox {
            position: relative;
        }
        
        .category-checkbox input {
            position: absolute;
            opacity: 0;
        }
        
        .category-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .category-label:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .category-checkbox input:checked + .category-label {
            border-color: var(--primary);
            background: rgba(13, 110, 253, 0.05);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.1);
        }
        
        .category-label i {
            font-size: 1.5rem;
            width: 30px;
            text-align: center;
        }
        
        /* Map Container */
        .map-container {
            height: 400px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }
        
        /* Service Area Form */
        .service-area-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .service-area-form.active {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            background: #e8f3ff;
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            margin: 0;
            padding: 0;
            z-index: 2;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
            z-index: 1;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Badges Display */
        .badges-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .badge-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 2px solid transparent;
        }
        
        .badge-item.bg-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .badge-item.bg-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .badge-item.bg-primary {
            background: #cfe2ff;
            color: #084298;
            border-color: #b6d4fe;
        }
        
        .badge-item.bg-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .badge-item.bg-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        /* Analytics Cards */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .analytic-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }
        
        .analytic-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .analytic-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: var(--dark);
        }
        
        .analytic-card p {
            color: var(--secondary);
            margin: 0;
            font-weight: 500;
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .settings-nav {
                flex-direction: column;
                position: relative;
                top: 0;
            }
            
            .settings-nav a {
                min-width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .verification-steps {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
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
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-text {
            color: var(--secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        /* Submit Button */
        .btn-save {
            background: var(--success);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
        }
        
        .btn-save:hover {
            background: #157347;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25, 135, 84, 0.4);
        }
        
        /* Document List */
        .document-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .document-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }
        
        .document-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .document-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .document-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Session History */
        .session-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-device {
            font-weight: 600;
            color: var(--dark);
        }
        
        .session-ip {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .session-time {
            color: var(--secondary);
            font-size: 0.85rem;
        }
        
        .current-session {
            background: #f8fff9;
            border-left: 4px solid var(--success);
        }
        
        /* Review Item */
        .review-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .review-client {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .client-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .rating-stars {
            color: #ffc107;
        }
        
        /* Days Selector */
        .days-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .day-checkbox {
            position: relative;
        }
        
        .day-checkbox input {
            display: none;
        }
        
        .day-checkbox label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
        }
        
        .day-checkbox input:checked + label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Payment Method Card */
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s;
        }
        
        .payment-method-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .payment-method-card.default {
            border-color: var(--success);
            background: #f8fff9;
        }
        
        /* Wallet Balance */
        .wallet-balance {
            background: linear-gradient(135deg, var(--primary), #0a58ca);
            color: white;
            border-radius: var(--card-radius);
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .wallet-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        /* Setting Card */
        .setting-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .setting-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .setting-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .setting-title {
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }
        
        .setting-description {
            color: var(--secondary);
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            margin: 0;
        }
        
        /* Responsive Tables */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            color: var(--dark);
        }
        
        .table td {
            vertical-align: middle;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Maintenance Warning -->
        <?php if (isset($maintenance_warning)): ?>
            <div class="alert maintenance-warning">
                <i class="fas fa-tools me-2"></i>
                <strong>Maintenance Mode Active</strong>
                <p class="mb-0 mt-2">The platform is currently under maintenance. Some features may be limited.</p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> <?php echo __('settings.header_title', [], 'dashboard'); ?></h1>
            <p><?php echo __('settings.header_subtitle', [], 'dashboard'); ?></p>
            
            <div class="d-flex align-items-center gap-3 mt-3">
                <span class="verification-badge <?php echo $verificationBadge; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo $verificationLevel; ?> (<?php echo $verificationProgress; ?>%)
                </span>
                
                <?php if (!empty($badges)): ?>
                    <div class="badges-grid">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-item bg-<?php echo $badge['color']; ?>">
                                <i class="fas <?php echo $badge['icon']; ?>"></i>
                                <?php echo $badge['name']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($warning): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $warning; ?>
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

        <!-- Settings Navigation -->
        <div class="view-tabs">
            <a href="?section=identity" class="view-tab <?php echo $settings_section === 'identity' ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i> <?php echo __('settings.tab_identity', [], 'dashboard'); ?>
            </a>
            <a href="?section=visibility" class="view-tab <?php echo $settings_section === 'visibility' ? 'active' : ''; ?>">
                <i class="fas fa-eye"></i> <?php echo __('settings.tab_visibility', [], 'dashboard'); ?>
            </a>
            <a href="?section=pricing" class="view-tab <?php echo $settings_section === 'pricing' ? 'active' : ''; ?>">
                <i class="fas fa-tag"></i> <?php echo __('settings.tab_pricing', [], 'dashboard'); ?>
            </a>
            <a href="?section=availability" class="view-tab <?php echo $settings_section === 'availability' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> <?php echo __('settings.tab_availability', [], 'dashboard'); ?>
            </a>
            <a href="?section=location" class="view-tab <?php echo $settings_section === 'location' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i> <?php echo __('settings.tab_location', [], 'dashboard'); ?>
            </a>
            <a href="?section=ai" class="view-tab <?php echo $settings_section === 'ai' ? 'active' : ''; ?>">
                <i class="fas fa-robot"></i> <?php echo __('settings.tab_ai', [], 'dashboard'); ?>
            </a>
            <a href="?section=payment" class="view-tab <?php echo $settings_section === 'payment' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> <?php echo __('settings.tab_payment', [], 'dashboard'); ?>
            </a>
            <a href="?section=communication" class="view-tab <?php echo $settings_section === 'communication' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i> <?php echo __('settings.tab_communication', [], 'dashboard'); ?>
            </a>
            <a href="?section=language" class="view-tab <?php echo $settings_section === 'language' ? 'active' : ''; ?>">
                <i class="fas fa-language"></i> <?php echo __('settings.tab_language', [], 'dashboard'); ?>
            </a>
            <a href="?section=notifications" class="view-tab <?php echo $settings_section === 'notifications' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <?php echo __('settings.tab_notifications', [], 'dashboard'); ?>
            </a>
            <a href="?section=reviews" class="view-tab <?php echo $settings_section === 'reviews' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> <?php echo __('settings.tab_reviews', [], 'dashboard'); ?>
            </a>
            <a href="?section=security" class="view-tab <?php echo $settings_section === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i> <?php echo __('settings.tab_security', [], 'dashboard'); ?>
            </a>
            <a href="?section=analytics" class="view-tab <?php echo $settings_section === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <?php echo __('settings.tab_analytics', [], 'dashboard'); ?>
            </a>
            <a href="?section=account" class="view-tab <?php echo $settings_section === 'account' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> <?php echo __('settings.tab_account', [], 'dashboard'); ?>
            </a>
            <a href="?section=requirements" class="view-tab <?php echo $settings_section === 'requirements' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i> <?php echo __('settings.tab_requirements', [], 'dashboard'); ?>
            </a>
        </div>

        <!-- 🔐 Identity & Verification Section -->
        <?php if ($settings_section === 'identity'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-id-card"></i> 1. Identity & Verification (Trust Foundation)</h2>
            
            <!-- Verification Progress -->
            <div class="verification-progress">
                <div class="progress-info">
                    <div>
                        <h3 class="mb-1">Verification Progress</h3>
                        <p class="text-muted mb-0">Complete verification to earn trust badges and increase credibility</p>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0"><?php echo $verificationProgress; ?>%</h2>
                        <small class="text-muted">Complete</small>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $verificationProgress; ?>%"></div>
                </div>
                
                <div class="verification-steps">
                    <div class="verification-step <?php echo $verificationSteps['email_verified'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['email_verified'] ? 'fa-check-circle' : 'fa-envelope'; ?>"></i>
                        <div>
                            <h6 class="mb-0">Email Verified</h6>
                            <small class="text-muted">Verify your email address</small>
                        </div>
                    </div>
                    
                    <div class="verification-step <?php echo $verificationSteps['phone_verified'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['phone_verified'] ? 'fa-check-circle' : 'fa-phone'; ?>"></i>
                        <div>
                            <h6 class="mb-0">Phone Verified</h6>
                            <small class="text-muted">Verify your phone number via OTP</small>
                        </div>
                    </div>
                    
                    <div class="verification-step <?php echo $verificationSteps['national_id'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['national_id'] ? 'fa-check-circle' : 'fa-id-card'; ?>"></i>
                        <div>
                            <h6 class="mb-0">ID Verification</h6>
                            <small class="text-muted">Upload National ID/Passport</small>
                        </div>
                    </div>
                    
                    <div class="verification-step <?php echo $verificationSteps['selfie'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['selfie'] ? 'fa-check-circle' : 'fa-camera'; ?>"></i>
                        <div>
                            <h6 class="mb-0">Selfie Verification</h6>
                            <small class="text-muted">Upload ID + face match photo</small>
                        </div>
                    </div>
                    
                    <div class="verification-step <?php echo $verificationSteps['business_reg'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['business_reg'] ? 'fa-check-circle' : 'fa-file-contract'; ?>"></i>
                        <div>
                            <h6 class="mb-0">Business Registration</h6>
                            <small class="text-muted">For registered businesses (optional)</small>
                        </div>
                    </div>
                    
                    <div class="verification-step <?php echo $verificationSteps['certificate'] ? 'completed' : ''; ?>">
                        <i class="fas <?php echo $verificationSteps['certificate'] ? 'fa-check-circle' : 'fa-certificate'; ?>"></i>
                        <div>
                            <h6 class="mb-0">Certificates/Licenses</h6>
                            <small class="text-muted">Professional certifications</small>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p class="text-muted mb-2"><i class="fas fa-info-circle me-2"></i> <strong>Benefits of Verification:</strong></p>
                    <ul class="text-muted">
                        <li>Earn "Verified Provider" badge</li>
                        <li>Higher trust from clients</li>
                        <li>Priority in search results</li>
                        <li>Access to premium features</li>
                        <li>Higher booking conversion rates</li>
                    </ul>
                </div>
            </div>
            
            <!-- Document Uploads -->
            <form method="POST" enctype="multipart/form-data" id="identityForm">
                <input type="hidden" name="section" value="identity">
                
                <h4 class="mb-3">Upload Verification Documents</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="document-upload">
                            <i class="fas fa-id-card fa-3x mb-3 text-primary"></i>
                            <h5>National ID / Passport</h5>
                            <p class="text-muted">Upload front and back of your ID (Max: 5MB, JPG/PNG/PDF)</p>
                            <input type="file" name="national_id" id="national_id" accept="image/*,.pdf" onchange="previewFile(this, 'national_id')">
                            <label for="national_id">
                                <i class="fas fa-upload me-2"></i> Choose File
                            </label>
                            <div id="national_id_preview" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="document-upload">
                            <i class="fas fa-camera fa-3x mb-3 text-primary"></i>
                            <h5>Selfie with ID</h5>
                            <p class="text-muted">Take a selfie holding your ID (Max: 2MB, JPG/PNG)</p>
                            <input type="file" name="selfie_verification" id="selfie_verification" accept="image/*" onchange="previewFile(this, 'selfie')">
                            <label for="selfie_verification">
                                <i class="fas fa-upload me-2"></i> Choose File
                            </label>
                            <div id="selfie_preview" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="document-upload">
                            <i class="fas fa-file-contract fa-3x mb-3 text-primary"></i>
                            <h5>Business Registration</h5>
                            <p class="text-muted">Optional for registered businesses</p>
                            <input type="file" name="business_registration" id="business_registration" accept="image/*,.pdf,.doc,.docx">
                            <label for="business_registration">
                                <i class="fas fa-upload me-2"></i> Choose File
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="document-upload">
                            <i class="fas fa-certificate fa-3x mb-3 text-primary"></i>
                            <h5>Certificates/Licenses</h5>
                            <p class="text-muted">Professional certifications (electricians, plumbers, etc.)</p>
                            <input type="file" name="certificate" id="certificate" accept="image/*,.pdf">
                            <label for="certificate">
                                <i class="fas fa-upload me-2"></i> Choose File
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="document-upload">
                            <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                            <h5>Other Documents</h5>
                            <p class="text-muted">Any additional verification documents</p>
                            <input type="file" name="other" id="other" accept="image/*,.pdf,.doc,.docx">
                            <label for="other">
                                <i class="fas fa-upload me-2"></i> Choose File
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Uploaded Documents -->
                <?php if (!empty($verificationDocs)): ?>
                    <h4 class="mt-4 mb-3">Uploaded Documents</h4>
                    <div class="document-list">
                        <?php foreach ($verificationDocs as $doc): ?>
                            <div class="document-item">
                                <div class="document-info">
                                    <i class="fas fa-file fa-2x text-primary"></i>
                                    <div>
                                        <h6 class="mb-0"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></h6>
                                        <small class="text-muted">Uploaded: <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="document-status status-<?php echo $doc['status']; ?>">
                                        <?php echo ucfirst($doc['status']); ?>
                                    </span>
                                    <?php if ($doc['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Under Review</span>
                                    <?php elseif ($doc['status'] === 'approved'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Important:</strong> All documents are securely stored and only used for verification purposes. 
                    Verification usually takes 1-3 business days. You'll be notified once your documents are reviewed.
                </div>
                
                <button type="submit" class="btn-save" id="submitIdentity">
                    <i class="fas fa-save me-2"></i> Save & Submit for Review
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 👀 Profile Visibility & Public Appearance -->
        <?php if ($settings_section === 'visibility'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-eye"></i> 2. Profile Visibility & Public Appearance</h2>
            
            <form method="POST" id="visibilityForm">
                <input type="hidden" name="section" value="visibility">
                
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="mb-3">Contact Information</h4>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Show Phone Number</h6>
                                    <p class="setting-description">Clients can see your phone number</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="show_phone" id="show_phone" 
                                           <?php echo getSetting('visibility', 'show_phone', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Show WhatsApp</h6>
                                    <p class="setting-description">Clients can contact you via WhatsApp</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="show_whatsapp" id="show_whatsapp" 
                                           <?php echo getSetting('visibility', 'show_whatsapp', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Show Exact Location</h6>
                                    <p class="setting-description">Show exact location pin vs general area</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="show_exact_location" id="show_exact_location" 
                                           <?php echo getSetting('visibility', 'show_exact_location', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="mb-3">Profile Visibility</h4>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Public Profile</h6>
                                    <p class="setting-description">Your profile is visible to public</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="profile_public" id="profile_public" 
                                           <?php echo getSetting('visibility', 'profile_public', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Appear in Search Results</h6>
                                    <p class="setting-description">Show your profile in search results</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="appear_in_search" id="appear_in_search" 
                                           <?php echo getSetting('visibility', 'appear_in_search', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Appear as "Available Now"</h6>
                                    <p class="setting-description">Show in "Available Now" listings</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="appear_available" id="appear_available" 
                                           <?php echo getSetting('visibility', 'appear_available', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h4 class="mb-3">Service Options</h4>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Emergency Service</h6>
                                    <p class="setting-description">Offer emergency services (24/7)</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="emergency_service" id="emergency_service" 
                                           <?php echo getSetting('visibility', 'emergency_service', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Night Service</h6>
                                    <p class="setting-description">Offer services during night hours</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="night_service" id="night_service" 
                                           <?php echo getSetting('visibility', 'night_service', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Weekend Service</h6>
                                    <p class="setting-description">Offer services on weekends</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="weekend_service" id="weekend_service" 
                                           <?php echo getSetting('visibility', 'weekend_service', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h4 class="mb-3">Profile Badges</h4>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Verified Provider Badge</h6>
                                    <p class="setting-description">Show verified badge on profile</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="badge_verified" id="badge_verified" 
                                           <?php echo getSetting('visibility', 'badge_verified', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Top Rated Badge</h6>
                                    <p class="setting-description">Show top rated badge (if eligible)</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="badge_top_rated" id="badge_top_rated" 
                                           <?php echo getSetting('visibility', 'badge_top_rated', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Fast Responder Badge</h6>
                                    <p class="setting-description">Show fast responder badge</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="badge_fast_responder" id="badge_fast_responder" 
                                           <?php echo getSetting('visibility', 'badge_fast_responder', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Tip:</strong> More visibility settings increase your chances of being found by clients. 
                    However, consider your privacy preferences when sharing contact information.
                </div>
                
                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i> Save Visibility Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 🛠️ Service & Pricing Control -->
        <?php if ($settings_section === 'pricing'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-tag"></i> 3. Service & Pricing Control</h2>
            
            <form method="POST" id="pricingForm">
                <input type="hidden" name="section" value="pricing">
                
                <h4 class="mb-3">Service Categories</h4>
                <p class="text-muted mb-3">Select the categories of services you offer. Clients will find you based on these categories.</p>
                
                <div class="categories-grid">
                    <?php foreach ($allCategories as $category): ?>
                        <div class="category-checkbox">
                            <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                   id="cat_<?php echo $category['id']; ?>"
                                   <?php echo in_array($category['id'], $selectedCategories) ? 'checked' : ''; ?>>
                            <label for="cat_<?php echo $category['id']; ?>" class="category-label">
                                <i class="fas <?php echo $category['icon']; ?>"></i>
                                <div><?php echo htmlspecialchars($category['name']); ?></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($allCategories)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No service categories are currently available. Please contact support.
                    </div>
                <?php endif; ?>
                
                <h4 class="mt-4 mb-3">Pricing Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Pricing Model</label>
                                <select name="pricing_model" class="form-select">
                                    <option value="fixed" <?php echo getSetting('pricing', 'pricing_model') === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                                    <option value="per_hour" <?php echo getSetting('pricing', 'pricing_model') === 'per_hour' ? 'selected' : ''; ?>>Per Hour</option>
                                    <option value="per_day" <?php echo getSetting('pricing', 'pricing_model') === 'per_day' ? 'selected' : ''; ?>>Per Day</option>
                                    <option value="negotiable" <?php echo getSetting('pricing', 'pricing_model') === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                                </select>
                                <p class="form-text">How you charge for your services</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Fixed Price (RWF)</label>
                                <input type="number" name="fixed_price" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'fixed_price', 0); ?>" min="0" step="100">
                                <p class="form-text">Standard fixed price for services</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Per Hour Rate (RWF)</label>
                                <input type="number" name="per_hour_rate" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'per_hour_rate', 0); ?>" min="0" step="500">
                                <p class="form-text">Hourly rate for services</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Per Day Rate (RWF)</label>
                                <input type="number" name="per_day_rate" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'per_day_rate', 0); ?>" min="0" step="1000">
                                <p class="form-text">Daily rate for services</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Minimum Booking Price (RWF)</label>
                                <input type="number" name="minimum_price" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'minimum_price', 0); ?>" min="0" step="100">
                                <p class="form-text">Minimum price for any service booking</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Call-out Fee (RWF)</label>
                                <input type="number" name="callout_fee" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'callout_fee', 0); ?>" min="0" step="500">
                                <p class="form-text">Fee for traveling to client location</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Emergency Surcharge (%)</label>
                                <input type="number" name="emergency_surcharge" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'emergency_surcharge', 0); ?>" min="0" max="100" step="5">
                                <p class="form-text">Additional charge for emergency services</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Repeat Client Discount (%)</label>
                                <input type="number" name="repeat_client_discount" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'repeat_client_discount', 0); ?>" min="0" max="50" step="5">
                                <p class="form-text">Discount for returning clients</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Price Negotiable</h6>
                                    <p class="setting-description">Allow clients to negotiate prices</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="price_negotiable" id="price_negotiable" 
                                           <?php echo getSetting('pricing', 'price_negotiable', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Accept Partial Payment</h6>
                                    <p class="setting-description">Allow clients to pay in installments</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="accept_partial_payment" id="accept_partial_payment" 
                                           <?php echo getSetting('pricing', 'accept_partial_payment', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Deposit Percentage (%)</label>
                                <input type="number" name="deposit_percentage" class="form-control" 
                                       value="<?php echo getSetting('pricing', 'deposit_percentage', 0); ?>" min="0" max="100" step="5">
                                <p class="form-text">Required deposit for bookings</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Custom Service Notes</label>
                                <textarea class="form-control" rows="3" placeholder="Add custom notes for all services"></textarea>
                                <p class="form-text">These notes will appear on all your services</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Pricing Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Research competitor prices in your area</li>
                        <li>Consider your experience and expertise level</li>
                        <li>Factor in travel costs and material expenses</li>
                        <li>Offer clear pricing to avoid misunderstandings</li>
                        <li>Consider offering package deals for multiple services</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Pricing Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 📅 Availability & Scheduling -->
        <?php if ($settings_section === 'availability'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-calendar-alt"></i> 4. Availability & Scheduling</h2>
            
            <form method="POST" id="availabilityForm">
                <input type="hidden" name="section" value="availability">
                
                <h4 class="mb-3">Working Hours & Days</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <label class="form-label mb-3">Working Days</label>
                            <div class="days-selector">
                                <?php 
                                $days = [
                                    1 => ['label' => 'Mon', 'full' => 'Monday'],
                                    2 => ['label' => 'Tue', 'full' => 'Tuesday'],
                                    3 => ['label' => 'Wed', 'full' => 'Wednesday'],
                                    4 => ['label' => 'Thu', 'full' => 'Thursday'],
                                    5 => ['label' => 'Fri', 'full' => 'Friday'],
                                    6 => ['label' => 'Sat', 'full' => 'Saturday'],
                                    7 => ['label' => 'Sun', 'full' => 'Sunday']
                                ];
                                foreach ($days as $value => $day): ?>
                                    <div class="day-checkbox">
                                        <input type="checkbox" name="working_days[]" value="<?php echo $value; ?>" 
                                               id="work_day<?php echo $value; ?>" 
                                               <?php echo in_array($value, $workingDays) ? 'checked' : ''; ?>>
                                        <label for="work_day<?php echo $value; ?>"><?php echo $day['label']; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-text mt-2">Select your regular working days</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" class="form-control" name="working_hours_start" 
                                               value="<?php echo getSetting('availability', 'working_hours_start', '08:00'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">End Time</label>
                                        <input type="time" class="form-control" name="working_hours_end" 
                                               value="<?php echo getSetting('availability', 'working_hours_end', '17:00'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <p class="form-text">Your regular working hours</p>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Break Start (Optional)</label>
                                        <input type="time" class="form-control" name="break_start" 
                                               value="<?php echo getSetting('availability', 'break_start', ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Break End (Optional)</label>
                                        <input type="time" class="form-control" name="break_end" 
                                               value="<?php echo getSetting('availability', 'break_end', ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <p class="form-text">Set your break/lunch time</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Maximum Jobs Per Day</label>
                                <input type="number" class="form-control" name="max_jobs_per_day" 
                                       value="<?php echo getSetting('availability', 'max_jobs_per_day', 5); ?>" min="1" max="20" step="1">
                                <p class="form-text">Maximum number of jobs you can accept per day</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">Booking Preferences</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Auto-Accept Bookings</h6>
                                    <p class="setting-description">Automatically accept new bookings</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="auto_accept_bookings" id="auto_accept_bookings" 
                                           <?php echo getSetting('availability', 'auto_accept_bookings', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Buffer Time Between Jobs (minutes)</label>
                                <input type="number" class="form-control" name="buffer_time" 
                                       value="<?php echo getSetting('availability', 'buffer_time', 15); ?>" min="0" max="120" step="5">
                                <p class="form-text">Time between appointments for travel/preparation</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Google Calendar Sync</h6>
                                    <p class="setting-description">Sync with Google Calendar</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="google_calendar_sync" id="google_calendar_sync" 
                                           <?php echo getSetting('availability', 'google_calendar_sync', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                            <?php if (getSetting('availability', 'google_calendar_sync', 0)): ?>
                                <div class="mt-2">
                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                        <i class="fab fa-google me-1"></i> Connect Google Calendar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Booking Conflict Prevention</h6>
                                    <p class="setting-description">Prevent overlapping bookings</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="calendar_conflict_prevention" id="calendar_conflict_prevention" 
                                           <?php echo getSetting('availability', 'calendar_conflict_prevention', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vacation/Unavailable Dates -->
                <h4 class="mt-4 mb-3">Vacation / Unavailable Dates</h4>
                
                <div class="setting-card">
                    <div class="form-group">
                        <label class="form-label">Add Unavailable Dates</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="unavailable_date" placeholder="Select date">
                            <button type="button" class="btn btn-outline-primary" onclick="addUnavailableDate()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <p class="form-text">Add dates when you're not available</p>
                        
                        <div id="unavailable_dates_list" class="mt-3">
                            <!-- Unavailable dates will appear here -->
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Availability Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Set realistic working hours to avoid burnout</li>
                        <li>Use buffer time for travel between jobs</li>
                        <li>Keep your calendar updated to avoid double bookings</li>
                        <li>Plan vacations in advance and mark them as unavailable</li>
                        <li>Consider peak hours when setting maximum daily jobs</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Availability Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 📍 Location & Service Area -->
        <?php if ($settings_section === 'location'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-map-marker-alt"></i> 5. Location & Service Area</h2>
            
            <form method="POST" id="locationForm">
                <input type="hidden" name="section" value="location">
                
                <h4 class="mb-3">Service Areas</h4>
                <p class="text-muted mb-3">Define the areas where you provide services. You can add multiple service areas.</p>
                
                <div id="serviceAreasContainer">
                    <?php if (!empty($serviceAreas)): ?>
                        <?php foreach ($serviceAreas as $index => $area): ?>
                            <div class="service-area-form" data-index="<?php echo $index; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Area Name</label>
                                            <input type="text" class="form-control" name="service_areas[<?php echo $index; ?>][name]" 
                                                   value="<?php echo htmlspecialchars($area['area_name']); ?>" 
                                                   placeholder="e.g., Kigali City Center" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Latitude</label>
                                            <input type="text" class="form-control" name="service_areas[<?php echo $index; ?>][lat]" 
                                                   value="<?php echo $area['latitude']; ?>" placeholder="e.g., -1.9441" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Longitude</label>
                                            <input type="text" class="form-control" name="service_areas[<?php echo $index; ?>][lng]" 
                                                   value="<?php echo $area['longitude']; ?>" placeholder="e.g., 30.0619" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Service Radius (km)</label>
                                            <input type="number" class="form-control" name="service_areas[<?php echo $index; ?>][radius]" 
                                                   value="<?php echo $area['radius_km']; ?>" min="1" max="100" step="1" required>
                                            <p class="form-text">Distance you're willing to travel from this point</p>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-info me-2" onclick="setSelectedServiceArea(<?php echo $index; ?>);">
                                            <i class="fas fa-map-pin"></i> Pick on Map
                                        </button>

                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="radio" name="primary_area" 
                                                   value="<?php echo $index; ?>" <?php echo $area['is_primary'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Primary Service Area</label>
                                        </div>

                                        <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removeServiceArea(this)">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default service area form -->
                        <div class="service-area-form" data-index="0">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Area Name</label>
                                        <input type="text" class="form-control" name="service_areas[0][name]" 
                                               placeholder="e.g., Kigali City Center" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Latitude</label>
                                        <input type="text" class="form-control" name="service_areas[0][lat]" 
                                               placeholder="e.g., -1.9441" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Longitude</label>
                                        <input type="text" class="form-control" name="service_areas[0][lng]" 
                                               placeholder="e.g., 30.0619" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Service Radius (km)</label>
                                        <input type="number" class="form-control" name="service_areas[0][radius]" 
                                               value="10" min="1" max="100" step="1" required>
                                        <p class="form-text">Distance you're willing to travel from this point</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-info me-2" onclick="setSelectedServiceArea(0);">
                                        <i class="fas fa-map-pin"></i> Pick on Map
                                    </button>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="primary_area" value="0" checked>
                                        <label class="form-check-label">Primary Service Area</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn btn-outline-primary mb-3" onclick="addServiceArea()">
                    <i class="fas fa-plus me-2"></i> Add Another Service Area
                </button>

                <div class="alert alert-secondary">
                    <i class="fas fa-info-circle me-2"></i>
                    Click "Pick on Map" for one service area, then click the map to set its latitude and longitude automatically.
                    The selected service area is shown as a blue circle (primary) or green circle (secondary).
                </div>
                
                <!-- Map Preview -->
                <h4 class="mt-4 mb-3">Map Preview</h4>
                <div class="map-container" id="serviceMap">
                    <!-- Map will be loaded here -->
                </div>
                <p class="text-muted"><i class="fas fa-info-circle me-2"></i> Your service areas will be shown to clients on the map</p>
                
                <h4 class="mt-4 mb-3">Location Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Travel Fee Per Kilometer (RWF)</label>
                                <input type="number" name="travel_fee_per_km" class="form-control" 
                                       value="<?php echo getSetting('location', 'travel_fee_per_km', 0); ?>" min="0" step="50">
                                <p class="form-text">Additional charge per km beyond service area</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Maximum Travel Distance (km)</label>
                                <input type="number" name="max_travel_distance" class="form-control" 
                                       value="<?php echo getSetting('location', 'max_travel_distance', 20); ?>" min="1" max="200" step="5">
                                <p class="form-text">Maximum distance you're willing to travel</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Map Accuracy</label>
                                <select name="map_accuracy" class="form-select">
                                    <option value="approximate" <?php echo getSetting('location', 'map_accuracy') === 'approximate' ? 'selected' : ''; ?>>Approximate Location</option>
                                    <option value="exact" <?php echo getSetting('location', 'map_accuracy') === 'exact' ? 'selected' : ''; ?>>Exact Location</option>
                                    <option value="hidden" <?php echo getSetting('location', 'map_accuracy') === 'hidden' ? 'selected' : ''; ?>>Hidden Location</option>
                                </select>
                                <p class="form-text">How accurate your location appears to clients</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Multiple Service Areas</h6>
                                    <p class="setting-description">Enable multiple service areas</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="multiple_areas" id="multiple_areas" 
                                           <?php echo getSetting('location', 'multiple_areas', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Location Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Set realistic service radii based on your transportation</li>
                        <li>Consider traffic patterns when setting travel fees</li>
                        <li>Multiple service areas can increase your client base</li>
                        <li>Update your location if you move to a new area</li>
                        <li>Be clear about travel fees to avoid client disputes</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Location Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 🤖 AI & Smart Features -->
        <?php if ($settings_section === 'ai'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-robot"></i> 6. AI & Smart Features (Advanced)</h2>
            
            <form method="POST" id="aiForm">
                <input type="hidden" name="section" value="ai_features">
                
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>AI Assistant:</strong> Enable AI features to automate tasks, improve responses, and optimize your business. 
                    These features use artificial intelligence to help you work smarter, not harder.
                </div>
                
                <h4 class="mb-3">🤖 AI Assistance Controls</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable AI Assistant</h6>
                                    <p class="setting-description">Turn on AI features for your account</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_ai_assistant" id="enable_ai_assistant" 
                                           <?php echo getSetting('ai_features', 'enable_ai_assistant', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Auto-Reply Suggestions</h6>
                                    <p class="setting-description">AI suggests responses to client messages</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="ai_auto_reply" id="ai_auto_reply" 
                                           <?php echo getSetting('ai_features', 'ai_auto_reply', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Service Description Improvement</h6>
                                    <p class="setting-description">AI improves your service descriptions</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="ai_description_improvement" id="ai_description_improvement" 
                                           <?php echo getSetting('ai_features', 'ai_description_improvement', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">AI Pricing Suggestions</h6>
                                    <p class="setting-description">Get AI-powered pricing recommendations</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="ai_pricing_suggestions" id="ai_pricing_suggestions" 
                                           <?php echo getSetting('ai_features', 'ai_pricing_suggestions', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Availability Optimization</h6>
                                    <p class="setting-description">AI optimizes your working hours</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="ai_availability_optimization" id="ai_availability_optimization" 
                                           <?php echo getSetting('ai_features', 'ai_availability_optimization', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Fraud/Scam Protection</h6>
                                    <p class="setting-description">AI detects potential fraud attempts</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="ai_fraud_protection" id="ai_fraud_protection" 
                                           <?php echo getSetting('ai_features', 'ai_fraud_protection', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">🧠 Smart Booking by Prompt</h4>
                <p class="text-muted mb-3">Allow AI to handle booking requests automatically based on client prompts.</p>
                
                <div class="setting-card">
                    <div class="alert alert-secondary">
                        <i class="fas fa-comment-dots me-2"></i>
                        <strong>Example Prompt:</strong> "I need a plumber tomorrow morning near Gisenyi for a leaky pipe"
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="setting-card">
                                <div class="setting-header">
                                    <div>
                                        <h6 class="setting-title">Auto-Select Service</h6>
                                        <p class="setting-description">AI selects appropriate service</p>
                                    </div>
                                    <div class="toggle-switch">
                                        <input type="checkbox" name="ai_auto_select_service" id="ai_auto_select_service" 
                                               <?php echo getSetting('ai_features', 'ai_auto_select_service', 0) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="setting-card">
                                <div class="setting-header">
                                    <div>
                                        <h6 class="setting-title">Auto-Schedule</h6>
                                        <p class="setting-description">AI schedules appointments automatically</p>
                                    </div>
                                    <div class="toggle-switch">
                                        <input type="checkbox" name="ai_auto_schedule" id="ai_auto_schedule" 
                                               <?php echo getSetting('ai_features', 'ai_auto_schedule', 0) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="setting-card">
                                <div class="setting-header">
                                    <div>
                                        <h6 class="setting-title">Auto-Quote Price</h6>
                                        <p class="setting-description">AI provides price estimates</p>
                                    </div>
                                    <div class="toggle-switch">
                                        <input type="checkbox" name="ai_auto_quote" id="ai_auto_quote" 
                                               <?php echo getSetting('ai_features', 'ai_auto_quote', 0) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="setting-card mt-3">
                        <div class="setting-header">
                            <div>
                                <h6 class="setting-title">Smart Booking by Prompt</h6>
                                <p class="setting-description">Enable AI to handle natural language booking requests</p>
                            </div>
                            <div class="toggle-switch">
                                <input type="checkbox" name="smart_booking_by_prompt" id="smart_booking_by_prompt" 
                                       <?php echo getSetting('ai_features', 'smart_booking_by_prompt', 0) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> AI features are in beta. While they can save time, always review AI suggestions before accepting.
                    You remain in control of all final decisions.
                </div>
                
                <div class="mt-4">
                    <h5>AI Usage Statistics</h5>
                    <div class="stats-overview">
                        <div class="stat-item">
                            <div class="stat-value">0</div>
                            <div class="stat-label">AI Suggestions Used</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">0</div>
                            <div class="stat-label">Time Saved</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">100%</div>
                            <div class="stat-label">Accuracy Rate</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">0</div>
                            <div class="stat-label">Bookings via AI</div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save AI Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 💳 Payment & Financial Settings -->
        <?php if ($settings_section === 'payment'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-credit-card"></i> 7. Payment & Financial Settings</h2>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="section" value="payment">
                
                <!-- Earnings Overview (withdrawal disabled) -->
                <div class="wallet-balance">
                    <i class="fas fa-wallet fa-3x mb-3"></i>
                    <h4>Total Earnings</h4>
                    <div class="wallet-amount">RWF <?php echo number_format($walletBalance, 2); ?></div>
                    <p class="mb-0 text-muted">Recorded earnings for your account (withdrawal not available on this platform).</p>
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <a href="transactions.php" class="btn btn-outline-light">
                            <i class="fas fa-history me-2"></i> Transaction History
                        </a>
                    </div>
                </div>
                
                <h4 class="mb-3">Payment Preferences</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <label class="form-label mb-3">Accepted Payment Methods</label>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" 
                                       value="cash" id="cash_payment" <?php echo in_array('cash', $enabledPaymentMethods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cash_payment">
                                    <i class="fas fa-money-bill-wave me-2 text-success"></i> Cash
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" 
                                       value="mobile_money" id="mobile_money" <?php echo in_array('mobile_money', $enabledPaymentMethods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mobile_money">
                                    <i class="fas fa-mobile-alt me-2 text-primary"></i> Mobile Money (MTN/Airtel)
                                </label>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" 
                                       value="wallet" id="wallet" <?php echo in_array('wallet', $enabledPaymentMethods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="wallet">
                                    <i class="fas fa-wallet me-2 text-info"></i> Platform Wallet
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enabled_payment_methods[]" 
                                       value="bank_transfer" id="bank_transfer" <?php echo in_array('bank_transfer', $enabledPaymentMethods) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bank_transfer">
                                    <i class="fas fa-university me-2 text-warning"></i> Bank Transfer
                                </label>
                            </div>
                            
                            <p class="form-text mt-2">Select payment methods you accept from clients</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Accept Partial Payment</h6>
                                    <p class="setting-description">Allow clients to pay deposits</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="accept_partial_payment" id="accept_partial_payment_payment" 
                                           <?php echo getSetting('payment', 'accept_partial_payment', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Pay After Service</h6>
                                    <p class="setting-description">Allow payment after service completion</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="pay_after_service" id="pay_after_service" 
                                           <?php echo getSetting('payment', 'pay_after_service', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods Setup -->
                <h4 class="mt-4 mb-3">Payment Method Details</h4>
                
                <div id="paymentMethodsContainer">
                    <?php if (!empty($paymentMethods)): ?>
                        <?php foreach ($paymentMethods as $index => $method): ?>
                            <div class="payment-method-card <?php echo $method['is_default'] ? 'default' : ''; ?>" data-index="<?php echo $index; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Method Type</label>
                                            <select name="payment_methods[<?php echo $index; ?>][type]" class="form-select">
                                                <option value="mobile_money" <?php echo $method['method_type'] === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                                <option value="bank_account" <?php echo $method['method_type'] === 'bank_account' ? 'selected' : ''; ?>>Bank Account</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" name="payment_methods[<?php echo $index; ?>][account_name]" 
                                                   value="<?php echo htmlspecialchars($method['account_name']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" name="payment_methods[<?php echo $index; ?>][account_number]" 
                                                   value="<?php echo htmlspecialchars($method['account_number']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Bank Name (if applicable)</label>
                                            <input type="text" class="form-control" name="payment_methods[<?php echo $index; ?>][bank_name]" 
                                                   value="<?php echo htmlspecialchars($method['bank_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="default_payment_method" 
                                                   value="<?php echo $index; ?>" <?php echo $method['is_default'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Set as Default</label>
                                        </div>
                                        
                                        <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removePaymentMethod(this)">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Default payment method form -->
                        <div class="payment-method-card default" data-index="0">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Method Type</label>
                                        <select name="payment_methods[0][type]" class="form-select">
                                            <option value="mobile_money">Mobile Money</option>
                                            <option value="bank_account">Bank Account</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Account Name</label>
                                        <input type="text" class="form-control" name="payment_methods[0][account_name]" 
                                               value="<?php echo htmlspecialchars($provider['full_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="payment_methods[0][account_number]" 
                                               placeholder="07XXXXXXXX or Account Number" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Bank Name (if applicable)</label>
                                        <input type="text" class="form-control" name="payment_methods[0][bank_name]" 
                                               placeholder="Bank of Kigali, Equity Bank, etc.">
                                    </div>
                                </div>
                                
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="default_payment_method" value="0" checked>
                                        <label class="form-check-label">Set as Default</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn btn-outline-primary mb-3" onclick="addPaymentMethod()">
                    <i class="fas fa-plus me-2"></i> Add Another Payment Method
                </button>
                
                <!-- Earnings Overview (withdrawal disabled) -->
                <h4 class="mt-4 mb-3">Earnings Overview</h4>

                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Total Earnings (RWF)</label>
                                <div class="wallet-amount">RWF <?php echo number_format($walletBalance, 2); ?></div>
                                <p class="form-text">Recorded earnings for your account (withdrawal not available).</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Commission Transparency</h6>
                                    <p class="setting-description">Show platform commission details</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="commission_transparency" id="commission_transparency" 
                                           <?php echo getSetting('payment', 'commission_transparency', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction History Preview -->
                <div class="mt-4">
                    <h5>Recent Transactions</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-history fa-2x mb-3"></i>
                                        <p>No recent transactions</p>
                                    </td>
                                </tr>
                                <!-- Transaction data would be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Payment Information:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Withdrawals typically process within 1-3 business days</li>
                        <li>Platform commission is <?php echo $platformSettings['commission_rate'] ?? '10'; ?>% per transaction</li>
                        <li>Keep your payment methods updated to avoid delays</li>
                        <li>Contact support for any payment-related issues</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Payment Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 💬 Communication & Contact Settings -->
        <?php if ($settings_section === 'communication'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-comments"></i> 8. Communication & Contact Settings</h2>
            
            <form method="POST" id="communicationForm">
                <input type="hidden" name="section" value="communication">
                
                <h4 class="mb-3">Communication Channels</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable In-App Chat</h6>
                                    <p class="setting-description">Clients can message you via app</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_chat" id="enable_chat" 
                                           <?php echo getSetting('communication', 'enable_chat', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable WhatsApp</h6>
                                    <p class="setting-description">Clients can contact via WhatsApp</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_whatsapp" id="enable_whatsapp" 
                                           <?php echo getSetting('communication', 'enable_whatsapp', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable Phone Calls</h6>
                                    <p class="setting-description">Clients can call you directly</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_calls" id="enable_calls" 
                                           <?php echo getSetting('communication', 'enable_calls', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable Email</h6>
                                    <p class="setting-description">Clients can email you</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_email" id="enable_email" 
                                           <?php echo getSetting('communication', 'enable_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Enable AI Booking Assistant</h6>
                                    <p class="setting-description">AI handles initial booking inquiries</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_ai_booking" id="enable_ai_booking" 
                                           <?php echo getSetting('communication', 'enable_ai_booking', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">🔕 Do-Not-Disturb Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Do Not Disturb Mode</h6>
                                    <p class="setting-description">Temporarily disable notifications</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="do_not_disturb" id="do_not_disturb" 
                                           <?php echo getSetting('communication', 'do_not_disturb', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Quiet Hours Start</label>
                                <input type="time" class="form-control" name="quiet_hours_start" 
                                       value="<?php echo getSetting('communication', 'quiet_hours_start', '22:00'); ?>">
                                <p class="form-text">Start time for quiet hours</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Quiet Hours End</label>
                                <input type="time" class="form-control" name="quiet_hours_end" 
                                       value="<?php echo getSetting('communication', 'quiet_hours_end', '06:00'); ?>">
                                <p class="form-text">End time for quiet hours</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">Auto-Reply Messages</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Auto-Reply Message (When Busy)</label>
                                <textarea class="form-control" name="auto_reply_message" rows="3" 
                                          placeholder="I'm currently busy with another client. I'll get back to you as soon as possible. Thank you for your patience."><?php echo htmlspecialchars(getSetting('communication', 'auto_reply_message', '')); ?></textarea>
                                <p class="form-text">Automatic response when you're busy</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">"Busy/On Job" Status Message</label>
                                <textarea class="form-control" name="busy_message" rows="3" 
                                          placeholder="Currently working on a job. Will be available in [time]. For emergencies, please call [phone]."><?php echo htmlspecialchars(getSetting('communication', 'busy_message', '')); ?></textarea>
                                <p class="form-text">Message shown when you mark yourself as busy</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Communication Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Respond to messages within 1 hour for better ratings</li>
                        <li>Set realistic quiet hours to maintain work-life balance</li>
                        <li>Use professional auto-reply messages</li>
                        <li>Keep clients informed about your availability</li>
                        <li>Consider time zones when setting quiet hours</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Communication Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 🌐 Language & Localization -->
        <?php if ($settings_section === 'language'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-language"></i> Language & Localization</h2>
            
            <form method="POST" id="languageForm">
                <input type="hidden" name="section" value="language">
                
                <div class="settings-card">
                    <div class="card-body">
                        <h4 class="mb-3"><?php echo __('settings.language.title', [], 'settings'); ?></h4>
                        <p class="text-muted mb-4"><?php echo __('settings.language.select_language', [], 'settings'); ?></p>
                        
                        <div class="form-group mb-4">
                            <label for="language-select" class="form-label"><strong><?php echo __('settings.language.current_language', [], 'settings'); ?></strong></label>
                            <select id="language-select" name="language" class="form-select form-select-lg" onchange="document.getElementById('languageForm').submit();">
                                <?php 
                                $current_lang = getCurrentLanguage();
                                foreach (getSupportedLanguages() as $lang_code => $lang_name): 
                                ?>
                                    <option value="<?php echo $lang_code; ?>" <?php echo $current_lang === $lang_code ? 'selected' : ''; ?>>
                                        <?php echo $lang_name; ?> (<?php echo strtoupper($lang_code); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted mt-2">
                                <?php echo __('common.app_name', [], 'common'); ?> will use your selected language for all pages and notifications.
                            </small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Language Support:</strong>
                            Your language preference is saved to your account and will be remembered across devices.
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> <?php echo __('settings.common.save', [], 'settings'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 🔔 Notifications & Alerts -->
        <?php if ($settings_section === 'notifications'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-bell"></i> 9. Notifications & Alerts</h2>
            
            <form method="POST" id="notificationsForm">
                <input type="hidden" name="section" value="notifications">
                
                <h4 class="mb-3">Booking Notifications</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Booking (Email)</h6>
                                    <p class="setting-description">Receive email for new bookings</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="new_booking_email" id="new_booking_email" 
                                           <?php echo getSetting('notifications', 'new_booking_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Booking (SMS)</h6>
                                    <p class="setting-description">Receive SMS for new bookings</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="new_booking_sms" id="new_booking_sms" 
                                           <?php echo getSetting('notifications', 'new_booking_sms', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Booking (Push)</h6>
                                    <p class="setting-description">Receive push notifications</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="new_booking_push" id="new_booking_push" 
                                           <?php echo getSetting('notifications', 'new_booking_push', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">Message Notifications</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Chat Messages (Email)</h6>
                                    <p class="setting-description">Email for new messages</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="chat_message_email" id="chat_message_email" 
                                           <?php echo getSetting('notifications', 'chat_message_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Chat Messages (SMS)</h6>
                                    <p class="setting-description">SMS for new messages</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="chat_message_sms" id="chat_message_sms" 
                                           <?php echo getSetting('notifications', 'chat_message_sms', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Chat Messages (Push)</h6>
                                    <p class="setting-description">Push notifications for messages</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="chat_message_push" id="chat_message_push" 
                                           <?php echo getSetting('notifications', 'chat_message_push', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">Payment & Review Notifications</h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Payment Received (Email)</h6>
                                    <p class="setting-description">Email when payment is received</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="payment_received_email" id="payment_received_email" 
                                           <?php echo getSetting('notifications', 'payment_received_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Payment Received (SMS)</h6>
                                    <p class="setting-description">SMS when payment is received</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="payment_received_sms" id="payment_received_sms" 
                                           <?php echo getSetting('notifications', 'payment_received_sms', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Review (Email)</h6>
                                    <p class="setting-description">Email when you receive a review</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="review_received_email" id="review_received_email" 
                                           <?php echo getSetting('notifications', 'review_received_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Review (SMS)</h6>
                                    <p class="setting-description">SMS when you receive a review</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="review_received_sms" id="review_received_sms" 
                                           <?php echo getSetting('notifications', 'review_received_sms', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">New Review (Push)</h6>
                                    <p class="setting-description">Push notification for new reviews</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="review_received_push" id="review_received_push" 
                                           <?php echo getSetting('notifications', 'review_received_push', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4 mb-3">System & Marketing Notifications</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">System Announcements (Email)</h6>
                                    <p class="setting-description">Important platform updates</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="system_announcements_email" id="system_announcements_email" 
                                           <?php echo getSetting('notifications', 'system_announcements_email', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">System Announcements (SMS)</h6>
                                    <p class="setting-description">Important SMS announcements</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="system_announcements_sms" id="system_announcements_sms" 
                                           <?php echo getSetting('notifications', 'system_announcements_sms', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Marketing Emails</h6>
                                    <p class="setting-description">Promotional offers and tips</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="marketing_email" id="marketing_email" 
                                           <?php echo getSetting('notifications', 'marketing_email', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Marketing SMS</h6>
                                    <p class="setting-description">Promotional SMS messages</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="marketing_sms" id="marketing_sms" 
                                           <?php echo getSetting('notifications', 'marketing_sms', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Notification Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Enable booking notifications to respond quickly to clients</li>
                        <li>Consider SMS notifications for urgent matters</li>
                        <li>Disable marketing notifications if you prefer fewer emails</li>
                        <li>Keep system announcements enabled for important updates</li>
                        <li>Adjust notification preferences based on your work schedule</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Notification Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- ⭐ Ratings, Reviews & Reputation -->
        <?php if ($settings_section === 'reviews'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-star"></i> 10. Ratings, Reviews & Reputation</h2>
            
            <!-- Reviews Overview -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="analytic-card">
                        <h3><?php echo number_format($analytics['avg_rating'] ?? 0, 1); ?>/5.0</h3>
                        <p>Average Rating</p>
                        <div class="rating-stars">
                            <?php
                            $avgRating = $analytics['avg_rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= floor($avgRating)):
                                    echo '<i class="fas fa-star"></i>';
                                elseif ($i - 0.5 <= $avgRating):
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                else:
                                    echo '<i class="far fa-star"></i>';
                                endif;
                            endfor;
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="analytic-card">
                        <h3><?php echo $analytics['unique_clients'] ?? 0; ?></h3>
                        <p>Clients Served</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Reviews -->
            <h4 class="mb-3">Recent Reviews</h4>
            
            <?php if (!empty($recentReviews)): ?>
                <div class="reviews-list">
                    <?php foreach ($recentReviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-client">
                                    <div class="client-avatar">
                                        <?php if (!empty($review['client_image'])): ?>
                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($review['client_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($review['client_name']); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($review['client_name']); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                </div>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="mb-0"><?php echo htmlspecialchars($review['comment']); ?></p>
                            
                            <?php if (!empty($review['provider_response'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">Your response:</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($review['provider_response']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You haven't received any reviews yet. Complete jobs to get reviews from clients.
                </div>
            <?php endif; ?>
            
            <form method="POST" id="reviewsForm">
                <input type="hidden" name="section" value="reviews">
                
                <h4 class="mt-4 mb-3">Review Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Public Response to Reviews</h6>
                                    <p class="setting-description">Allow public responses to reviews</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="public_response" id="public_response" 
                                           <?php echo getSetting('reviews', 'public_response', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Thank-You Auto Reply</h6>
                                    <p class="setting-description">Auto-thank clients for reviews</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="thank_you_auto_reply" id="thank_you_auto_reply" 
                                           <?php echo getSetting('reviews', 'thank_you_auto_reply', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Hide Old Reviews</h6>
                                    <p class="setting-description">Hide reviews older than 1 year</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="hide_old_reviews" id="hide_old_reviews" 
                                           <?php echo getSetting('reviews', 'hide_old_reviews', 0) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Rating Visibility</label>
                                <select name="rating_visibility" class="form-select">
                                    <option value="public" <?php echo getSetting('reviews', 'rating_visibility') === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="clients_only" <?php echo getSetting('reviews', 'rating_visibility') === 'clients_only' ? 'selected' : ''; ?>>Clients Only</option>
                                    <option value="private" <?php echo getSetting('reviews', 'rating_visibility') === 'private' ? 'selected' : ''; ?>>Private</option>
                                </select>
                                <p class="form-text">Who can see your ratings and reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="setting-card mt-3">
                    <div class="setting-header">
                        <div>
                            <h6 class="setting-title">Review Notifications</h6>
                            <p class="setting-description">Get notified about new reviews</p>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" name="review_notifications" id="review_notifications" 
                                   <?php echo getSetting('reviews', 'review_notifications', 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Reputation Insights -->
                <h4 class="mt-4 mb-3">📊 Reputation Insights</h4>
                
                <div class="stats-overview">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($analytics['avg_rating'] ?? 0, 1); ?></div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $analytics['completed_jobs'] ?? 0; ?></div>
                        <div class="stat-label">Jobs Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            $completionRate = $analytics['total_jobs'] > 0 ? 
                                ($analytics['completed_jobs'] / $analytics['total_jobs']) * 100 : 0;
                            echo number_format($completionRate, 0);
                            ?>%
                        </div>
                        <div class="stat-label">Completion Rate</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php
                            $repeatRate = $analytics['unique_clients'] > 0 ? 
                                (($analytics['total_jobs'] - $analytics['unique_clients']) / $analytics['unique_clients']) * 100 : 0;
                            echo number_format($repeatRate, 0);
                            ?>%
                        </div>
                        <div class="stat-label">Repeat Customer Rate</div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Reputation Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Respond to all reviews, especially negative ones</li>
                        <li>Ask satisfied clients for reviews</li>
                        <li>Maintain high completion rates for better visibility</li>
                        <li>Use feedback to improve your services</li>
                        <li>Build long-term relationships with repeat clients</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Review Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 🛡️ Security & Safety Settings -->
        <?php if ($settings_section === 'security'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-shield-alt"></i> 11. Security & Safety Settings</h2>
            
            <form method="POST" id="securityForm">
                <input type="hidden" name="section" value="security">
                
                <h4 class="mb-3">Security Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Two-Factor Authentication (2FA)</h6>
                                    <p class="setting-description">Add extra security to your account</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="enable_2fa" id="enable_2fa" 
                                           <?php echo $provider['two_factor_enabled'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                            <?php if ($provider['two_factor_enabled']): ?>
                                <div class="mt-2">
                                    <span class="badge bg-success"><i class="fas fa-check"></i> 2FA Enabled</span>
                                    <a href="#" class="btn btn-sm btn-outline-danger ms-2">Disable 2FA</a>
                                </div>
                            <?php else: ?>
                                <div class="mt-2">
                                    <a href="#" class="btn btn-sm btn-outline-primary">Set Up 2FA</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Login Alerts</h6>
                                    <p class="setting-description">Get notified of new logins</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="login_alerts" id="login_alerts" 
                                           <?php echo $provider['login_notifications'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" name="session_timeout" 
                                       value="<?php echo getSetting('security', 'session_timeout', 30); ?>" min="5" max="480" step="5">
                                <p class="form-text">Auto-logout after inactivity</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Password Change -->
                <h4 class="mt-4 mb-3">Change Password</h4>
                
                <div class="setting-card">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" 
                                       placeholder="Enter current password">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" 
                                       placeholder="Enter new password">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" 
                                       placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>
                    <p class="form-text">Password must be at least 8 characters with numbers and special characters</p>
                </div>
                
                <h4 class="mt-4 mb-3">🚨 Safety Settings</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="form-group">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars(getSetting('security', 'emergency_contact', '')); ?>" 
                                       placeholder="Name and phone number">
                                <p class="form-text">Contact to notify in emergencies</p>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Panic Button (On Job)</h6>
                                    <p class="setting-description">Enable emergency alert button</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="panic_button_enabled" id="panic_button_enabled" 
                                           <?php echo getSetting('security', 'panic_button_enabled', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                            <?php if (getSetting('security', 'panic_button_enabled', 1)): ?>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Test Panic Button
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Report Abusive Clients</h6>
                                    <p class="setting-description">Enable client reporting</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="report_abusive_clients" id="report_abusive_clients" 
                                           <?php echo getSetting('security', 'report_abusive_clients', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="setting-card">
                            <div class="setting-header">
                                <div>
                                    <h6 class="setting-title">Job Cancellation Protection</h6>
                                    <p class="setting-description">Protect against last-minute cancellations</p>
                                </div>
                                <div class="toggle-switch">
                                    <input type="checkbox" name="job_cancellation_protection" id="job_cancellation_protection" 
                                           <?php echo getSetting('security', 'job_cancellation_protection', 1) ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Session History -->
                <h4 class="mt-4 mb-3">Device & Session Management</h4>
                
                <div class="setting-card">
                    <div class="session-list">
                        <?php if (!empty($sessionHistory)): ?>
                            <?php foreach ($sessionHistory as $session): ?>
                                <div class="session-item <?php echo strtotime($session['logout_time']) === null ? 'current-session' : ''; ?>">
                                    <div class="session-info">
                                        <div class="session-device">
                                            <i class="fas fa-desktop me-2"></i>
                                            <?php echo htmlspecialchars($session['device']); ?>
                                        </div>
                                        <div class="session-ip">
                                            <i class="fas fa-globe me-2"></i>
                                            <?php echo htmlspecialchars($session['ip_address']); ?>
                                        </div>
                                        <div class="session-time">
                                            <i class="far fa-clock me-2"></i>
                                            Last login: <?php echo date('M d, Y H:i', strtotime($session['login_time'])); ?>
                                        </div>
                                    </div>
                                    <?php if (strtotime($session['logout_time']) === null): ?>
                                        <span class="badge bg-success">Current Session</span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="logoutDevice('<?php echo $session['device']; ?>')">
                                            <i class="fas fa-sign-out-alt"></i> Logout
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted py-4">No session history available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Security Tips:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Use strong, unique passwords</li>
                        <li>Enable 2FA for extra security</li>
                        <li>Regularly review active sessions</li>
                        <li>Log out from unused devices</li>
                        <li>Never share your login credentials</li>
                        <li>Use the panic button in unsafe situations</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn-save mt-3">
                    <i class="fas fa-save me-2"></i> Save Security Settings
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- 📈 Analytics & Performance Dashboard -->
        <?php if ($settings_section === 'analytics'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> 12. Analytics & Performance Dashboard</h2>
            
            <!-- Analytics Overview -->
            <div class="analytics-grid">
                <div class="analytic-card">
                    <h3><?php echo $analytics['total_jobs'] ?? 0; ?></h3>
                    <p>Total Jobs (30 days)</p>
                </div>
                
                <div class="analytic-card">
                    <h3><?php echo $analytics['completed_jobs'] ?? 0; ?></h3>
                    <p>Completed Jobs</p>
                </div>
                
                <div class="analytic-card">
                    <h3><?php echo $analytics['unique_clients'] ?? 0; ?></h3>
                    <p>Unique Clients</p>
                </div>
                
                <div class="analytic-card">
                    <h3><?php echo $analytics['active_days'] ?? 0; ?></h3>
                    <p>Active Days</p>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <h4 class="mb-3">Performance Metrics</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Job Completion Rate</h5>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php 
                                 $completionRate = $analytics['total_jobs'] > 0 ? 
                                     ($analytics['completed_jobs'] / $analytics['total_jobs']) * 100 : 0;
                                 echo $completionRate;
                                 ?>%;">
                                <?php echo number_format($completionRate, 0); ?>%
                            </div>
                        </div>
                        <p class="form-text mt-2">Percentage of jobs completed successfully</p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Customer Satisfaction</h5>
                        <div class="rating-stars mb-2">
                            <?php
                            $avgRating = $analytics['avg_rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= floor($avgRating)):
                                    echo '<i class="fas fa-star"></i>';
                                elseif ($i - 0.5 <= $avgRating):
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                else:
                                    echo '<i class="far fa-star"></i>';
                                endif;
                            endfor;
                            ?>
                            <span class="ms-2"><?php echo number_format($avgRating, 1); ?>/5.0</span>
                        </div>
                        <p class="form-text">Based on client reviews</p>
                    </div>
                </div>
            </div>
            
            <!-- Booking Insights -->
            <h4 class="mt-4 mb-3">Booking Insights</h4>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="setting-card">
                        <h5 class="mb-3">Weekly Bookings</h5>
                        <h3 class="text-primary"><?php echo $analytics['weekly_bookings'] ?? 0; ?></h3>
                        <p class="form-text">Bookings in last 7 days</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="setting-card">
                        <h5 class="mb-3">Cancellation Rate</h5>
                        <h3 class="text-warning">
                            <?php
                            $cancellationRate = $analytics['total_jobs'] > 0 ? 
                                ($analytics['cancelled_jobs'] / $analytics['total_jobs']) * 100 : 0;
                            echo number_format($cancellationRate, 0);
                            ?>%
                        </h3>
                        <p class="form-text">Jobs cancelled by clients</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="setting-card">
                        <h5 class="mb-3">Repeat Customer Rate</h5>
                        <h3 class="text-success">
                            <?php
                            $repeatRate = $analytics['unique_clients'] > 0 ? 
                                (($analytics['total_jobs'] - $analytics['unique_clients']) / $analytics['unique_clients']) * 100 : 0;
                            echo number_format($repeatRate, 0);
                            ?>%
                        </h3>
                        <p class="form-text">Clients who book again</p>
                    </div>
                </div>
            </div>
            
            <!-- Profile Views & Conversion -->
            <h4 class="mt-4 mb-3">Profile Performance</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Profile Views</h5>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-eye fa-2x text-primary me-3"></i>
                            <div>
                                <h3 class="mb-0">0</h3>
                                <p class="form-text mb-0">Times your profile was viewed</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Booking Conversion Rate</h5>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chart-line fa-2x text-success me-3"></i>
                            <div>
                                <h3 class="mb-0">0%</h3>
                                <p class="form-text mb-0">Views to bookings conversion</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Earnings Analytics -->
            <h4 class="mt-4 mb-3">Earnings Analytics</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Total Earnings (30 days)</h5>
                        <h3 class="text-success">RWF 0</h3>
                        <p class="form-text">Total earned in last 30 days</p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Average per Job</h5>
                        <h3 class="text-primary">RWF 0</h3>
                        <p class="form-text">Average earnings per job</p>
                    </div>
                </div>
            </div>
            
            <!-- Peak Hours -->
            <h4 class="mt-4 mb-3">Peak Booking Hours</h4>
            
            <div class="setting-card">
                <p class="text-muted">Data not available yet. Complete more jobs to see peak hours analysis.</p>
                <!-- Peak hours chart would go here -->
            </div>
            
            <!-- Most Requested Services -->
            <h4 class="mt-4 mb-3">Most Requested Services</h4>
            
            <div class="setting-card">
                <p class="text-muted">Data not available yet. Complete more jobs to see service popularity.</p>
                <!-- Service popularity chart would go here -->
            </div>
            
            <!-- Missed Opportunities -->
            <h4 class="mt-4 mb-3">Missed Opportunities</h4>
            
            <div class="setting-card">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Improvement Areas:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Complete your profile verification for better visibility</li>
                        <li>Add more service categories to attract diverse clients</li>
                        <li>Set competitive pricing based on market rates</li>
                        <li>Improve response time to booking requests</li>
                        <li>Ask satisfied clients for reviews</li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i>
                <strong>Analytics Tips:</strong> 
                <ul class="mb-0 mt-2">
                    <li>Check analytics weekly to track performance</li>
                    <li>Use data to optimize your pricing and availability</li>
                    <li>Focus on improving low metrics</li>
                    <li>Set goals based on analytics data</li>
                    <li>Compare your performance with platform averages</li>
                </ul>
            </div>
            
            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i> Export Analytics
                </button>
                <button type="button" class="btn btn-outline-primary">
                    <i class="fas fa-sync me-2"></i> Refresh Data
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- ⚙️ Account Control -->
        <?php if ($settings_section === 'account'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-user-cog"></i> 13. Account Control</h2>
            
            <!-- Account Information -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Account Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Account Status:</strong></td>
                                <td>
                                    <?php if ($provider['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Member Since:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($provider['join_date'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email Verified:</strong></td>
                                <td>
                                    <?php if ($provider['email_verified']): ?>
                                        <span class="badge bg-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Not Verified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Last Password Change:</strong></td>
                                <td>
                                    <?php
                                    $stmt = $db->prepare("SELECT password_changed_at FROM users WHERE id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $passwordChanged = $stmt->fetchColumn();
                                    echo $passwordChanged ? date('M d, Y', strtotime($passwordChanged)) : 'Never';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="setting-card">
                        <h5 class="mb-3">Account Actions</h5>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#switchCategoryModal">
                                <i class="fas fa-exchange-alt me-2"></i> Switch Service Category
                            </button>
                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                <i class="fas fa-pause me-2"></i> Temporarily Deactivate Account
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="exportData()">
                                <i class="fas fa-download me-2"></i> Export Personal Data
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="fas fa-trash me-2"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Legal & Platform Rules -->
            <h4 class="mb-3">Legal & Platform Rules</h4>
            
            <div class="setting-card">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="accept_terms" checked>
                    <label class="form-check-label" for="accept_terms">
                        I accept the <a href="../terms.php" target="_blank">Terms of Service</a> and <a href="../privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="service_agreement" checked>
                    <label class="form-check-label" for="service_agreement">
                        I agree to the <a href="../service-agreement.php" target="_blank">Service Provider Agreement</a>
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="cancellation_policy" checked>
                    <label class="form-check-label" for="cancellation_policy">
                        I accept the <a href="../cancellation-policy.php" target="_blank">Cancellation Policy</a>
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="data_privacy" checked>
                    <label class="form-check-label" for="data_privacy">
                        I consent to data processing as described in the <a href="../privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="danger-zone mt-4">
                <h4 class="section-title"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                
                <div class="p-3">
                    <p class="text-danger"><strong>Warning:</strong> These actions are irreversible. Please proceed with caution.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>Deactivate Account</h6>
                                <p class="text-muted small">Temporarily hide your profile and stop receiving bookings. You can reactivate anytime.</p>
                                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                    <i class="fas fa-pause me-1"></i> Deactivate Account
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6>Delete Account</h6>
                                <p class="text-muted small">Permanently delete your account and all data. This action cannot be undone.</p>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash me-1"></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account History -->
            <h4 class="mt-4 mb-3">Account History</h4>
            
            <div class="setting-card">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo date('Y-m-d'); ?></td>
                                <td>Settings Updated</td>
                                <td>Profile visibility settings changed</td>
                                <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime('-1 day')); ?></td>
                                <td>Login</td>
                                <td>Successful login from <?php echo $_SERVER['HTTP_USER_AGENT']; ?></td>
                                <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                            </tr>
                            <!-- More history rows would be loaded from database -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ✓ Profile Requirements & Completion -->
        <?php if ($settings_section === 'requirements'): ?>
        <div class="settings-section active">
            <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Profile Requirements & Completion</h2>
            
            <div class="row mb-4">
                <div class="col-lg-8">
                    <?php echo $requirements->renderChecklist(true); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    
    <!-- Withdraw Modal removed (withdrawals unsupported) -->
    
    <!-- Switch Category Modal -->
    <div class="modal fade" id="switchCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="section" value="account">
                    <input type="hidden" name="account_action" value="switch_category">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Switch Service Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Switching categories may affect your visibility and existing bookings. Please review carefully.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Service Category</label>
                            <select name="new_category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($allCategories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_switch" required>
                            <label class="form-check-label" for="confirm_switch">
                                I understand that switching categories may require re-verification
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Switch Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="section" value="account">
                    <input type="hidden" name="account_action" value="deactivate">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Deactivate Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>What happens when you deactivate your account:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your profile will be hidden from search results</li>
                                <li>You will not receive new booking requests</li>
                                <li>Existing bookings will be notified</li>
                                <li>Your data will be preserved</li>
                                <li>You can reactivate anytime by logging in</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Deactivation (Optional)</label>
                            <textarea class="form-control" rows="3" placeholder="Help us improve by sharing your reason"></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_deactivate" required>
                            <label class="form-check-label" for="confirm_deactivate">
                                I understand that I can reactivate my account anytime by logging in
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Deactivate Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="section" value="account">
                    <input type="hidden" name="account_action" value="delete_account">
                    
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Delete Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>This action cannot be undone. All your data will be permanently deleted.</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your profile and all service listings will be removed</li>
                                <li>All booking history and reviews will be deleted</li>
                                <li>Any pending payments will be canceled</li>
                                <li>This action is irreversible</li>
                                <li>Account deletion takes 30 days to complete</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">To confirm, type <strong>DELETE</strong> below:</label>
                            <input type="text" class="form-control" name="confirm_delete" placeholder="DELETE" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Leaving (Optional)</label>
                            <select class="form-select">
                                <option value="">Select reason</option>
                                <option>Found another platform</option>
                                <option>Not getting enough business</option>
                                <option>Technical issues</option>
                                <option>Too many fees</option>
                                <option>Privacy concerns</option>
                                <option>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_delete_checkbox" required>
                            <label class="form-check-label" for="confirm_delete_checkbox">
                                I understand that all my data will be permanently deleted and this action cannot be undone
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Permanently Delete Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Event bus for cross-tab/page communication -->
    <script src="../assets/js/eventBus.js"></script>
    <!-- Bootstrap JS -->
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS for maps -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
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
        
        // Settings navigation - removed (using URL-based navigation instead)
        
        // Initialize map for location section
        let map;
        let markers = [];
        
        function initializeMap() {
            if (document.getElementById('serviceMap')) {
                map = L.map('serviceMap').setView([-1.9441, 30.0619], 12); // Kigali center
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                // Keep map in sync with form
                map.on('click', handleMapClick);

                const chosenRadio = document.querySelector('input[name="primary_area"]:checked');
                selectedServiceAreaIndex = chosenRadio ? Number(chosenRadio.value) : 0;
                setSelectedServiceArea(selectedServiceAreaIndex);
                renderServiceAreasOnMap();
            }
        }
        
        function addServiceAreaToMap(lat, lng, radius, name, isPrimary) {
            // Add circle for service area
            const circle = L.circle([lat, lng], {
                color: isPrimary ? 'blue' : 'green',
                fillColor: isPrimary ? '#3388ff' : '#33cc33',
                fillOpacity: 0.2,
                radius: radius * 1000 // Convert km to meters
            }).addTo(map);
            
            // Add marker
            const marker = L.marker([lat, lng]).addTo(map)
                .bindPopup(`<strong>${name}</strong><br>Radius: ${radius}km<br>${isPrimary ? 'Primary Area' : 'Secondary Area'}`);
            
            markers.push({ circle, marker });
        }
        
        // Service area management
        let serviceAreaCount = <?php echo count($serviceAreas); ?>;
        
        function addServiceArea() {
            const container = document.getElementById('serviceAreasContainer');
            const newIndex = serviceAreaCount;
            
            const newArea = document.createElement('div');
            newArea.className = 'service-area-form';
            newArea.setAttribute('data-index', newIndex);
            newArea.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Area Name</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][name]" 
                                   placeholder="e.g., Kigali City Center" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][lat]" 
                                   placeholder="e.g., -1.9441" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="service_areas[${newIndex}][lng]" 
                                   placeholder="e.g., 30.0619" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Service Radius (km)</label>
                            <input type="number" class="form-control" name="service_areas[${newIndex}][radius]" 
                                   value="10" min="1" max="100" step="1" required>
                            <p class="form-text">Distance you're willing to travel from this point</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-info me-2" onclick="setSelectedServiceArea(${newIndex});">
                            <i class="fas fa-map-pin"></i> Pick on Map
                        </button>

                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" name="primary_area" value="${newIndex}">
                            <label class="form-check-label">Set as Primary</label>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removeServiceArea(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newArea);
            serviceAreaCount++;
            setSelectedServiceArea(newIndex);
            renderServiceAreasOnMap();
        }
        
        function removeServiceArea(button) {
            const areaForm = button.closest('.service-area-form');
            const removedIndex = areaForm.dataset.index;
            areaForm.remove();
            if (selectedServiceAreaIndex === Number(removedIndex)) {
                selectedServiceAreaIndex = 0;
                setSelectedServiceArea(0);
            }
            renderServiceAreasOnMap();
        }

        let selectedServiceAreaIndex = null;

        function setSelectedServiceArea(index) {
            selectedServiceAreaIndex = Number(index);
            document.querySelectorAll('.service-area-form').forEach(form => {
                form.classList.toggle('active', Number(form.dataset.index) === selectedServiceAreaIndex);
            });

            const targetRadio = document.querySelector(`input[name='primary_area'][value='${selectedServiceAreaIndex}']`);
            if (targetRadio) {
                targetRadio.checked = true;
            }

            document.querySelector('.alert-secondary').innerHTML =
                `<i class="fas fa-info-circle me-2"></i>Click on the map to set latitude and longitude for the selected service area (index ${selectedServiceAreaIndex + 1}).`;
        }

        function getServiceAreaDataFromForm(form) {
            const index = form.dataset.index;
            const name = form.querySelector(`[name='service_areas[${index}][name]']`)?.value || `Area ${Number(index) + 1}`;
            const lat = parseFloat(form.querySelector(`[name='service_areas[${index}][lat]']`)?.value);
            const lng = parseFloat(form.querySelector(`[name='service_areas[${index}][lng]']`)?.value);
            const radius = parseFloat(form.querySelector(`[name='service_areas[${index}][radius]']`)?.value) || 10;
            const isPrimary = form.querySelector(`input[name='primary_area']:checked`)?.value === index;
            return { name, lat, lng, radius, isPrimary };
        }

        function renderServiceAreasOnMap() {
            if (!map) return;
            markers.forEach(m => {
                map.removeLayer(m.circle);
                map.removeLayer(m.marker);
            });
            markers = [];

            const areaForms = Array.from(document.querySelectorAll('.service-area-form'));
            const bounds = L.featureGroup();

            areaForms.forEach(form => {
                const area = getServiceAreaDataFromForm(form);
                if (!isNaN(area.lat) && !isNaN(area.lng)) {
                    addServiceAreaToMap(area.lat, area.lng, area.radius, area.name, area.isPrimary);
                    bounds.addLayer(L.circle([area.lat, area.lng], { radius: area.radius * 1000 }));
                }
            });

            if (bounds.getLayers().length > 0) {
                map.fitBounds(bounds.getBounds().pad(0.25));
            }
        }

        function handleMapClick(e) {
            if (selectedServiceAreaIndex === null) {
                alert('Please select a service area first by clicking "Pick on Map"');
                return;
            }

            const form = document.querySelector(`.service-area-form[data-index='${selectedServiceAreaIndex}']`);
            if (!form) {
                alert('Selected service area form not found.');
                return;
            }

            const latInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][lat]']`);
            const lngInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][lng]']`);
            const nameInput = form.querySelector(`input[name='service_areas[${selectedServiceAreaIndex}][name]']`);

            if (latInput && lngInput) {
                const lat = e.latlng.lat.toFixed(6);
                const lng = e.latlng.lng.toFixed(6);

                latInput.value = lat;
                lngInput.value = lng;

                // Reverse geocode area label if area name is blank or default
                if (nameInput && (!nameInput.value.trim() || nameInput.value.startsWith('Area '))) {
                    const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=14&addressdetails=1`;

                    fetch(nominatimUrl, { method: 'GET', headers: { 'Accept': 'application/json' } })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.address) {
                                const address = data.address;
                                const placeParts = [
                                    address.neighbourhood,
                                    address.suburb,
                                    address.city_district,
                                    address.city,
                                    address.town,
                                    address.village,
                                    address.state,
                                    address.country
                                ].filter(Boolean);

                                if (placeParts.length) {
                                    nameInput.value = placeParts[0];
                                }
                            }
                        })
                        .catch(() => {
                            // fallback: keep current name without changing
                        });
                }

                renderServiceAreasOnMap();
            }
        }

        // Payment method management
        let paymentMethodCount = <?php echo count($paymentMethods); ?>;
        
        function addPaymentMethod() {
            const container = document.getElementById('paymentMethodsContainer');
            const newIndex = paymentMethodCount;
            
            const newMethod = document.createElement('div');
            newMethod.className = 'payment-method-card';
            newMethod.setAttribute('data-index', newIndex);
            newMethod.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Method Type</label>
                            <select name="payment_methods[${newIndex}][type]" class="form-select">
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_account">Bank Account</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Account Name</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][account_name]" 
                                   value="<?php echo htmlspecialchars($provider['full_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][account_number]" 
                                   placeholder="07XXXXXXXX or Account Number" required>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Bank Name (if applicable)</label>
                            <input type="text" class="form-control" name="payment_methods[${newIndex}][bank_name]" 
                                   placeholder="Bank of Kigali, Equity Bank, etc.">
                        </div>
                    </div>
                    
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="default_payment_method" value="${newIndex}">
                            <label class="form-check-label">Set as Default</label>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removePaymentMethod(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newMethod);
            paymentMethodCount++;
        }
        
        function removePaymentMethod(button) {
            const methodCard = button.closest('.payment-method-card');
            methodCard.remove();
        }
        
        // Unavailable dates management
        let unavailableDates = [];
        
        function addUnavailableDate() {
            const dateInput = document.getElementById('unavailable_date');
            const date = dateInput.value;
            
            if (!date) {
                alert('Please select a date');
                return;
            }
            
            if (!unavailableDates.includes(date)) {
                unavailableDates.push(date);
                updateUnavailableDatesList();
                dateInput.value = '';
            } else {
                alert('This date is already marked as unavailable');
            }
        }
        
        function updateUnavailableDatesList() {
            const container = document.getElementById('unavailable_dates_list');
            container.innerHTML = '';
            
            if (unavailableDates.length === 0) {
                container.innerHTML = '<p class="text-muted">No unavailable dates added</p>';
                return;
            }
            
            unavailableDates.forEach((date, index) => {
                const dateElement = document.createElement('div');
                dateElement.className = 'd-flex justify-content-between align-items-center mb-2';
                dateElement.innerHTML = `
                    <span>${formatDate(date)}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeUnavailableDate(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(dateElement);
            });
        }
        
        function removeUnavailableDate(index) {
            unavailableDates.splice(index, 1);
            updateUnavailableDatesList();
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        // File preview for identity verification
        function previewFile(input, type) {
            const file = input.files[0];
            if (!file) return;
            
            const previewDiv = document.getElementById(`${type}_preview`);
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                            <img src="${e.target.result}" class="img-thumbnail mt-2" style="max-height: 100px;">
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ${file.name} (${(file.size / 1024).toFixed(2)} KB)
                    </div>
                `;
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map when location section is active
            initializeMap();

            const serviceAreasContainer = document.getElementById('serviceAreasContainer');
            if (serviceAreasContainer) {
                serviceAreasContainer.addEventListener('input', function(e) {
                    if (e.target.name && (e.target.name.startsWith('service_areas') || e.target.name === 'primary_area')) {
                        renderServiceAreasOnMap();
                    }
                });

                serviceAreasContainer.addEventListener('click', function(e) {
                    if (e.target.matches('input[name="primary_area"]')) {
                        setSelectedServiceArea(Number(e.target.value));
                        renderServiceAreasOnMap();
                    }
                });
            }
            
            // Set up form submissions
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Show loading state
                    const submitBtn = this.querySelector('.btn-save');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                        submitBtn.disabled = true;
                        
                        // Re-enable button after 3 seconds (in case of error)
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
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
        });
        
        // Export data function
        function exportData() {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const sectionInput = document.createElement('input');
            sectionInput.type = 'hidden';
            sectionInput.name = 'section';
            sectionInput.value = 'account';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'account_action';
            actionInput.value = 'export_data';
            
            form.appendChild(sectionInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Withdrawal processing removed (feature unsupported)
        
        // Logout device
        function logoutDevice(device) {
            if (confirm(`Log out from ${device}?`)) {
                fetch('logout_device.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        device: device
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Device logged out successfully');
                        location.reload();
                    } else {
                        alert('Failed to log out device');
                    }
                });
            }
        }
        
        // Handle hash changes for direct section access
        window.addEventListener('hashchange', function() {
            const sectionId = window.location.hash.substring(1);
            if (sectionId && document.getElementById(sectionId)) {
                showSection(sectionId);
            }
        });
        
        // Initialize based on current hash
        if (window.location.hash) {
            const sectionId = window.location.hash.substring(1);
            if (sectionId && document.getElementById(sectionId)) {
                // Remove active class from identity nav link
                document.querySelector('.settings-nav a[href="#identity"]').classList.remove('active');
                document.getElementById('identity').classList.remove('active');
                
                // Show the section from hash
                showSection(sectionId);
            }
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>