<?php
/**
 * Common utility functions for BII LocalFinder
 */

// Database connection (if not already in config/database.php)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/NotificationEngine.php';
require_once __DIR__ . '/sms.php';

if (!defined('WS_AUTH_SECRET')) {
    define('WS_AUTH_SECRET', getenv('WS_AUTH_SECRET') ?: 'BiiLocalFinderLiveLocationSecret_v1');
}

/**
 * Sanitize user input
 *
 * @param string|null $data The input to sanitize
 * @return string The sanitized input
 */
function sanitize($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Sanitize a filename for safe storage on disk.
 * Removes dangerous characters, collapses whitespace to underscores,
 * limits length and returns a basename-safe string.
 *
 * @param string $filename
 * @return string
 */
function sanitize_filename($filename) {
    // Convert Windows backslashes, trim
    $filename = str_replace('\\', '/', $filename);
    $filename = trim($filename);

    // Keep only basic characters, dots, dashes and spaces
    $filename = preg_replace('/[^\w\-\.\s]/u', '', $filename);

    // Replace whitespace with underscore
    $filename = preg_replace('/\s+/', '_', $filename);

    // Prevent directory traversal
    $filename = basename($filename);

    // Limit length
    if (mb_strlen($filename) > 200) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = mb_substr(pathinfo($filename, PATHINFO_FILENAME), 0, 200 - (mb_strlen($ext) + 1));
        $filename = $name . ($ext ? '.' . $ext : '');
    }

    return $filename;
}

/**
 * Check if user is logged in
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged in user is a service provider
 *
 * @return bool
 */
function isProvider() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider';
}

/**
 * Check if logged in user is a client
 *
 * @return bool
 */
function isClient() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client';
}

/**
 * Check if logged in user is an admin
 *
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Generate a random token
 *
 * @param int $length Length of the token
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Format a date string
 *
 * @param string $date Date string
 * @param string $format Desired format (default: Y-m-d H:i:s)
 * @return string
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Require provider login
 *
 * Redirects to login page if the user is not logged in or not a provider.
 */
function requireProvider() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'provider') {
        // use redirect helper so path is always correct regardless of current folder
        redirect('login.php');
    }
}

/**
 * Redirect helper
 * Usage examples:
 *   redirect('login.php');            // go to login page at application root
 *   redirect('/some/absolute/path.php');
 *   redirect('provider/dashboard.php');
 *   redirect('https://external.com');
 */
function getBasePath(): string {
    // Determine the base web path for the application.
    // e.g. if SCRIPT_NAME is /bii_localfinder/client/dashboard.php, return '/bii_localfinder'
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $segments = explode('/', trim($script, '/'));
    if (count($segments) > 1) {
        return '/' . $segments[0];
    }
    return '';
}

function redirect(string $path): void {
    // absolute URL
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    // root-relative path
    if (strpos($path, '/') === 0) {
        header('Location: ' . $path);
        exit;
    }

    // relative path – prepend base application path
    $base = getBasePath();
    $clean = ltrim($path, '/');
    header('Location: ' . $base . '/' . $clean);
    exit;
}

/**
 * Get user's IP address
 *
 * @return string
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Provide a safe global getSetting() if not already declared
 */
