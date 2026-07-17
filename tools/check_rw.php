<?php
$path = __DIR__ . '/../provider/languages/rw.php';
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}
$translations = include $path;
if (!is_array($translations)) {
    echo "Include did not return an array\n";
    var_dump($translations);
    exit(1);
}
if (!isset($translations['dashboard'])) {
    echo "No 'dashboard' key in translations\n";
    var_dump(array_keys($translations));
    exit(1);
}
if (!isset($translations['dashboard']['settings'])) {
    echo "No dashboard.settings key found\n";
    var_dump(array_keys($translations['dashboard']));
    exit(1);
}
$keys = array_keys($translations['dashboard']['settings']);
echo "Found " . count($keys) . " keys in dashboard.settings\n";
foreach ($keys as $k) { echo $k . "\n"; }
