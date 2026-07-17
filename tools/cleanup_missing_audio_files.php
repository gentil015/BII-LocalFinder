<?php
/**
 * Cleanup script to remove database records for missing audio files
 * 
 * This script finds all messages with audio files that no longer exist on disk
 * and either deletes them or just reports them.
 * 
 * Usage: php cleanup_missing_audio_files.php [--delete] [--dry-run]
 *   --delete: Actually delete orphaned records (default: just report)
 *   --dry-run: Show what would be deleted without making changes
 */

require_once __DIR__ . '/../config/database.php';

$dryRun = in_array('--dry-run', $argv);
$delete = in_array('--delete', $argv);

if ($dryRun) {
    $delete = false; // dry-run implies report only
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Find all audio messages with file_path
    $stmt = $db->query("SELECT id, file_path, sender_id, receiver_id, created_at FROM messages WHERE message_type = 'audio' AND file_path IS NOT NULL AND file_path != ''");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $orphaned = 0;
    $valid = 0;
    $errors = [];
    
    echo "Checking " . count($messages) . " audio messages...\n\n";
    
    foreach ($messages as $msg) {
        $filePath = __DIR__ . '/../' . $msg['file_path'];
        
        if (!file_exists($filePath) || !is_file($filePath)) {
            $orphaned++;
            echo "❌ MISSING: ID {$msg['id']} | File: {$msg['file_path']} | Date: {$msg['created_at']}\n";
            
            if ($delete && !$dryRun) {
                try {
                    $deleteStmt = $db->prepare("DELETE FROM messages WHERE id = ?");
                    $deleteStmt->execute([$msg['id']]);
                    echo "   → Deleted from database\n";
                } catch (Exception $e) {
                    $errors[] = "Failed to delete message ID {$msg['id']}: " . $e->getMessage();
                }
            } elseif ($delete && $dryRun) {
                echo "   → Would delete from database (dry-run mode)\n";
            }
        } else {
            $valid++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Valid files:    $valid\n";
    echo "Orphaned files: $orphaned\n";
    
    if ($dryRun) {
        echo "\n⚠️  DRY RUN MODE - No changes made\n";
        echo "Run with --delete flag to actually remove orphaned records\n";
    }
    
    if (!empty($errors)) {
        echo "\n=== Errors ===\n";
        foreach ($errors as $error) {
            echo "⚠️  $error\n";
        }
    }
    
    if ($orphaned > 0 && !$delete && !$dryRun) {
        echo "\nRun with --delete flag to remove these orphaned records\n";
        echo "Or with --dry-run to see what would be deleted first\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
