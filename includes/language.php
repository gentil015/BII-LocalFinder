<?php
/**
 * Language and Translation System for BII LocalFinder
 * Supports multiple languages with database storage of provider preferences
 */

// Supported languages
define('SUPPORTED_LANGUAGES', ['en', 'rw']);
define('DEFAULT_LANGUAGE', 'en');

// Store translations in memory to avoid repeated file loading
$GLOBALS['translations'] = [];
$GLOBALS['current_language'] = null;

/**
 * Initialize language system
 * Loads user's preferred language from database or uses default
 */
function initializeLanguage() {
    // Determine language
    $language = DEFAULT_LANGUAGE;
    
    // Try to get from session first
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['language'])) {
        $language = $_SESSION['language'];
    } 
    // Try to get from database for logged-in users
    elseif (!empty($_SESSION['user_id'])) {
        try {
            // Use Database singleton instead of global $db
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT setting_value FROM provider_settings 
                WHERE provider_id = (SELECT id FROM service_providers WHERE user_id = ?) 
                AND setting_key = 'communication_preferred_language'
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            if ($result && in_array($result['setting_value'], SUPPORTED_LANGUAGES)) {
                $language = $result['setting_value'];
                $_SESSION['language'] = $language;
            }
        } catch (Exception $e) {
            error_log("Language initialization error: " . $e->getMessage());
        }
    }
    // Try to get from URL parameter
    elseif (!empty($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
        $language = $_GET['lang'];
        $_SESSION['language'] = $language;
    }
    // Try to get from cookie
    elseif (!empty($_COOKIE['bii_language']) && in_array($_COOKIE['bii_language'], SUPPORTED_LANGUAGES)) {
        $language = $_COOKIE['bii_language'];
        $_SESSION['language'] = $language;
    }
    // Try browser language
    else {
        $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
        if (in_array($browser_lang, SUPPORTED_LANGUAGES)) {
            $language = $browser_lang;
        }
    }
    
    // Validate and set
    if (!in_array($language, SUPPORTED_LANGUAGES)) {
        $language = DEFAULT_LANGUAGE;
    }
    
    $GLOBALS['current_language'] = $language;
    setcookie('bii_language', $language, time() + (365 * 24 * 60 * 60), '/');
    
    if (!isset($_SESSION['language'])) {
        $_SESSION['language'] = $language;
    }
    
    return $language;
}

/**
 * Load language file
 * 
 * @param string $language Language code (e.g., 'en', 'rw')
 * @param string $section Section/module (e.g., 'dashboard', 'settings', 'common')
 * @return array Translated strings
 */
function loadLanguageFile($language, $section = 'dashboard') {
    // Validate inputs
    if (!in_array($language, SUPPORTED_LANGUAGES)) {
        $language = DEFAULT_LANGUAGE;
    }
    
    // Create cache key
    $cache_key = "{$language}_{$section}";
    
    // Return cached translation if available
    if (isset($GLOBALS['translations'][$cache_key])) {
        return $GLOBALS['translations'][$cache_key];
    }
    
    // Determine file path based on context
    $file_paths = [
        // Provider area
        __DIR__ . "/../provider/languages/{$section}.{$language}.php",
        __DIR__ . "/../provider/languages/{$language}.php",
        // Client area
        __DIR__ . "/../client/languages/{$section}.{$language}.php",
        __DIR__ . "/../client/languages/{$language}.php",
        // Root languages
        __DIR__ . "/../languages/{$section}.{$language}.php",
        __DIR__ . "/../languages/{$language}.php",
    ];
    
    $translations = [];
    
    // Try each path
    foreach ($file_paths as $file_path) {
        if (file_exists($file_path)) {
            $translations = include $file_path;
            if (is_array($translations)) {
                break;
            }
        }
    }
    
    // Cache the result
    $GLOBALS['translations'][$cache_key] = $translations ?: [];
    
    return $GLOBALS['translations'][$cache_key];
}

/**
 * Get translated string
 * One key → One meaning → Many languages approach
 * 
 * @param string $key Translation key (e.g., 'dashboard.welcome.title')
 * @param array $params Parameters to interpolate (optional)
 * @param string $section Section/module (optional)
 * @return string Translated string or key if not found
 */
function __($key, $params = [], $section = 'dashboard') {
    $language = $GLOBALS['current_language'] ?? initializeLanguage();
    
    // Load language file if not already loaded
    $translations = loadLanguageFile($language, $section);
    
    // Navigate nested array structure using dot notation
    $keys = explode('.', $key);
    $value = $translations;
    
    // First, check if we need to navigate to the section
    // If section is provided and exists in translations, start from there
    if (!empty($section) && isset($translations[$section])) {
        $value = $translations[$section];
        // Now navigate the keys from within the section
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Key not found, return original key as fallback
                error_log("Translation missing: {$language}.{$section}.{$key}");
                return $key;
            }
        }
    } else {
        // Navigate from root (for backward compatibility)
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Key not found, return original key as fallback
                error_log("Translation missing: {$language}.{$section}.{$key}");
                return $key;
            }
        }
    }
    
    // If value is not a string, return key
    if (!is_string($value)) {
        error_log("Translation value is not a string: {$language}.{$section}.{$key}");
        return $key;
    }
    
    // Interpolate parameters if provided
    if (!empty($params) && is_array($params)) {
        foreach ($params as $param_key => $param_value) {
            $value = str_replace("{{$param_key}}", $param_value, $value);
            $value = str_replace(":{$param_key}", $param_value, $value);
        }
    }
    
    return $value;
}

/**
 * Get current language
 * 
 * @return string Current language code
 */
function getCurrentLanguage() {
    if (empty($GLOBALS['current_language'])) {
        initializeLanguage();
    }
    return $GLOBALS['current_language'];
}

/**
 * Set current language
 * 
 * @param string $language Language code
 * @return bool Success
 */
function setLanguage($language) {
    if (!in_array($language, SUPPORTED_LANGUAGES)) {
        return false;
    }
    
    $GLOBALS['current_language'] = $language;
    $_SESSION['language'] = $language;
    setcookie('bii_language', $language, time() + (365 * 24 * 60 * 60), '/');
    
    return true;
}

/**
 * Get all supported languages
 * 
 * @return array Languages with display names
 */
function getSupportedLanguages() {
    return [
        'en' => 'English',
        'rw' => 'Kinyarwanda'
    ];
}

/**
 * Save language preference to database
 * 
 * @param int $user_id User ID
 * @param string $language Language code
 * @return bool Success
 */
function saveLanguagePreference($user_id, $language) {
    if (!in_array($language, SUPPORTED_LANGUAGES)) {
        return false;
    }
    
    try {
        // Use Database singleton instead of global $db
        $db = Database::getInstance()->getConnection();
        
        // Get provider ID
        $stmt = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $provider = $stmt->fetch();
        
        if (!$provider) {
            return false;
        }
        
        // Update or insert setting
        $stmt = $db->prepare("
            INSERT INTO provider_settings (provider_id, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        return $stmt->execute([
            $provider['id'],
            'communication_preferred_language',
            $language,
            $language
        ]);
    } catch (Exception $e) {
        error_log("Error saving language preference: " . $e->getMessage());
        return false;
    }
}

// Initialize language on include
if (session_status() === PHP_SESSION_ACTIVE) {
    initializeLanguage();
}
?>
