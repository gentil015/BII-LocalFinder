<?php
// Read the original file, fix all quote issues comprehensively

$file = 'provider/languages/rw.php';
$content = file_get_contents($file);

// Step 1: Remove all double backslashes
$content = str_replace("\\\\", "\\", $content);

// Step 2: Replace Unicode curly quotes with straight apostrophes
$content = str_replace("\xE2\x80\x99", "'", $content); // Right single quotation mark
$content = str_replace("\xE2\x80\x98", "'", $content); // Left single quotation mark

// Step 3: Now we need to escape all unescaped quotes inside string values
// Split by lines
$lines = explode("\n", $content);
$output = array();

foreach ($lines as $idx => $line) {
    // If line doesn't have =>, just keep as-is
    if (strpos($line, '=>') === false) {
        $output[] = $line;
        continue;
    }
    
    // Match the pattern: 'key' => 'value',
    if (preg_match_all("/^(.*)'([^']+)'\s*=>\s*'([^']*)'(.*)$/", $line, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $before = $match[1];
            $key = $match[2];
            $value = $match[3];
            $after = $match[4];
            
            // Only escape quotes in the value if not already escaped
            $value_escaped = '';
            for ($i = 0; $i < strlen($value); $i++) {
                if ($value[$i] == "'" && ($i == 0 || $value[$i-1] != "\\")) {
                    $value_escaped .= "\\'";
                } else {
                    $value_escaped .= $value[$i];
                }
            }
            
            $line = $before . "'" . $key . "' => '" . $value_escaped . "'" . $after;
        }
    }
    
    $output[] = $line;
}

$result = implode("\n", $output);
file_put_contents($file, $result);
echo "Fixed provider/languages/rw.php\n";
?>
