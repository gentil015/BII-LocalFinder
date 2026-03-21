<?php
/**
 * Cron Job Script for Automated Provider Performance Updates
 *
 * This script should be run daily to update provider performance metrics
 * Recommended cron schedule: 0 2 * * * (daily at 2 AM)
 *
 * Usage: php cron_update_provider_performance.php
 */

require_once 'config/database.php';
require_once 'includes/provider_performance.php';

echo "Starting automated provider performance update...\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    $performanceManager = new ProviderPerformanceManager();

    // Get all active providers
    $stmt = $db->prepare("
        SELECT sp.id, sp.user_id, u.full_name
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.is_active = 1
        ORDER BY sp.id
    ");
    $stmt->execute();
    $providers = $stmt->fetchAll();

    echo "Found " . count($providers) . " active providers to update.\n\n";

    $updated = 0;
    $errors = 0;

    foreach ($providers as $provider) {
        try {
            echo "Updating performance for provider: {$provider['full_name']} (ID: {$provider['id']})... ";

            // Update performance for the last 30 days
            $metrics = $performanceManager->updateProviderPerformance($provider['id']);

            echo "✓ Score: " . number_format($metrics['overall_performance_score'], 1) . "/100, Grade: " . ucfirst($metrics['performance_grade']) . "\n";

            $updated++;

        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "\nUpdate Summary:\n";
    echo "- Providers updated: $updated\n";
    echo "- Errors encountered: $errors\n";
    echo "- Total processed: " . count($providers) . "\n";

    // Clean up old performance records (keep last 90 days)
    echo "\nCleaning up old performance records... ";
    $stmt = $db->prepare("
        DELETE FROM provider_performance
        WHERE period_end < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ");
    $deleted = $stmt->execute();
    echo "✓ Deleted $deleted old records.\n";

    echo "\nAutomated provider performance update completed successfully!\n";

} catch (Exception $e) {
    echo "Critical error during performance update: " . $e->getMessage() . "\n";
    exit(1);
}
?>