if (!function_exists('getSetting')) {
    /**
     * Get a system setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function getSetting($key, $default = '') {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            return $result !== false ? $result : $default;
        } catch (Throwable $e) {
            // Log and return default on error (prevents fatal on missing table/DB issues)
            error_log('getSetting error: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get a provider-specific setting value.
     *
     * @param int $providerId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function getProviderSetting(int $providerId, string $key, $default = '') {
        if ($providerId <= 0) {
            return $default;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT setting_value FROM provider_settings WHERE provider_id = ? AND setting_key = ? LIMIT 1");
            $stmt->execute([$providerId, $key]);
            $result = $stmt->fetch(PDO::FETCH_COLUMN);
            return $result !== false ? $result : $default;
        } catch (Throwable $e) {
            error_log('getProviderSetting error: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Determine whether AI features are enabled for a provider.
     *
     * @param int $providerId
     * @return bool
     */
    function isProviderAIEnabled(int $providerId): bool {
        return getProviderSetting($providerId, 'ai_features_enable_ai_assistant', '0') === '1';
    }

    /**
     * Ensure provider_settings table has the expected schema required by provider settings APIs.
     * This is a safe migration helper for older installs.
     *
     * @param PDO $db
     * @return void
     */
    function ensureProviderSettingsSchema(PDO $db): void {
        try {
            $columns = $db->query("SHOW COLUMNS FROM provider_settings")->fetchAll(PDO::FETCH_ASSOC);
            $hasId = false;
            $hasCreatedAt = false;
            $hasUpdatedAt = false;
            $hasAutoIncrement = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'id') {
                    $hasId = true;
                    if (stripos($column['Extra'], 'auto_increment') !== false) {
                        $hasAutoIncrement = true;
                    }
                }
                if ($column['Field'] === 'created_at') {
                    $hasCreatedAt = true;
                }
                if ($column['Field'] === 'updated_at') {
                    $hasUpdatedAt = true;
                }
            }

            if ($hasId && !$hasAutoIncrement) {
                $db->exec("ALTER TABLE provider_settings MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
            }

            if (!$hasUpdatedAt) {
                $db->exec("ALTER TABLE provider_settings ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
            }

            $indexes = $db->query("SHOW INDEX FROM provider_settings WHERE Key_name = 'unique_setting'")->fetchAll(PDO::FETCH_ASSOC);
            if (count($indexes) === 0) {
                $db->exec("ALTER TABLE provider_settings ADD UNIQUE KEY unique_setting (provider_id, setting_key)");
            }
        } catch (Throwable $e) {
            error_log('ensureProviderSettingsSchema error: ' . $e->getMessage());
        }
    }
}

/**
 * Update the last ML prediction record for a user/provider pair.
 * Marks the most recent unlabeled prediction as success or failure.
 *
 * @param PDO $db
 * @param int $userId
 * @param int $providerId
 * @param int $outcome
 * @return bool
 */
