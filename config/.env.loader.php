<?php
/**
 * Simple .env file loader
 * 
 * Loads environment variables from .env file into PHP
 */

function loadEnvFile($file_path = null) {
    if ($file_path === null) {
        $file_path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($file_path)) {
        return false;
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos($line, '#') === 0) {
            continue;
        }
        
        // Skip lines without =
        if (strpos($line, '=') === false) {
            continue;
        }
        
        // Parse KEY=VALUE
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove quotes if present
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
            $value = substr($value, 1, -1);
        }
        
        // Set in $_ENV and putenv
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
    
    return true;
}

// Auto-load if included
if (file_exists(__DIR__ . '/../.env')) {
    loadEnvFile();
}
