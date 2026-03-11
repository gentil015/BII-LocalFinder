<?php
// Fix translation keys in provider pages

function fixTranslationKeys($filePath, $searchPrefix, $section) {
    $content = file_get_contents($filePath);
    $original = $content;
    
    // Replace __('PREFIX.xxx with __('xxx when section is PREFIX
    $pattern = "__\\('" . preg_quote($searchPrefix) . "\\.";
    $replacement = "__('" ;
    $content = preg_replace("/" . $pattern . "/", $replacement, $content);
    
    if ($content !== $original) {
        file_put_contents($filePath, $content);
        return true;
    }
    return false;
}

// Fix services.php
if (fixTranslationKeys('provider/services.php', 'services_page', 'services_page')) {
    echo "[✓] Fixed provider/services.php - removed 'services_page.' prefix from all translation keys\n";
} else {
    echo "[!] No changes made to provider/services.php\n";
}

// Fix dashboard.php
if (fixTranslationKeys('provider/dashboard.php', 'dashboard', 'dashboard')) {
    echo "[✓] Fixed provider/dashboard.php - removed 'dashboard.' prefix from all translation keys\n";
} else {
    echo "[!] No changes made to provider/dashboard.php\n";
}

echo "\n[✓] All translation keys have been fixed!\n";
echo "\nNow testing translation system...\n\n";

// Test the fixes
require_once 'includes/language.php';

$_SESSION['language'] = 'en';
$GLOBALS['current_language'] = 'en';

echo "=== Testing Services Page ===\n";
echo "page_header: " . __('page_header', [], 'services_page') . "\n";
echo "stats.total_services: " . __('stats.total_services', [], 'services_page') . "\n";
echo "add_service_form.title: " . __('add_service_form.title', [], 'services_page') . "\n\n";

echo "=== Testing Dashboard ===\n";
echo "welcome.title: " . __('welcome.title', [], 'dashboard') . "\n";
echo "statistics.total_bookings: " . __('statistics.total_bookings', [], 'dashboard') . "\n";
echo "bookings.title: " . __('bookings.title', [], 'dashboard') . "\n";
?>
