<?php
/**
 * test_ml_system.php
 * ------------------
 * Simple test script to verify the ML prediction system works correctly.
 *
 * Run with: php test_ml_system.php
 */

require_once '../config/database.php';
require_once '../includes/MLRecommender.php';

echo "Testing ML Prediction System\n";
echo "============================\n\n";

try {
    $db = Database::getInstance()->getConnection();
    $recommender = new MLRecommender($db, 'http://localhost:8000');

    // Test API health
    echo "1. Testing API Health...\n";
    $healthy = $recommender->isApiHealthy();
    echo "   API Health: " . ($healthy ? "✓ Online" : "✗ Offline") . "\n\n";

    if (!$healthy) {
        echo "   Note: API is offline, but fallback ranking will still work.\n\n";
    }

    // Test with sample provider data
    echo "2. Testing Provider Ranking...\n";

    // Sample provider data (simplified)
    $providers = [
        [
            'id' => 1,
            'provider_id' => 1,
            'user_id' => 1,
            'profession' => 'Plumber',
            'average_rating' => 4.5,
            'total_reviews' => 25,
            'full_name' => 'John Doe',
        ],
        [
            'id' => 2,
            'provider_id' => 2,
            'user_id' => 2,
            'profession' => 'Electrician',
            'average_rating' => 3.8,
            'total_reviews' => 15,
            'full_name' => 'Jane Smith',
        ],
        [
            'id' => 3,
            'provider_id' => 3,
            'user_id' => 3,
            'profession' => 'Carpenter',
            'average_rating' => 4.9,
            'total_reviews' => 40,
            'full_name' => 'Bob Johnson',
        ],
    ];

    echo "   Original providers:\n";
    foreach ($providers as $p) {
        echo "     - {$p['full_name']} ({$p['profession']}): {$p['average_rating']}★\n";
    }
    echo "\n";

    // Rank providers for a specific user. Set user_id to a real user in your database for personalized results.
    $rankedProviders = $recommender->rankProviders($providers, 1);

    echo "   Ranked providers:\n";
    foreach ($rankedProviders as $i => $p) {
        $score = isset($p['ml_score']) ? number_format($p['ml_score'], 3) : 'N/A';
        echo "     " . ($i + 1) . ". {$p['full_name']} ({$p['profession']}): Score {$score}\n";
    }

    echo "\n3. Test completed successfully! ✓\n";

} catch (Exception $e) {
    echo "   ✗ Test failed: " . $e->getMessage() . "\n";
}

echo "\nTest script completed.\n";