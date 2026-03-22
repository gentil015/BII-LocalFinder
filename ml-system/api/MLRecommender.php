<?php
/**
 * MLRecommender.php
 * -----------------
 * PHP client that calls the FastAPI ML prediction API to rank providers.
 *
 * Place this file at: /includes/MLRecommender.php
 *
 * Usage in providers.php:
 *
 *   require_once '../includes/MLRecommender.php';
 *   $recommender = new MLRecommender($db);
 *   $providers   = $recommender->rankProviders($providers);
 *   // $providers is now sorted by ML hire-probability DESC
 */

class MLRecommender
{
    /** Base URL of the FastAPI service */
    private string $apiBase;

    /** DB connection (PDO) — used to build features when not already in provider array */
    private PDO $db;

    /** Timeout for cURL requests in seconds */
    private int $timeout;

    /** Whether ML ranking is enabled (can disable via system_settings) */
    private bool $enabled;

    public function __construct(PDO $db, string $apiBase = 'http://localhost:8000', int $timeout = 3)
    {
        $this->db      = $db;
        $this->apiBase = rtrim($apiBase, '/');
        $this->timeout = $timeout;
        $this->enabled = $this->loadSetting('enable_ml_recommendations', '1') === '1';
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Re-ranks an array of provider rows by ML hire-probability (DESC).
     *
     * Each $providers element must have at least: id (or provider_id).
     * Returns the same array with an added ml_score float field.
     * Falls back to the original order if the ML API is unavailable.
     *
     * @param  array $providers  Raw provider rows from DB query
     * @param  int   $userId     User ID for personalized recommendations
     * @return array             Providers sorted best-first
     */
    public function rankProviders(array $providers, int $userId = null): array
    {
        if (!$this->enabled || empty($providers)) {
            return $providers;
        }

        // Build batch payload
        $items = [];
        foreach ($providers as $p) {
            $pid      = (int) ($p['id'] ?? $p['provider_id'] ?? 0);
            $features = $this->buildFeatures($p, $userId);
            $items[]  = ['provider_id' => $pid, 'features' => $features];
        }

        $results = $this->callBatchPredict($items);

        if (empty($results)) {
            // API unreachable — return original order with neutral score
            foreach ($providers as &$p) {
                $p['ml_score'] = 0.0;
            }
            return $providers;
        }

        // Build lookup: provider_id => probability
        $scoreMap = [];
        foreach ($results as $r) {
            $scoreMap[(int) $r['provider_id']] = (float) ($r['probability'] ?? 0.0);
        }

        // Attach scores to provider rows
        foreach ($providers as &$p) {
            $pid          = (int) ($p['id'] ?? $p['provider_id'] ?? 0);
            $p['ml_score'] = $scoreMap[$pid] ?? 0.0;
        }
        unset($p);

        // Sort descending by ML score
        usort($providers, fn($a, $b) => $b['ml_score'] <=> $a['ml_score']);

        return $providers;
    }

    /**
     * Predict hire probability for a single provider.
     * Returns a float between 0 and 1, or null on failure.
     */
    public function predictOne(array $providerRow, int $userId = null): ?float
    {
        if (!$this->enabled) {
            return null;
        }

        $features = $this->buildFeatures($providerRow, $userId);
        $response = $this->callSinglePredict($features);

        return $response ? (float) ($response['probability'] ?? 0.0) : null;
    }

    /**
     * Health check — returns true when the ML API is reachable.
     */
    public function isApiHealthy(): bool
    {
        $response = $this->curlGet('/health');
        return isset($response['status']) && $response['status'] === 'ok';
    }

    // ── Feature building ────────────────────────────────────────────────────

    /**
     * Builds a feature array for one provider.
     * Uses pre-computed columns if present in $row, otherwise queries the DB.
     */
    private function buildFeatures(array $row, int $userId = null): array
    {
        $pid = (int) ($row['id'] ?? $row['provider_id'] ?? 0);

        // ── Provider features ─────────────────────────────────────────────────
        $views = $row['views'] ?? $this->queryScalar(
            "SELECT COUNT(*) FROM provider_views WHERE provider_id = ?",
            [$pid]
        );

        $clicks = $row['clicks'] ?? $this->queryScalar(
            "SELECT COUNT(*) FROM click_logs WHERE target_type = 'provider' AND target_id = ?",
            [$pid]
        );

        $userId_provider = $row['user_id'] ?? $this->queryScalar(
            "SELECT user_id FROM service_providers WHERE id = ?",
            [$pid]
        );
        $messages = $row['messages'] ?? ($userId_provider
            ? $this->queryScalar("SELECT COUNT(*) FROM messages WHERE receiver_id = ?", [$userId_provider])
            : 0
        );

        $rating = $row['average_rating'] ?? 0.0;

        $price = $row['avg_service_price'] ?? $this->queryScalar(
            "SELECT AVG(price) FROM provider_services WHERE provider_id = ? AND is_available = 1",
            [$pid]
        );

        $avgResponse = $this->queryScalar(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at))
             FROM bookings
             WHERE provider_id = ? AND responded_at IS NOT NULL",
            [$pid]
        );
        if ($avgResponse === null) $avgResponse = 24.0;

        // ── User behavior features ───────────────────────────────────────────
        $userFeatures = ['user_avg_price' => 0.0, 'user_avg_response_time' => 24.0, 'user_total_bookings' => 0];
        if ($userId) {
            $userProfile = $this->queryRow(
                "SELECT user_avg_price, user_avg_response_time, user_total_bookings
                 FROM user_profiles WHERE user_id = ?",
                [$userId]
            );
            if ($userProfile) {
                $userFeatures = [
                    'user_avg_price' => (float) ($userProfile['user_avg_price'] ?? 0.0),
                    'user_avg_response_time' => (float) ($userProfile['user_avg_response_time'] ?? 24.0),
                    'user_total_bookings' => (int) ($userProfile['user_total_bookings'] ?? 0),
                ];
            }
        }

        return [
            'views'             => max(0, (float) $views),
            'clicks'            => max(0, (float) $clicks),
            'messages'          => max(0, (float) $messages),
            'rating'            => min(5.0, max(0.0, (float) $rating)),
            'price'             => max(0, (float) $price),
            'avg_response_time' => max(0, (float) $avgResponse),
            'user_avg_price'    => $userFeatures['user_avg_price'],
            'user_avg_response_time' => $userFeatures['user_avg_response_time'],
            'user_total_bookings' => $userFeatures['user_total_bookings'],
        ];
    }

    // ── HTTP helpers ────────────────────────────────────────────────────────

    /** POST /predict for a single provider. */
    private function callSinglePredict(array $features): ?array
    {
        return $this->curlPost('/predict', $features);
    }

    /** POST /predict/batch for multiple providers. */
    private function callBatchPredict(array $items): array
    {
        $result = $this->curlPost('/predict/batch', $items);
        return is_array($result) ? $result : [];
    }

    private function curlPost(string $path, array $payload): ?array
    {
        $url  = $this->apiBase . $path;
        $json = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || $body === false) {
            error_log("[MLRecommender] POST {$path} failed (errno={$errno})");
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function curlGet(string $path): ?array
    {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);
        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || $body === false) return null;

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    // ── DB helper ───────────────────────────────────────────────────────────

    private function queryScalar(string $sql, array $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : null;
        } catch (Throwable $e) {
            error_log("[MLRecommender] DB query failed: " . $e->getMessage());
            return null;
        }
    }

    private function queryRow(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log("[MLRecommender] DB query failed: " . $e->getMessage());
            return null;
        }
    }

    private function loadSetting(string $key, string $default = ''): string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1"
            );
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string) $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}