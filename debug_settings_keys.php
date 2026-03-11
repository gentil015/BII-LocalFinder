<?php
// Check exact structure in settings array

$translations = include 'provider/languages/rw.php';

$settings = $translations['dashboard']['settings'];

echo "All keys in settings:\n";
foreach ($settings as $key => $value) {
    if (is_array($value)) {
        echo "  [$key] => Array (" . count($value) . " items)\n";
    } else {
        echo "  '$key' => '" . substr($value, 0, 50) . "...'\n";
    }
}
?>
