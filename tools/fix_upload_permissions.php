<?php
/**
 * Fix file permissions for uploads directory
 * 
 * This script checks and fixes permissions for all files and directories
 * in the uploads folder to ensure proper access.
 * 
 * Usage: php fix_upload_permissions.php [--fix]
 *   --fix: Actually apply fixes (default: just report)
 */

$fix = in_array('--fix', $argv);

$uploadsDir = __DIR__ . '/../uploads';

if (!is_dir($uploadsDir)) {
    echo "Error: Uploads directory not found at $uploadsDir\n";
    exit(1);
}

echo "Checking permissions in: $uploadsDir\n\n";

$issues = [];
$totalFiles = 0;
$totalDirs = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $path => $item) {
    if ($item->isDir()) {
        $totalDirs++;
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        
        // Directories should be readable and executable (755)
        if ($perms != '0755' && $perms != '755') {
            $issues[] = [
                'path' => $path,
                'type' => 'dir',
                'current' => $perms,
                'expected' => '0755'
            ];
            
            if ($fix) {
                chmod($path, 0755);
            }
        }
    } else {
        $totalFiles++;
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        
        // Files should be readable (644)
        if ($perms != '0644' && $perms != '644') {
            $issues[] = [
                'path' => $path,
                'type' => 'file',
                'current' => $perms,
                'expected' => '0644'
            ];
            
            if ($fix) {
                chmod($path, 0644);
            }
        }
    }
}

echo "Scan Results:\n";
echo "- Directories checked: $totalDirs\n";
echo "- Files checked: $totalFiles\n";
echo "- Issues found: " . count($issues) . "\n\n";

if (!empty($issues)) {
    echo "=== Permission Issues ===\n";
    foreach ($issues as $issue) {
        $type = $issue['type'] === 'dir' ? '📁' : '📄';
        echo "$type {$issue['path']}\n";
        echo "   Current: {$issue['current']} | Expected: {$issue['expected']}\n";
    }
    
    if ($fix) {
        echo "\n✅ Permissions have been fixed!\n";
    } else {
        echo "\nRun with --fix flag to apply these changes\n";
    }
} else {
    echo "✅ All permissions are correct!\n";
}
?>
