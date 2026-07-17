<?php
/**
 * Admin Ranking Score Helpers
 *
 * This module contains a simple manual ranking score calculator for providers.
 * It is intentionally separate from ML ranking so admins can influence visibility
 * with explicit flags and override values.
 *
 * Usage:
 *   require_once __DIR__ . '/admin_ranking.php';
 *   $score = calculate_admin_score($providerRow);
 */

/**
 * Clamp an integer between a minimum and maximum.
 *
 * @param int $value
 * @param int $min
 * @param int $max
 * @return int
 */
function clamp_int(int $value, int $min = 0, int $max = 100): int {
    return max($min, min($max, $value));
}

/**
 * Calculate a provider's admin ranking score.
 *
 * Rules:
 * 1. Featured provider adds +50
 * 2. Verified provider adds +30
 * 3. Promotion boost adds 0-20
 * 4. Priority level 0-3 multiplies to add 0, 10, 20, or 30
 * 5. Manual override uses fixed score if provided
 *
 * @param array $provider  Associative provider row / object-like array
 * @return int            Final score between 0 and 100
 */
function calculate_admin_score(array $provider): int {
    $override = $provider['admin_score_override'] ?? $provider['override_score'] ?? null;
    if ($override !== null && $override !== '') {
        return clamp_int((int) $override, 0, 100);
    }

    $score = 0;

    // Featured provider gets the largest visibility boost.
    if (!empty($provider['is_featured']) || (isset($provider['is_featured']) && $provider['is_featured'] === '1')) {
        $score += 50;
    }

    // Verified provider gets a strong trust signal.
    $isVerified = !empty($provider['is_verified'])
        || in_array(strtolower((string)($provider['verification_level'] ?? '')), ['verified', 'gold', 'premium'], true);
    if ($isVerified) {
        $score += 30;
    }

    // Admin promotion boost is capped to 20.
    $promotionBoost = clamp_int((int) ($provider['admin_promotion_boost'] ?? $provider['promotion_boost'] ?? 0), 0, 20);
    $score += $promotionBoost;

    // Priority level 0-3 adds 10 points per level.
    $priorityLevel = clamp_int((int) ($provider['admin_priority_level'] ?? $provider['priority_level'] ?? 0), 0, 3);
    $score += $priorityLevel * 10;

    return clamp_int($score, 0, 100);
}

/**
 * Check if the given table contains a column.
 *
 * @param PDO $db
 * @param string $table
 * @param string $column
 * @return bool
 */
function admin_ranking_table_has_column(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Update the computed admin ranking score for all providers.
 *
 * If the `admin_ranking_score` column exists, this will persist the score.
 * Otherwise it returns the computed values without writing.
 *
 * @param PDO $db
 * @return array<int,int>  Provider ID => calculated score
 */
function update_admin_scores_for_all_providers(PDO $db): array {
    $sql = "SELECT id, is_featured, is_verified, verification_level, admin_promotion_boost, promotion_boost, admin_priority_level, priority_level, admin_score_override, override_score FROM service_providers";
    $stmt = $db->query($sql);
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $persist = admin_ranking_table_has_column($db, 'service_providers', 'admin_ranking_score');
    $updateStmt = null;

    if ($persist) {
        $updateStmt = $db->prepare("UPDATE service_providers SET admin_ranking_score = ? WHERE id = ?");
    }

    $results = [];
    foreach ($providers as $provider) {
        $score = calculate_admin_score($provider);
        if ($persist && $updateStmt) {
            $updateStmt->execute([$score, $provider['id']]);
        }
        $results[(int) $provider['id']] = $score;
    }

    return $results;
}

/**
 * Example usage.
 *
 * $provider = [
 *     'is_featured' => 1,
 *     'is_verified' => 1,
 *     'admin_promotion_boost' => 12,
 *     'admin_priority_level' => 2,
 *     'admin_score_override' => null,
 * ];
 *
 * echo calculate_admin_score($provider); // 100
 */
