<?php
// Parse the file and check for errors
$content = file_get_contents('provider/languages/rw.php');

// Try to evaluate it
ob_start();
$result = @eval('return ' . substr($content, 5)); // Skip <?php
$output = ob_get_clean();

if ($result === false) {
    echo "Parse error when evaluating the file!\n";
    echo "Output: " . $output . "\n";
} else if (!is_array($result)) {
    echo "File doesn't return an array!\n";
} else {
    echo "File parses OK!\n";
    echo "Top keys: " . implode(", ", array_keys($result)) . "\n";
    
    if (isset($result['dashboard'])) {
        $keys = array_keys($result['dashboard']);
        echo "Dashboard has " . count($keys) . " keys\n";
        if (isset($result['dashboard']['settings'])) {
            $settings_keys = array_keys($result['dashboard']['settings']);
            echo "Settings has " . count($settings_keys) . " keys: ";
            echo implode(", ", array_slice($settings_keys, 0, 5)) . "...\n";
        }
    }
}
?>
