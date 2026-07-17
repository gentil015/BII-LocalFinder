<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if portfolio_videos table exists
    $stmt = $db->query("SHOW TABLES LIKE 'portfolio_videos'");
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'portfolio_videos' already exists!";
    } else {
        echo "Creating portfolio_videos table...";
        
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
        echo "✓ Successfully created 'portfolio_videos' table!";
    }
    
} catch (PDOException $e) {
    echo "✗ Database Error: " . htmlspecialchars($e->getMessage());
    exit(1);
}
?>
