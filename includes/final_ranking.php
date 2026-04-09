<?php
/**
 * Final Ranking Engine
 *
 * This module combines ML, system and admin scores into a single final score.
 * It is intentionally kept separate from ML and system helpers so it can be
 * reused across search, recommendation, and ranking APIs.
 */

/**
 * Normalize a raw score into a 0-100 range.
 * If the value is between 0 and 1, treat it as a percentage and scale to 100.
 *
 * @param mixed $value
 * @return float
 */
function normalize_score($value): float {
    if ($value === null || $value === '') {
        return 0.0;
    }

    if (is_bool($value)) {
        return $value ? 100.0 : 0.0;
    }

    if (is_string($value) && !is_numeric($value)) {
        return 0.0;
    }

    $score = (float) $value;

    if ($score >= 0.0 && $score <= 1.0) {
        $score *= 100.0;
    }

    return max(0.0, min(100.0, $score));
}

/**
 * Normalize weight values so they sum to 1.
 *
 * @param array|null $weights
 * @return array
 */
function normalize_weights(?array $weights): array {
    $defaults = [
        'ml' => 0.5,
        'system' => 0.3,
        'admin' => 0.2,
    ];

    if (empty($weights)) {
        return $defaults;
    }

    $normalized = [];
    foreach ($defaults as $key => $defaultValue) {
        $normalized[$key] = isset($weights[$key]) ? (float) $weights[$key] : $defaultValue;
    }

    $sum = array_sum($normalized);
    if ($sum <= 0.0) {
        return $defaults;
    }

    foreach ($normalized as $key => $value) {
        $normalized[$key] = $value / $sum;
    }

    return $normalized;
}

/**
 * Get a provider field from array or object sources.
 *
 * @param array|object $provider
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function final_ranking_get_value($provider, string $key, $default = null) {
    if (is_array($provider) && array_key_exists($key, $provider)) {
        return $provider[$key];
    }

    if (is_object($provider)) {
        if (property_exists($provider, $key)) {
            return $provider->$key;
        }
        if ($provider instanceof ArrayAccess && isset($provider[$key])) {
            return $provider[$key];
        }
    }

    return $default;
}

/**
 * Get the first non-null provider field from a list of keys.
 *
 * @param array|object $provider
 * @param array $keys
 * @param mixed $default
 * @return mixed
 */
function final_ranking_get_first_available($provider, array $keys, $default = 0) {
    foreach ($keys as $key) {
        $value = final_ranking_get_value($provider, $key, null);
        if ($value !== null) {
            return $value;
        }
    }
    return $default;
}

/**
 * Calculate the final ranking score for a provider.
 *
 * @param array|object $provider
 * @param array|null $weights
 * @param bool $debug
 * @return array Updated provider row with final_score and optional debug info
 */
function calculate_final_score($provider, ?array $weights = null, bool $debug = false): array {
    $weights = normalize_weights($weights);

    $mlScore = normalize_score(final_ranking_get_first_available($provider, ['ml_score']));
    $systemScore = normalize_score(final_ranking_get_first_available($provider, ['system_score', 'system_ranking_score']));
    $adminScore = normalize_score(final_ranking_get_first_available($provider, ['admin_score', 'admin_ranking_score']));

    $finalScore = ($mlScore * $weights['ml'])
                + ($systemScore * $weights['system'])
                + ($adminScore * $weights['admin']);

    $finalScore = round(max(0.0, min(100.0, $finalScore)), 2);

    $result = [];
    if (is_array($provider)) {
        $result = $provider;
    } elseif (is_object($provider)) {
        $result = get_object_vars($provider);
    }

    $result['final_score'] = $finalScore;

    if ($debug) {
        $result['_final_ranking_debug'] = [
            'weights' => $weights,
            'ml_score' => $mlScore,
            'system_score' => $systemScore,
            'admin_score' => $adminScore,
            'final_score_raw' => $finalScore,
        ];
    }

    return $result;
}

/**
 * Sort providers by final_score descending.
 * Ensures each provider has a calculated final_score.
 *
 * @param array $providers
 * @param array|null $weights
 * @return array
 */
function sort_providers_by_final_score(array $providers, ?array $weights = null): array {
    foreach ($providers as $index => $provider) {
        $providers[$index] = calculate_final_score($provider, $weights, false);
    }

    usort($providers, function ($a, $b) {
        $aScore = (float) ($a['final_score'] ?? 0);
        $bScore = (float) ($b['final_score'] ?? 0);
        return $bScore <=> $aScore;
    });

    return $providers;
}

/**
 * Check whether the provider table has a final_score column.
 *
 * @param PDO $db
 * @param string $table
 * @param string $column
 * @return bool
 */
function final_ranking_has_column(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Update final_score for all providers in the database.
 * If the final_score column exists, values are persisted.
 * Otherwise this only returns computed values.
 *
 * @param PDO $db
 * @param array|null $weights
 * @return array<int,float> Mapping provider_id => final_score
 */
function update_all_provider_scores(PDO $db, ?array $weights = null): array {
    $columnStmt = $db->query("SHOW COLUMNS FROM service_providers");
    $existingColumns = $columnStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $selectFields = ['id'];
    if (in_array('ml_score', $existingColumns, true)) {
        $selectFields[] = 'ml_score';
    }
    if (in_array('system_score', $existingColumns, true)) {
        $selectFields[] = 'system_score';
    }
    if (in_array('system_ranking_score', $existingColumns, true)) {
        $selectFields[] = 'system_ranking_score';
    }
    if (in_array('admin_score', $existingColumns, true)) {
        $selectFields[] = 'admin_score';
    }
    if (in_array('admin_ranking_score', $existingColumns, true)) {
        $selectFields[] = 'admin_ranking_score';
    }

    $stmt = $db->query("SELECT " . implode(', ', $selectFields) . " FROM service_providers");
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $persist = final_ranking_has_column($db, 'service_providers', 'final_score');
    $updateStmt = null;
    if ($persist) {
        $updateStmt = $db->prepare("UPDATE service_providers SET final_score = ? WHERE id = ?");
    }

    $results = [];
    foreach ($providers as $provider) {
        $updated = calculate_final_score($provider, $weights, false);
        $score = (float) $updated['final_score'];
        if ($persist && $updateStmt) {
            $updateStmt->execute([$score, $provider['id']]);
        }
        $results[(int) $provider['id']] = $score;
    }

    return $results;
}

/**
 * Example usage.
 */
/*
$sampleProvider = [
    'ml_score' => 87,
    'system_score' => 76,
    'admin_score' => 45,
];
$result = calculate_final_score($sampleProvider);
echo $result['final_score']; // 75.1
*/
