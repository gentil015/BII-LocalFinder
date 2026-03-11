<?php
// Simple migration to add missing verification columns to service_providers
// Run this from CLI: php migrate_add_verification_columns.php
// Or visit in browser: /admin/migrate_add_verification_columns.php (ensure protected access in production)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    // Check for verified_at
    $stmt = $db->prepare("SHOW COLUMNS FROM service_providers LIKE 'verified_at'");
    $stmt->execute();
    $has_verified_at = (bool) $stmt->fetch();

    if (!$has_verified_at) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN verified_at DATETIME DEFAULT NULL");
        echo "Added column: verified_at\n";
    } else {
        echo "Column verified_at already exists\n";
    }

    // Check for verification_notes
    $stmt = $db->prepare("SHOW COLUMNS FROM service_providers LIKE 'verification_notes'");
    $stmt->execute();
    $has_verification_notes = (bool) $stmt->fetch();

    if (!$has_verification_notes) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN verification_notes TEXT DEFAULT NULL");
        echo "Added column: verification_notes\n";
    } else {
        echo "Column verification_notes already exists\n";
    }

    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>