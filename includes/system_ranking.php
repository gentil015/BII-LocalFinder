<?php
/**
 * System Ranking Score Helpers
 *
 * This helper provides a simple automatic ranking score based on
 * provider performance and activity metrics.
 *
 * Usage:
 *   require_once __DIR__ . '/system_ranking.php';
 *   $score = calculate_system_score($providerRow);
 */

/**
 * Clamp a numeric score between min and max.
 *
 * @param int $value
 * @param int $min
 * @param int $max
 * @return int
 */
function system_ranking_clamp(int $value, int $min = 0, int $max = 100): int {
    return max($min, min($max, $value));
}

/**
 * Read a provider property from either an array or object.
 *
 * @param array|object $provider
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function system_ranking_get_source_value($provider, string $key, $default = null) {
    if (is_array($provider) && array_key_exists($key, $provider)) {
        return $provider[$key];
    }
    if (is_object($provider) && property_exists($provider, $key)) {
        return $provider->$key;
    }
    return $default;
}

/**
 * Calculate a provider's system ranking score (0-100).
 *
 * Rules:
 *   1. Availability: online adds +20
 *   2. Response time: faster adds more, using max(0, 30 - minutes)
 *   3. Completion rate: percentage (0-1) contributes up to +50
 *   4. Rating: normalized from 1-5 to 0-20
 *   5. Recent activity: active in last 24h adds +10
 *
 * @param array|object $provider
 * @return int
 */
function calculate_system_score($provider): int {
    $score = 0;

    // 1. Availability: online providers get a base boost.
    $isOnline = system_ranking_get_source_value($provider, 'is_online', false);
    if (!empty($isOnline) && $isOnline !== '0') {
        $score += 20;
    }

    // 2. Response time: lower minutes should increase the score.
    $responseMinutes = system_ranking_get_source_value($provider, 'avg_response_time_minutes', null);
    if ($responseMinutes === null) {
        $responseMinutes = system_ranking_get_source_value($provider, 'response_time_in_minutes', null);
    }
    if ($responseMinutes === null) {
        $responseMinutes = system_ranking_get_source_value($provider, 'avg_response_time', 0);
    }
    $responseMinutes = max(0, (int) $responseMinutes);
    $score += max(0, 30 - $responseMinutes);

    // 3. Completion rate: expected as 0-1. If stored as percentage, normalize it.
    $completionRate = (float) system_ranking_get_source_value($provider, 'completion_rate', 0.0);
    if ($completionRate > 1.0) {
        $completionRate = min(1.0, $completionRate / 100.0);
    }
    $score += $completionRate * 50;

    // 4. Rating: normalize 1-5 into 0-20.
    $rating = (float) system_ranking_get_source_value($provider, 'average_rating', 0.0);
    $rating = max(0.0, min(5.0, $rating));
    $score += ($rating / 5.0) * 20;

    // 5. Recent activity: active in the last 24 hours adds a bonus.
    $lastActive = system_ranking_get_source_value($provider, 'last_active', null);
    if ($lastActive === null) {
        $lastActive = system_ranking_get_source_value($provider, 'updated_at', null);
    }
    if ($lastActive !== null && strtotime($lastActive) >= strtotime('-24 hours')) {
        $score += 10;
    }

    return system_ranking_clamp((int) round($score), 0, 100);
}

/**
 * Check whether a table column exists.
 *
 * @param PDO $db
 * @param string $table
 * @param string $column
 * @return bool
 */
function system_ranking_has_column(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Update system ranking scores for all providers.
 *
 * If `system_ranking_score` exists, the score is persisted on each provider.
 * Otherwise the calculated scores are returned without updating the DB.
 *
 * @param PDO $db
 * @return array<int,int>  Mapping provider_id => computed score
 */
function update_system_scores_for_all_providers(PDO $db): array {
    $stmt = $db->query("SELECT id, is_online, avg_response_time_minutes, response_time_in_minutes, avg_response_time, completion_rate, average_rating, last_active, updated_at FROM service_providers");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $persist = system_ranking_has_column($db, 'service_providers', 'system_ranking_score');
    $updateStmt = null;

    if ($persist) {
        $updateStmt = $db->prepare("UPDATE service_providers SET system_ranking_score = ? WHERE id = ?");
    }

    $results = [];
    foreach ($providers as $provider) {
        $score = calculate_system_score($provider);
        if ($persist && $updateStmt) {
            $updateStmt->execute([$score, $provider['id']]);
        }
        $results[(int) $provider['id']] = $score;
    }

    return $results;
}

/**
 * Example usage:
 *
 * require_once __DIR__ . '/system_ranking.php';
 *
 * $provider = [
 *     'is_online' => 1,
 *     'avg_response_time_minutes' => 12,
 *     'completion_rate' => 0.88,
 *     'average_rating' => 4.6,
 *     'last_active' => '2026-04-01 10:15:00',
 * ];
 *
 * echo calculate_system_score($provider); // e.g. 86
 */
