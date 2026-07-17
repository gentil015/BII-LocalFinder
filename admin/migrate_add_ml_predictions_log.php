<?php
// Simple migration to add an ML prediction log table.
// Run from CLI: php migrate_add_ml_predictions_log.php
// Or via browser: /admin/migrate_add_ml_predictions_log.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SHOW TABLES LIKE 'ml_predictions_log'");
    $stmt->execute();
    $exists = (bool) $stmt->fetch();

    if ($exists) {
        echo "Table ml_predictions_log already exists.\n";
        exit(0);
    }

    $db->exec("CREATE TABLE `ml_predictions_log` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `provider_id` int(11) NOT NULL,
      `predicted_score` decimal(8,6) NOT NULL,
      `actual_outcome` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user_id` (`user_id`),
      KEY `idx_provider_id` (`provider_id`),
      KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;");

    echo "Created table ml_predictions_log successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
