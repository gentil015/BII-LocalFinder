<?php
// Check for lines with multiple => on same line
$content = file_get_contents('provider/languages/rw.php');
$lines = explode("\n", $content);
for ($i = 0; $i < count($lines); $i++) {
    // Look for lines with multiple '=>' on same line
    $count = substr_count($lines[$i], '=>');
    if ($count > 1) {
        echo "Line " . ($i+1) . ": Multiple => found\n";
        echo "  " . substr($lines[$i], 0, 150) . "...\n\n";
    }
}
?>
