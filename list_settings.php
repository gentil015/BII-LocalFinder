<?php
$translations = include 'provider/languages/rw.php';
$settings = $translations['dashboard']['settings'];

echo "Settings keys (" . count($settings) . " total):\n";
foreach (array_keys($settings) as $key) {
    echo "  - $key\n";
}
?>
