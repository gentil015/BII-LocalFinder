<?php
// Fix apostrophes in language files - they need to be escaped in single-quoted strings

$files = [
    'provider/languages/rw.php',
    'languages/rw.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $lines = file($file);
        $output = array();
        
        foreach ($lines as $line) {
            // Skip lines that don't have array values
            if (strpos($line, '=>') === false) {
                $output[] = $line;
                continue;
            }
            
            // Look for 'key' => 'value' pattern
            if (preg_match("/^(\s*)'([^']+)'\s*=>\s*'([^']*)'([^',]*),?$/", $line, $matches)) {
                $indent = $matches[1];
                $key = $matches[2];
                $value = $matches[3];
                $rest = $matches[4];
                
                // Escape any single quotes in the value
                $value = str_replace("'", "\\'", $value);
                
                // Reconstruct the line
                $output[] = $indent . "'" . $key . "' => '" . $value . "'" . $rest . "\n";
            } else {
                $output[] = $line;
            }
        }
        
        file_put_contents($file, implode("", $output));
        echo "Fixed quotes in: $file\n";
    }
}
?>
