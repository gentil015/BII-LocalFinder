<?php
/**
 * Migration: Add portfolio_videos table
 * This script creates the portfolio_videos table to support video uploads in provider portfolios
 */

require_once 'database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table already exists
    $stmt = $db->query("SHOW TABLES LIKE 'portfolio_videos'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'portfolio_videos' already exists.\n";
        exit(0);
    }
    
    // Create portfolio_videos table
    $sql = "
    CREATE TABLE `portfolio_videos` (
      `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `provider_id` int(11) NOT NULL,
      `video_path` varchar(255) NOT NULL,
      `title` varchar(100) DEFAULT NULL,
      `description` text DEFAULT NULL,
      `display_order` int(11) DEFAULT 0,
      `is_active` tinyint(1) DEFAULT 1,
      `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `provider_id` (`provider_id`),
      CONSTRAINT `fk_portfolio_videos_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $db->exec($sql);
    
    echo "✓ Successfully created 'portfolio_videos' table!\n";
    echo "✓ Migration completed.\n";
    
} catch (PDOException $e) {
    echo "✗ Error creating portfolio_videos table: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