function updateMlPredictionOutcome(PDO $db, int $userId, int $providerId, int $outcome): bool {
    if ($userId <= 0 || $providerId <= 0 || !in_array($outcome, [0, 1], true)) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            "UPDATE ml_predictions_log m
             JOIN (
                 SELECT id FROM ml_predictions_log
                 WHERE user_id = ? AND provider_id = ? AND actual_outcome = 0
                 ORDER BY created_at DESC
                 LIMIT 1
             ) latest ON m.id = latest.id
             SET m.actual_outcome = ?"
        );
        return $stmt->execute([$userId, $providerId, $outcome]);
    } catch (Throwable $e) {
        error_log('updateMlPredictionOutcome error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if platform is in maintenance mode
 *
 * @return bool
 */
function isMaintenanceMode() {
    return getSetting('maintenance_mode', '0') === '1';
}

/**
 * Check if client registration is enabled
 *
 * @return bool
 */
function isClientRegistrationEnabled() {
    return getSetting('client_registration', '1') === '1';
}

/**
 * Check if provider registration is enabled
 *
 * @return bool
 */
function isProviderRegistrationEnabled() {
    return getSetting('provider_registration', '1') === '1';
}

/**
 * Check if provider verification is required
 *
 * @return bool
 */
function isProviderVerificationRequired() {
    return getSetting('provider_verification_required', '0') === '1';
}

/**
 * Check if email verification is enabled
 *
 * @return bool
 */
function isEmailVerificationEnabled() {
    return getSetting('email_verification', '1') === '1';
}

/**
 * Check if phone verification is enabled
 *
 * @return bool
 */
function isPhoneVerificationEnabled() {
    return getSetting('phone_verification', '0') === '1';
}

/**
 * Get minimum password length requirement
 *
 * @return int
 */
function getMinPasswordLength() {
    return (int) getSetting('min_password_length', '8');
}

/**
 * Check if special characters are required in passwords
 *
 * @return bool
 */
function isSpecialCharsRequired() {
    return getSetting('require_special_chars', '0') === '1';
}

/**
 * Get session timeout in minutes
 *
 * @return int
 */
function getSessionTimeout() {
    return (int) getSetting('session_timeout', '60');
}

/**
 * Get maximum pending time for bookings in minutes
 *
 * @return int
 */
function getMaxPendingTime() {
    return (int) getSetting('max_pending_time', '15');
}

/**
 * Check if automatic provider assignment is enabled
 *
 * @return bool
 */
function isAutoAssignProvidersEnabled() {
    return getSetting('auto_assign_providers', '0') === '1';
}

/**
 * Check if booking editing is allowed
 *
 * @return bool
 */
function isBookingEditingAllowed() {
    return getSetting('allow_booking_editing', '1') === '1';
}

/**
 * Check if provider rejection is allowed
 *
 * @return bool
 */
function isProviderRejectionAllowed() {
    return getSetting('allow_provider_rejection', '1') === '1';
}

/**
 * Check if auto-cancel unconfirmed bookings is enabled
 *
 * @return bool
 */
function isAutoCancelUnconfirmedEnabled() {
    return getSetting('auto_cancel_unconfirmed', '1') === '1';
}

/**
 * Check if rating is required after completion
 *
 * @return bool
 */
function isRatingRequiredAfterCompletion() {
    return getSetting('require_rating_after_completion', '0') === '1';
}

/**
 * Get maximum cancellations allowed per month
 *
 * @return int
 */
function getMaxCancellationsPerMonth() {
    return (int) getSetting('max_cancellations_per_month', '3');
}

/**
 * Check if email notifications are enabled
 *
 * @return bool
 */
function isEmailNotificationsEnabled() {
    return getSetting('enable_email_notifications', '1') === '1';
}

/**
 * Check if SMS notifications are enabled
 *
 * @return bool
 */
function isSMSNotificationsEnabled() {
    return getSetting('enable_sms_notifications', '0') === '1';
}

/**
 * Check if commission system is enabled
 *
 * @return bool
 */
function isCommissionEnabled() {
    return getSetting('enable_commission', '0') === '1';
}

/**
 * Get commission rate
 *
 * @return float
 */
function getCommissionRate() {
    return (float) getSetting('commission_rate', '10.0');
}

/**
 * Check if subscriptions are enabled
 *
 * @return bool
 */
function isSubscriptionsEnabled() {
    return getSetting('enable_subscriptions', '0') === '1';
}

/**
 * Check if payouts are enabled
 *
 * @return bool
 */
function isPayoutsEnabled() {
    return getSetting('enable_payouts', '0') === '1';
}

/**
 * Get basic subscription price
 *
 * @return float
 */
function getBasicSubscriptionPrice() {
    return (float) getSetting('basic_subscription_price', '5000.0');
}

/**
 * Get premium subscription price
 *
 * @return float
 */
function getPremiumSubscriptionPrice() {
    return (float) getSetting('premium_subscription_price', '15000.0');
}

/**
 * Get featured listing price
 *
 * @return float
 */
function getFeaturedListingPrice() {
    return (float) getSetting('featured_listing_price', '10000.0');
}

/**
 * Get verification fee
 *
 * @return float
 */
function getVerificationFee() {
    return (float) getSetting('verification_fee', '2000.0');
}

/**
 * Get allowed file types for upload
 *
 * @return array
 */
function getAllowedFileTypes() {
    $types = getSetting('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx');
    return explode(',', $types);
}

/**
 * Get maximum file size for upload in MB
 *
 * @return int
 */
function getMaxFileSize() {
    return (int) getSetting('max_file_size', '10');
}

/**
 * Check if 2FA is enabled for admin
 *
 * @return bool
 */
function is2FAEnabledForAdmin() {
    return getSetting('enable_2fa_admin', '0') === '1';
}

/**
 * Check if auto backup is enabled
 *
 * @return bool
 */
function isAutoBackupEnabled() {
    return getSetting('auto_backup', '1') === '1';
}

/**
 * Check if cookie consent is enabled
 *
 * @return bool
 */
function isCookieConsentEnabled() {
    return getSetting('cookie_consent', '1') === '1';
}

/**
 * Check if debug mode is enabled
 *
 * @return bool
 */
function isDebugMode() {
    return getSetting('debug_mode', '0') === '1';
}

/**
 * Get API rate limit
 *
 * @return int
 */
function getAPIRateLimit() {
    return (int) getSetting('api_rate_limit', '60');
}

/**
 * Get cache duration in minutes
 *
 * @return int
 */
function getCacheDuration() {
    return (int) getSetting('cache_duration', '30');
}

/**
 * Validate password strength
 *
 * @param string $password
 * @return array [is_valid, errors]
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    // Check minimum length
    $minLength = getMinPasswordLength();
    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least {$minLength} characters long";
    }
    
    // Check for special characters if required
    if (isSpecialCharsRequired() && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return [
        'is_valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Check if IP address is blocked
 *
 * @param string $ip
 * @return bool
 */
function isIPBlocked($ip = null) {
    if ($ip === null) {
        $ip = getClientIP();
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM blocked_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking blocked IP: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has exceeded monthly cancellation limit
 *
 * @param int $userId
 * @return bool
 */
function hasExceededCancellationLimit($userId) {
    try {
        $db = Database::getInstance()->getConnection();
        $maxCancellations = getMaxCancellationsPerMonth();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM bookings 
            WHERE user_id = ? AND status = 'cancelled' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$userId]);
        $cancellationCount = $stmt->fetchColumn();
        
        return $cancellationCount >= $maxCancellations;
    } catch (Exception $e) {
        error_log("Error checking cancellation limit: " . $e->getMessage());
        return false;
    }
}

/**
 * Get platform logo URL
 *
 * @return string
 */
function getPlatformLogo() {
    $logo = getSetting('platform_logo', 'default-logo.png');
    return '../assets/images/' . $logo;
}

/**
 * Get platform name
 *
 * @return string
 */
function getPlatformName() {
    return getSetting('platform_name', 'BII LocalFinder');
}

/**
 * Get contact email
 *
 * @return string
 */
function getContactEmail() {
    return getSetting('contact_email', 'support@biilocalfinder.com');
}

/**
 * Get contact phone
 *
 * @return string
 */
function getContactPhone() {
    return getSetting('contact_phone', '+250 788 123 456');
}

/**
 * Get copyright text
 *
 * @return string
 */
function getCopyrightText() {
    return getSetting('copyright_text', '© 2024 BII LocalFinder. All rights reserved.');
}

/**
 * Calculate commission amount
 *
 * @param float $amount
 * @return float
 */
function calculateCommission($amount) {
    if (!isCommissionEnabled()) {
        return 0;
    }
    
    $rate = getCommissionRate();
    return ($amount * $rate) / 100;
}

/**
 * Send email notification
 *
 * @param string $to
 * @param string $subject
 * @param string $message
 * @return bool
 */
function sendEmailNotification($to, $subject, $message) {
    if (!isEmailNotificationsEnabled()) {
        return false;
    }

    try {
        $provider = new EmailProvider();
        $result = $provider->send($to, $subject, $message);
        return !empty($result['success']);
    } catch (Exception $e) {
        error_log("Error sending email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS notification
 *
 * @param string $phone
 * @param string $message
 * @return bool
 */
function sendSMSNotification($phone, $message, array $options = []) {
    if (!isSMSNotificationsEnabled()) {
        return false;
    }

    try {
        $result = SMSNotifier::send($phone, $message, $options);

        if (!empty($result['sms']['demo_mode']) || !empty($result['sms']['success'])) {
            return true;
        }

        return false;
    } catch (Exception $e) {
        error_log("Error sending SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if file type is allowed
 *
 * @param string $filename
 * @return bool
 */
function isFileTypeAllowed($filename) {
    $allowedTypes = getAllowedFileTypes();
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedTypes);
}

/**
 * Check if file size is within limits
 *
 * @param int $fileSize
 * @return bool
 */
function isFileSizeValid($fileSize) {
    $maxSize = getMaxFileSize() * 1024 * 1024; // Convert MB to bytes
    return $fileSize <= $maxSize;
}

/**
 * Get all categories
 *
 * @return array
 */
function getAllCategories() {
    try {
        $db = Database::getInstance()->getConnection();
        return $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all districts
 *
 * @return array
 */
function getAllDistricts() {
    try {
        $db = Database::getInstance()->getConnection();
        return $db->query("SELECT * FROM districts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting districts: " . $e->getMessage());
        return [];
    }
}

/**
 * Maintenance mode check and redirect
 */
function checkMaintenanceMode() {
    if (isMaintenanceMode() && !isAdmin()) {
        // Allow access to login and essential pages even in maintenance
        $allowedPages = ['login.php', 'logout.php', 'maintenance.php'];
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        if (!in_array($currentPage, $allowedPages)) {
            header('Location: maintenance.php');
            exit();
        }
    }
}

/**
 * Initialize session with security settings
 */
function initSession() {
    // Set session timeout
    $timeout = getSessionTimeout() * 60; // Convert minutes to seconds

    // Only modify session ini / cookie params and start session if no session is active
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', $timeout);
        // Use simple signature for broad PHP version compatibility
        session_set_cookie_params($timeout, '/');
        session_start();
    }

    // Check for session timeout
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
        session_unset();
        session_destroy();
        // Start a fresh session after destroying old one
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    $_SESSION['LAST_ACTIVITY'] = time();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (time() - $_SESSION['CREATED'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }

    // Check maintenance mode
    checkMaintenanceMode();

    // Check if IP is blocked
    if (isIPBlocked()) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied. Your IP address has been blocked.');
    }
}

/**
 * Log a user activity (safe: will not fatal if table missing)
 *
 * @param PDO|mixed $db   PDO instance or Database::getInstance()->getConnection()
 * @param int       $user_id
 * @param string    $activity_type
 * @param string    $description
 * @return bool
 */
function logActivity($db, $user_id, $activity_type, $description = '') {
    try {
        // accept either PDO or Database instance
        if ($db instanceof PDO) {
            $pdo = $db;
        } elseif (is_object($db) && method_exists($db, 'getConnection')) {
            $pdo = $db->getConnection();
        } else {
            $pdo = \Database::getInstance()->getConnection();
        }

        $sql = "INSERT INTO user_activities (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$user_id, (string)$activity_type, (string)$description]);
        return true;
    } catch (Throwable $e) {
        // Table may not exist or DB error — log it and continue (do not break user flow)
        error_log('logActivity error: ' . $e->getMessage());
        return false;
    }
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Require login (and optionally role).
 *
 * If the user is not authenticated:
 *  - For normal requests: redirect to login.php with a `next` param set to the current request URI.
 *  - For AJAX requests: return a 401 JSON { error: 'login_required', redirect: '...'} and exit.
 *
 * @param string|null $requiredRole Optional role name ('provider', 'client', 'admin')
 */
function requireLogin($requiredRole = null) {
    // Ensure session and session timeout handling is applied
    if (function_exists('initSession')) {
        initSession();
    } else {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // If not logged in
    if (!isLoggedIn()) {
        $current = $_SERVER['REQUEST_URI'] ?? '/';
        $next = rawurlencode($current); // login.php will rawurldecode before validation

        // Detect AJAX requests (common header)
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $loginUrl = 'login.php?next=' . $next;

        if ($isAjax) {
            header('Content-Type: application/json', true, 401);
            echo json_encode([
                'error' => 'login_required',
                'redirect' => $loginUrl
            ]);
            exit;
        } else {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    // If a role is required, ensure it matches
    if (!empty($requiredRole)) {
        $roleOkay = false;
        if ($requiredRole === 'provider' && isProvider()) $roleOkay = true;
        if ($requiredRole === 'client' && isClient()) $roleOkay = true;
        if ($requiredRole === 'admin' && isAdmin()) $roleOkay = true;

        if (!$roleOkay) {
            // If already logged in but wrong role, redirect to login to allow re-auth
            $current = $_SERVER['REQUEST_URI'] ?? '/';
            $next = rawurlencode($current);
            $loginUrl = 'login.php?next=' . $next;

            // For AJAX, respond 403 with suggestion to re-login
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                header('Content-Type: application/json', true, 403);
                echo json_encode([
                    'error' => 'forbidden',
                    'redirect' => $loginUrl
                ]);
                exit;
            } else {
                header('Location: ' . $loginUrl);
                exit;
            }
        }
    }
}

/**
 * Convert timestamp to human-readable time ago format
 *
 * @param string $datetime Datetime string
 * @return string Human-readable time difference
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $time;

    if ($time_difference < 1) {
        return 'now';
    }

    $condition = array(
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}
?>
