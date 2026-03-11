<?php
// Simple check - can we include the file?
try {
    $result = @include 'provider/languages/rw.php';
    echo "File included successfully\n";
    if (is_array($result)) {
        echo "Returns an array with " . count($result) . " top-level keys\n";
        if (isset($result['dashboard'])) {
            echo "Has 'dashboard' key\n";
            if (isset($result['dashboard']['settings'])) {
                echo "Dashboard has 'settings' key with " . count($result['dashboard']['settings']) . " items\n";
            } else {
                echo "ERROR: Dashboard does NOT have 'settings' key\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
