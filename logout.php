<?php
/**
 * Enhanced Logout Script with Session Management
 * Integrates with Provider Settings and Admin Dashboard
 */

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

// Log logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'] ?? 'unknown';
    
    try {
        // Log the logout for analytics and security
        $stmt = $db->prepare("
            INSERT INTO user_logout_logs (
                user_id, 
                user_type, 
                logout_time, 
                session_duration, 
                ip_address, 
                user_agent
            ) VALUES (?, ?, NOW(), ?, ?, ?)
        ");
        
        // Calculate session duration if login time is stored
        $session_duration = 0;
        if (isset($_SESSION['login_time'])) {
            $session_duration = time() - $_SESSION['login_time'];
        }
        
        $stmt->execute([
            $user_id,
            $user_type,
            $session_duration,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        // If user is provider, update last active status
        if ($user_type === 'provider' && isset($_SESSION['provider_id'])) {
            $stmt = $db->prepare("
                UPDATE service_providers 
                SET last_active = NOW(), 
                    is_online = 0 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['provider_id']]);
            
            // Update provider settings for availability
            $stmt = $db->prepare("
                INSERT INTO provider_settings (provider_id, setting_key, setting_value)
                VALUES (?, 'last_logout', NOW())
                ON DUPLICATE KEY UPDATE setting_value = NOW()
            ");
            $stmt->execute([$_SESSION['provider_id']]);
        }
        
        // If user is admin, log admin logout
        if ($user_type === 'admin') {
            $stmt = $db->prepare("
                INSERT INTO admin_activity_logs (
                    admin_id, 
                    activity_type, 
                    activity_details, 
                    ip_address, 
                    user_agent
                ) VALUES (?, 'logout', 'Admin logged out', ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
        
        // Clear active sessions from database
        $stmt = $db->prepare("
            UPDATE user_sessions 
            SET logout_time = NOW(), 
                is_active = 0 
            WHERE user_id = ? 
            AND session_id = ?
            AND is_active = 1
        ");
        $stmt->execute([$user_id, session_id()]);
        
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = [];

// Remove session cookie with proper parameters
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Additional security: Clear any authentication cookies
$cookies = ['remember_token', 'auth_token', 'two_factor_verified'];
foreach ($cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/');
        unset($_COOKIE[$cookie]);
    }
}

// Set security headers
header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Determine redirect URL based on previous page or user type
$redirect_url = 'index.php';

// Check if we have a return URL in session or GET
if (isset($_SESSION['return_url'])) {
    $redirect_url = $_SESSION['return_url'];
} elseif (isset($_GET['return'])) {
    $redirect_url = filter_var($_GET['return'], FILTER_SANITIZE_URL);
}

// Ensure redirect is within our domain
if (!isValidRedirect($redirect_url)) {
    $redirect_url = 'index.php';
}

// Add logout message parameter
$separator = (strpos($redirect_url, '?') === false) ? '?' : '&';
$redirect_url .= $separator . 'logout_success=1';

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Redirect with 302 Found status
header("Location: $redirect_url", true, 302);
exit();

/**
 * Validate redirect URL to prevent open redirects
 */
function isValidRedirect($url) {
    // Parse URL
    $parsed = parse_url($url);
    
    // Only allow relative URLs or same domain
    if (!isset($parsed['host'])) {
        // Relative URL - check if it's a valid path
        return preg_match('/^[a-zA-Z0-9_\-\.\/\?&=]*$/', $url);
    }
    
    // Check if host matches our domain
    $allowed_domains = [
        $_SERVER['HTTP_HOST'],
        'localhost',
        '127.0.0.1',
        '::1'
    ];
    
    return in_array($parsed['host'], $allowed_domains);
}