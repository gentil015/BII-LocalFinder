<?php
/**
 * MultiModelRecommender.php
 * -------------------------
 * PHP client that calls the FastAPI multi-model prediction API.
 *
 * Supports multiple specialized models:
 * - Recommendation Model: Predicts hiring probability
 * - Search Ranking Model: Predicts search relevance scores
 * - Personalization Model: Predicts user preferences
 * - Provider Performance Model: Predicts provider performance metrics
 * - User Engagement Model: Predicts user engagement likelihood
 *
 * Place this file at: /includes/MultiModelRecommender.php
 *
 * Usage:
 *
 *   require_once '../includes/MultiModelRecommender.php';
 *   $recommender = new MultiModelRecommender($db);
 *
 *   // Recommendation ranking
 *   $providers = $recommender->rankByRecommendation($providers, $userId);
 *
 *   // Search ranking
 *   $providers = $recommender->rankBySearchRelevance($providers, $searchQuery, $userId);
 *
 *   // Personalization
 *   $preferences = $recommender->getPersonalizedPreferences($userId, $providers);
 */

class MultiModelRecommender
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
     * Re-ranks providers by recommendation score (hiring probability).
     *
     * @param  array $providers  Raw provider rows from DB query
     * @param  int   $userId     User ID for personalized recommendations
     * @return array             Providers sorted by recommendation score DESC
     */
    public function rankByRecommendation(array $providers, int $userId = null): array
    {
        return $this->rankProviders($providers, $userId, 'recommendation');
    }

    /**
     * Re-ranks providers by search relevance score.
     *
     * @param  array  $providers   Raw provider rows from DB query
     * @param  string $searchQuery The search query string
     * @param  int    $userId      User ID for personalized search
     * @param  array  $searchFilters Additional search filters (category, location, price, etc.)
     * @return array               Providers sorted by search relevance DESC
     */
    public function rankBySearchRelevance(array $providers, string $searchQuery = '', int $userId = null, array $searchFilters = []): array
    {
        if (!$this->enabled || empty($providers)) {
            return $providers;
        }

        // Build batch payload for search ranking
        $items = [];
        foreach ($providers as $p) {
            $features = $this->buildSearchRankingFeatures($p, $searchQuery, $userId, $searchFilters);
            $items[] = $features;
        }

        $results = $this->callBatchPredict('search_ranking', $items);

        if (empty($results)) {
            // API unreachable — return original order with neutral score
            foreach ($providers as &$p) {
                $p['search_relevance_score'] = 50.0; // Neutral score
            }
            return $providers;
        }

        // Attach scores to provider rows
        foreach ($providers as $index => &$p) {
            $prediction = $results[$index]['prediction'] ?? 50.0;
            $p['search_relevance_score'] = (float) $prediction;
        }
        unset($p);

        // Sort descending by search relevance score
        usort($providers, fn($a, $b) => $b['search_relevance_score'] <=> $a['search_relevance_score']);

        return $providers;
    }

    /**
     * Get personalized preferences for a user across multiple providers.
     *
     * @param  int   $userId    User ID
     * @param  array $providers Array of provider data
     * @return array            Array of preference scores (0-1) indexed by provider ID
     */
    public function getPersonalizedPreferences(int $userId, array $providers): array
    {
        if (!$this->enabled || empty($providers)) {
            return array_fill_keys(array_column($providers, 'id'), 0.5);
        }

        $items = [];
        foreach ($providers as $p) {
            $features = $this->buildPersonalizationFeatures($p, $userId);
            $items[] = $features;
        }

        $results = $this->callBatchPredict('personalization', $items);

        if (empty($results)) {
            return array_fill_keys(array_column($providers, 'id'), 0.5);
        }

        $preferences = [];
        foreach ($providers as $index => $p) {
            $pid = $p['id'] ?? $p['provider_id'] ?? 0;
            $prediction = $results[$index]['prediction'] ?? 0.5;
            $preferences[$pid] = (float) $prediction;
        }

        return $preferences;
    }

    /**
     * Predict provider performance metrics.
     *
     * @param  array $providerRow Provider data
     * @return array|null         Performance predictions or null on failure
     */
    public function predictProviderPerformance(array $providerRow): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $features = $this->buildProviderPerformanceFeatures($providerRow);
        $response = $this->callSinglePredict('provider_performance', $features);

        return $response;
    }

    /**
     * Predict user engagement likelihood.
     *
     * @param  int   $userId      User ID
     * @param  array $providerRow Provider data
     * @param  array $context     Additional context (time, session info, etc.)
     * @return float|null         Engagement probability (0-1) or null on failure
     */
    public function predictUserEngagement(int $userId, array $providerRow, array $context = []): ?float
    {
        if (!$this->enabled) {
            return null;
        }

        $features = $this->buildUserEngagementFeatures($userId, $providerRow, $context);
        $response = $this->callSinglePredict('user_engagement', $features);

        return $response ? (float) ($response['prediction'] ?? 0.0) : null;
    }

    /**
     * Predict user segment based on user behavior and characteristics.
     *
     * @param  int   $userId User ID
     * @return array|null    Segment prediction with segment name and ID, or null on failure
     */
    public function predictUserSegment(int $userId): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $features = $this->buildUserSegmentationFeatures($userId);
        $response = $this->callSinglePredict('user_segmentation', $features);

        if (!$response) {
            return null;
        }

        return [
            'segment_id' => (int) ($response['prediction'] ?? 0),
            'segment_name' => $response['segment_name'] ?? 'Unknown',
            'confidence' => $response['probabilities'] ?? null,
        ];
    }

    /**
     * Generic provider ranking method.
     *
     * @param  array  $providers Raw provider rows from DB query
     * @param  int    $userId    User ID for personalized recommendations
     * @param  string $modelType Model type ('recommendation', 'search_ranking', etc.)
     * @return array             Providers sorted by model score DESC
     */
    private function rankProviders(array $providers, int $userId = null, string $modelType = 'recommendation'): array
    {
        if (!$this->enabled || empty($providers)) {
            return $providers;
        }

        // Build batch payload
        $items = [];
        foreach ($providers as $p) {
            if ($modelType === 'recommendation') {
                $features = $this->buildRecommendationFeatures($p, $userId);
            } elseif ($modelType === 'search_ranking') {
                $features = $this->buildSearchRankingFeatures($p, '', $userId);
            } else {
                // Default to recommendation features
                $features = $this->buildRecommendationFeatures($p, $userId);
            }
            $items[] = $features;
        }

        $results = $this->callBatchPredict($modelType, $items);

        if (empty($results)) {
            // API unreachable — return original order with neutral score
            $scoreField = $modelType === 'recommendation' ? 'ml_score' : 'relevance_score';
            foreach ($providers as &$p) {
                $p[$scoreField] = 0.0;
            }
            return $providers;
        }

        // Attach scores to provider rows
        $scoreField = $modelType === 'recommendation' ? 'ml_score' : 'relevance_score';
        foreach ($providers as $index => &$p) {
            $prediction = $results[$index]['prediction'] ?? 0.0;
            $p[$scoreField] = (float) $prediction;
        }
        unset($p);

        // Sort descending by score
        usort($providers, fn($a, $b) => $b[$scoreField] <=> $a[$scoreField]);

        return $providers;
    }

    /**
     * Health check — returns true when the ML API is reachable.
     */
    public function isApiHealthy(): bool
    {
        $response = $this->curlGet('/health');
        return isset($response['status']) && in_array($response['status'], ['healthy', 'degraded']);
    }

    /**
     * Get information about available models.
     */
    public function getAvailableModels(): array
    {
        $response = $this->curlGet('/models/info');
        return $response ?: [];
    }

    // ── Feature Building Methods ────────────────────────────────────────────

    /**
     * Builds recommendation features for one provider (same as original MLRecommender).
     */
    private function buildRecommendationFeatures(array $row, int $userId = null): array
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
            } else {
                $userStats = $this->queryRow(
                    "SELECT AVG(amount) AS avg_price,
                            AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) AS avg_response_time,
                            COUNT(*) AS total_bookings
                     FROM bookings
                     WHERE client_id = ?",
                    [$userId]
                );
                if ($userStats) {
                    $userFeatures = [
                        'user_avg_price' => (float) ($userStats['avg_price'] ?? 0.0),
                        'user_avg_response_time' => (float) ($userStats['avg_response_time'] ?? 24.0),
                        'user_total_bookings' => (int) ($userStats['total_bookings'] ?? 0),
                    ];
                }
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

    /**
     * Builds search ranking features for one provider.
     */
    private function buildSearchRankingFeatures(array $row, string $searchQuery = '', int $userId = null, array $searchFilters = []): array
    {
        $pid = (int) ($row['id'] ?? $row['provider_id'] ?? 0);

        // Provider features
        $views = $row['views'] ?? $this->queryScalar("SELECT COUNT(*) FROM provider_views WHERE provider_id = ?", [$pid]);
        $clicks = $row['clicks'] ?? $this->queryScalar("SELECT COUNT(*) FROM click_logs WHERE target_type = 'provider' AND target_id = ?", [$pid]);
        $messages = $row['messages'] ?? $this->queryScalar("SELECT COUNT(*) FROM messages WHERE receiver_id = (SELECT user_id FROM service_providers WHERE id = ?)", [$pid]);
        $rating = $row['average_rating'] ?? 0.0;
        $price = $row['price'] ?? $this->queryScalar("SELECT AVG(price) FROM provider_services WHERE provider_id = ? AND is_available = 1", [$pid]);
        $avgResponseTime = $row['avg_response_time'] ?? 60.0;
        $isVerified = $row['is_verified'] ?? 0;
        $isFeatured = $row['is_featured'] ?? 0;
        $experienceYears = $row['experience_years'] ?? 0;
        $completionRate = $row['completion_rate'] ?? 0.0;

        // Search context features
        $searchQueryLength = strlen($searchQuery);

        // Category match
        $categoryMatch = 0;
        if (!empty($searchFilters['category'])) {
            $providerCategories = $row['categories'] ?? '';
            $categoryMatch = strpos(strtolower($providerCategories), strtolower($searchFilters['category'])) !== false ? 1 : 0;
        }

        // Location match
        $locationMatch = 0;
        if (!empty($searchFilters['location'])) {
            $providerLocation = $row['location'] ?? '';
            $locationMatch = strpos(strtolower($providerLocation), strtolower($searchFilters['location'])) !== false ? 1 : 0;
        }

        // Price match
        $priceMatch = 0;
        if (isset($searchFilters['price_min']) && isset($searchFilters['price_max'])) {
            $providerPrice = $price ?? 0;
            $priceMatch = ($providerPrice >= $searchFilters['price_min'] && $providerPrice <= $searchFilters['price_max']) ? 1 : 0;
        }

        // Availability match
        $availabilityMatch = ($row['availability'] ?? '') === 'available' ? 1 : 0;

        // User context features
        $userSearchFrequency = 0;
        $userCategoryPreference = 0.0;
        $userPriceRangePreference = '0-50000';

        if ($userId) {
            $userStats = $this->queryRow(
                "SELECT search_frequency, category_preference_score, price_range_preference
                 FROM user_search_stats WHERE user_id = ?",
                [$userId]
            );
            if ($userStats) {
                $userSearchFrequency = (int) ($userStats['search_frequency'] ?? 0);
                $userCategoryPreference = (float) ($userStats['category_preference_score'] ?? 0.0);
                $userPriceRangePreference = $userStats['price_range_preference'] ?? '0-50000';
            }
        }

        return [
            'views' => max(0, (float) $views),
            'clicks' => max(0, (float) $clicks),
            'messages' => max(0, (float) $messages),
            'rating' => min(5.0, max(0.0, (float) $rating)),
            'price' => max(0, (float) $price),
            'avg_response_time' => max(0, (float) $avgResponseTime),
            'is_verified' => (int) $isVerified,
            'is_featured' => (int) $isFeatured,
            'experience_years' => max(0, (float) $experienceYears),
            'completion_rate' => min(1.0, max(0.0, (float) $completionRate)),
            'search_query_length' => (int) $searchQueryLength,
            'category_match' => (int) $categoryMatch,
            'location_match' => (int) $locationMatch,
            'price_match' => (int) $priceMatch,
            'availability_match' => (int) $availabilityMatch,
            'user_search_frequency' => (int) $userSearchFrequency,
            'user_category_preference' => (float) $userCategoryPreference,
            'user_price_range_preference' => $userPriceRangePreference,
        ];
    }

    /**
     * Builds personalization features for one provider-user pair.
     */
    private function buildPersonalizationFeatures(array $row, int $userId): array
    {
        $pid = (int) ($row['id'] ?? $row['provider_id'] ?? 0);

        // Provider features
        $rating = $row['average_rating'] ?? 0.0;
        $price = $row['price'] ?? 0.0;
        $avgResponseTime = $row['avg_response_time'] ?? 60.0;
        $experienceYears = $row['experience_years'] ?? 0;
        $isVerified = $row['is_verified'] ?? 0;
        $isFeatured = $row['is_featured'] ?? 0;
        $completionRate = $row['completion_rate'] ?? 0.0;

        // User historical behavior
        $userProfile = $this->queryRow(
            "SELECT user_avg_rating_given, user_avg_price_paid, user_preferred_response_time, user_total_bookings, user_category_preference_score
             FROM user_profiles WHERE user_id = ?",
            [$userId]
        );

        $userAvgRatingGiven = 0.0;
        $userAvgPricePaid = 0.0;
        $userPreferredResponseTime = 24.0;
        $userTotalBookings = 0;
        $userCategoryPreferenceScore = 0.0;

        if ($userProfile) {
            $userAvgRatingGiven = (float) ($userProfile['user_avg_rating_given'] ?? 0.0);
            $userAvgPricePaid = (float) ($userProfile['user_avg_price_paid'] ?? 0.0);
            $userPreferredResponseTime = (float) ($userProfile['user_preferred_response_time'] ?? 24.0);
            $userTotalBookings = (int) ($userProfile['user_total_bookings'] ?? 0);
            $userCategoryPreferenceScore = (float) ($userProfile['user_category_preference_score'] ?? 0.0);
        }

        // Interaction history
        $userProviderInteractionCount = $this->queryScalar(
            "SELECT COUNT(*) FROM provider_views WHERE user_id = ? AND provider_id = ?",
            [$userId, $pid]
        ) ?? 0;

        $userProviderMessageCount = $this->queryScalar(
            "SELECT COUNT(*) FROM messages WHERE (sender_id = ? AND receiver_id = (SELECT user_id FROM service_providers WHERE id = ?)) OR (sender_id = (SELECT user_id FROM service_providers WHERE id = ?) AND receiver_id = ?)",
            [$userId, $pid, $pid, $userId]
        ) ?? 0;

        $userProviderViewCount = $userProviderInteractionCount; // Same as interaction count for views

        $daysSinceLastInteraction = $this->queryScalar(
            "SELECT DATEDIFF(NOW(), MAX(created_at))
             FROM user_interactions
             WHERE user_id = ? AND provider_id = ?",
            [$userId, $pid]
        ) ?? 0;

        return [
            'rating' => min(5.0, max(0.0, (float) $rating)),
            'price' => max(0, (float) $price),
            'avg_response_time' => max(0, (float) $avgResponseTime),
            'experience_years' => max(0, (float) $experienceYears),
            'is_verified' => (int) $isVerified,
            'is_featured' => (int) $isFeatured,
            'completion_rate' => min(1.0, max(0.0, (float) $completionRate)),
            'user_avg_rating_given' => min(5.0, max(0.0, (float) $userAvgRatingGiven)),
            'user_avg_price_paid' => max(0, (float) $userAvgPricePaid),
            'user_preferred_response_time' => max(0, (float) $userPreferredResponseTime),
            'user_total_bookings' => max(0, (int) $userTotalBookings),
            'user_category_preference_score' => (float) $userCategoryPreferenceScore,
            'user_provider_interaction_count' => max(0, (int) $userProviderInteractionCount),
            'user_provider_message_count' => max(0, (int) $userProviderMessageCount),
            'user_provider_view_count' => max(0, (int) $userProviderViewCount),
            'days_since_last_interaction' => max(0, (int) $daysSinceLastInteraction),
        ];
    }

    /**
     * Builds provider performance features.
     */
    private function buildProviderPerformanceFeatures(array $row): array
    {
        $pid = (int) ($row['id'] ?? $row['provider_id'] ?? 0);

        return [
            'experience_years' => max(0, (float) ($row['experience_years'] ?? 0)),
            'is_verified' => (int) ($row['is_verified'] ?? 0),
            'is_featured' => (int) ($row['is_featured'] ?? 0),
            'hourly_rate' => max(0, (float) ($row['hourly_rate'] ?? 0)),
            'working_days_count' => max(1, min(7, (int) ($row['working_days_count'] ?? 5))),
            'max_daily_bookings' => max(1, (int) ($row['max_daily_bookings'] ?? 5)),
            'portfolio_enabled' => (int) ($row['portfolio_enabled'] ?? 0),
            'total_reviews' => max(0, (int) ($row['total_reviews'] ?? 0)),
            'average_rating' => min(5.0, max(0.0, (float) ($row['average_rating'] ?? 0.0))),
            'total_jobs_completed' => max(0, (int) ($row['total_jobs_completed'] ?? 0)),
            'avg_response_time_minutes' => max(0, (float) ($row['avg_response_time_minutes'] ?? 60.0)),
            'completion_rate' => min(1.0, max(0.0, (float) ($row['completion_rate'] ?? 0.0))),
            'cancellation_rate' => min(1.0, max(0.0, (float) ($row['cancellation_rate'] ?? 0.0))),
            'profile_views_last_30d' => max(0, (int) ($row['profile_views_last_30d'] ?? 0)),
            'messages_received_last_30d' => max(0, (int) ($row['messages_received_last_30d'] ?? 0)),
            'bookings_last_30d' => max(0, (int) ($row['bookings_last_30d'] ?? 0)),
            'days_since_last_booking' => max(0, (int) ($row['days_since_last_booking'] ?? 30)),
            'days_active' => max(0, (int) ($row['days_active'] ?? 0)),
            'facebook_followers' => max(0, (int) ($row['facebook_followers'] ?? 0)),
            'instagram_followers' => max(0, (int) ($row['instagram_followers'] ?? 0)),
            'website_has_content' => (int) ($row['website_has_content'] ?? 0),
        ];
    }

    /**
     * Builds user engagement features.
     */
    private function buildUserEngagementFeatures(int $userId, array $providerRow, array $context = []): array
    {
        $pid = (int) ($providerRow['id'] ?? $providerRow['provider_id'] ?? 0);

        // User profile features
        $userProfile = $this->queryRow(
            "SELECT DATEDIFF(NOW(), created_at) as account_age_days, is_verified,
                    profile_completion_score, total_bookings, total_reviews_written, total_messages_sent
             FROM users u
             LEFT JOIN user_profiles up ON u.id = up.user_id
             WHERE u.id = ?",
            [$userId]
        );

        $accountAgeDays = 0;
        $isVerified = 0;
        $profileCompletionScore = 0.0;
        $totalBookings = 0;
        $totalReviewsWritten = 0;
        $totalMessagesSent = 0;

        if ($userProfile) {
            $accountAgeDays = (int) ($userProfile['account_age_days'] ?? 0);
            $isVerified = (int) ($userProfile['is_verified'] ?? 0);
            $profileCompletionScore = (float) ($userProfile['profile_completion_score'] ?? 0.0);
            $totalBookings = (int) ($userProfile['total_bookings'] ?? 0);
            $totalReviewsWritten = (int) ($userProfile['total_reviews_written'] ?? 0);
            $totalMessagesSent = (int) ($userProfile['total_messages_sent'] ?? 0);
        }

        // Provider context features
        $providerRating = (float) ($providerRow['average_rating'] ?? 0.0);
        $providerPrice = (float) ($providerRow['price'] ?? 0.0);
        $providerResponseTime = (float) ($providerRow['avg_response_time'] ?? 60.0);
        $providerExperienceYears = (float) ($providerRow['experience_years'] ?? 0);
        $providerIsVerified = (int) ($providerRow['is_verified'] ?? 0);
        $providerIsFeatured = (int) ($providerRow['is_featured'] ?? 0);

        // Interaction history
        $previousViewsOfProvider = $this->queryScalar(
            "SELECT COUNT(*) FROM provider_views WHERE user_id = ? AND provider_id = ?",
            [$userId, $pid]
        ) ?? 0;

        $previousMessagesWithProvider = $this->queryScalar(
            "SELECT COUNT(*) FROM messages WHERE (sender_id = ? AND receiver_id = (SELECT user_id FROM service_providers WHERE id = ?)) OR (sender_id = (SELECT user_id FROM service_providers WHERE id = ?) AND receiver_id = ?)",
            [$userId, $pid, $pid, $userId]
        ) ?? 0;

        $daysSinceFirstInteraction = $this->queryScalar(
            "SELECT DATEDIFF(NOW(), MIN(created_at))
             FROM user_interactions
             WHERE user_id = ? AND provider_id = ?",
            [$userId, $pid]
        ) ?? 0;

        $interactionFrequency = $this->queryScalar(
            "SELECT COUNT(*) FROM user_interactions
             WHERE user_id = ? AND provider_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId, $pid]
        ) ?? 0;

        // Session context
        $timeOfDay = (int) ($context['time_of_day'] ?? date('H'));
        $dayOfWeek = (int) ($context['day_of_week'] ?? date('N'));
        $isWeekend = (int) ($dayOfWeek >= 6 ? 1 : 0);
        $sessionDurationMinutes = (float) ($context['session_duration_minutes'] ?? 0.0);
        $pagesViewedInSession = (int) ($context['pages_viewed_in_session'] ?? 1);
        $searchQueriesInSession = (int) ($context['search_queries_in_session'] ?? 0);

        // Platform engagement
        $avgSessionDuration = (float) ($context['avg_session_duration'] ?? 0.0);
        $pagesPerSession = (float) ($context['pages_per_session'] ?? 1.0);
        $returnVisitor = (int) ($context['return_visitor'] ?? 0);
        $daysSinceLastVisit = (int) ($context['days_since_last_visit'] ?? 0);
        $totalSessionsLast30d = (int) ($context['total_sessions_last_30d'] ?? 0);

        return [
            'account_age_days' => max(0, $accountAgeDays),
            'is_verified' => $isVerified,
            'profile_completion_score' => min(1.0, max(0.0, $profileCompletionScore)),
            'total_bookings' => max(0, $totalBookings),
            'total_reviews_written' => max(0, $totalReviewsWritten),
            'total_messages_sent' => max(0, $totalMessagesSent),
            'provider_rating' => min(5.0, max(0.0, $providerRating)),
            'provider_price' => max(0, $providerPrice),
            'provider_response_time' => max(0, $providerResponseTime),
            'provider_experience_years' => max(0, $providerExperienceYears),
            'provider_is_verified' => $providerIsVerified,
            'provider_is_featured' => $providerIsFeatured,
            'previous_views_of_provider' => max(0, (int) $previousViewsOfProvider),
            'previous_messages_with_provider' => max(0, (int) $previousMessagesWithProvider),
            'days_since_first_interaction' => max(0, (int) $daysSinceFirstInteraction),
            'interaction_frequency' => max(0, (int) $interactionFrequency),
            'time_of_day' => max(0, min(23, $timeOfDay)),
            'day_of_week' => max(1, min(7, $dayOfWeek)),
            'is_weekend' => $isWeekend,
            'session_duration_minutes' => max(0, $sessionDurationMinutes),
            'pages_viewed_in_session' => max(1, $pagesViewedInSession),
            'search_queries_in_session' => max(0, $searchQueriesInSession),
            'avg_session_duration' => max(0, $avgSessionDuration),
            'pages_per_session' => max(0, $pagesPerSession),
            'return_visitor' => $returnVisitor,
            'days_since_last_visit' => max(0, $daysSinceLastVisit),
            'total_sessions_last_30d' => max(0, $totalSessionsLast30d),
        ];
    }

    /**
     * Builds user segmentation features for clustering.
     */
    private function buildUserSegmentationFeatures(int $userId): array
    {
        // ── Booking behavior ──────────────────────────────────────────────────
        $bookingStats = $this->queryRow("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                AVG(amount) as avg_booking_value,
                SUM(amount) as total_spent,
                COUNT(*) / GREATEST(DATEDIFF(CURDATE(), MIN(created_at)), 1) as booking_frequency
            FROM bookings
            WHERE client_id = ?
        ", [$userId]) ?? [
            'total_bookings' => 0, 'completed_bookings' => 0, 'cancelled_bookings' => 0,
            'avg_booking_value' => 0, 'total_spent' => 0, 'booking_frequency' => 0
        ];

        $completionRate = $bookingStats['total_bookings'] > 0
            ? $bookingStats['completed_bookings'] / $bookingStats['total_bookings']
            : 0;

        // ── Service preferences ───────────────────────────────────────────────
        $servicePrefs = $this->queryRow("
            SELECT
                COUNT(DISTINCT profession) as service_diversity,
                GROUP_CONCAT(DISTINCT profession) as preferred_professions,
                CASE
                    WHEN AVG(amount) < 10000 THEN 0.8  -- Price sensitive
                    WHEN AVG(amount) > 50000 THEN 0.2  -- Premium focused
                    ELSE 0.5  -- Moderate
                END as price_sensitivity
            FROM bookings b
            JOIN service_providers sp ON b.provider_id = sp.id
            WHERE b.client_id = ? AND b.status = 'completed'
        ", [$userId]) ?? [
            'service_diversity' => 0, 'preferred_professions' => '', 'price_sensitivity' => 0.5
        ];

        $preferredProfessionsCount = empty($servicePrefs['preferred_professions'])
            ? 0
            : count(explode(',', $servicePrefs['preferred_professions']));

        // ── Engagement metrics ────────────────────────────────────────────────
        $userData = $this->queryRow("SELECT * FROM users WHERE id = ?", [$userId]);
        $profileCompleteness = 0.1; // Base score

        if ($userData) {
            $hasName = !empty($userData['full_name']);
            $hasLocation = !empty($userData['location']);
            $hasImage = !empty($userData['profile_image']);
            $isVerified = (int) ($userData['is_verified'] ?? 0);

            if ($hasName && $hasLocation && $hasImage) $profileCompleteness = 1.0;
            elseif ($hasName && $hasLocation) $profileCompleteness = 0.7;
            elseif ($hasName) $profileCompleteness = 0.4;
        }

        $engagementStats = $this->queryRow("
            SELECT
                COUNT(DISTINCT r.id) as reviews_written,
                COUNT(DISTINCT f.provider_id) as favorites_count,
                AVG(r.rating) as avg_rating_given
            FROM users u
            LEFT JOIN reviews r ON u.id = r.client_id
            LEFT JOIN favorites f ON u.id = f.client_id
            WHERE u.id = ?
        ", [$userId]) ?? [
            'reviews_written' => 0, 'favorites_count' => 0, 'avg_rating_given' => 0
        ];

        $engagementScore = (
            $profileCompleteness * 0.2 +
            (!empty($engagementStats['reviews_written']) ? 0.2 : 0) +
            (!empty($engagementStats['favorites_count']) ? 0.2 : 0) +
            ($completionRate * 0.2) +
            (min(1.0, $bookingStats['total_bookings'] / 10) * 0.2)
        );

        // ── Geographic behavior ───────────────────────────────────────────────
        $locationStats = $this->queryRow("
            SELECT COUNT(DISTINCT sp.location) as location_diversity
            FROM bookings b
            JOIN service_providers sp ON b.provider_id = sp.id
            WHERE b.client_id = ? AND b.status = 'completed'
        ", [$userId]) ?? ['location_diversity' => 0];

        // ── Temporal patterns ─────────────────────────────────────────────────
        $temporalStats = $this->queryRow("
            SELECT
                HOUR(AVG(created_at)) as peak_booking_hour,
                COUNT(CASE WHEN DAYOFWEEK(created_at) IN (1,7) THEN 1 END) / COUNT(*) as weekend_bookings_ratio,
                CASE
                    WHEN MONTH(AVG(created_at)) IN (12,1,2) THEN 0.0  -- Winter
                    WHEN MONTH(AVG(created_at)) IN (3,4,5) THEN 0.25  -- Spring
                    WHEN MONTH(AVG(created_at)) IN (6,7,8) THEN 0.5   -- Summer
                    WHEN MONTH(AVG(created_at)) IN (9,10,11) THEN 0.75 -- Fall
                    ELSE 0.5
                END as seasonal_pattern
            FROM bookings WHERE client_id = ?
        ", [$userId]) ?? [
            'peak_booking_hour' => 12, 'weekend_bookings_ratio' => 0, 'seasonal_pattern' => 0
        ];

        // ── Platform interaction ──────────────────────────────────────────────
        $accountAgeDays = $userData ? (int) $this->queryScalar(
            "SELECT DATEDIFF(CURDATE(), created_at) FROM users WHERE id = ?",
            [$userId]
        ) : 0;

        $platformStats = $this->queryRow("
            SELECT
                DATEDIFF(CURDATE(), MAX(created_at)) as last_activity_days,
                COUNT(*) / GREATEST(DATEDIFF(CURDATE(), MIN(created_at)), 1) as login_frequency,
                COUNT(CASE WHEN target_type = 'search' THEN 1 END) as search_queries_count,
                COUNT(CASE WHEN target_type = 'provider' THEN 1 END) as provider_views_count
            FROM click_logs
            WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ", [$userId]) ?? [
            'last_activity_days' => 999, 'login_frequency' => 0,
            'search_queries_count' => 0, 'provider_views_count' => 0
        ];

        return [
            'total_bookings' => max(0, (int) $bookingStats['total_bookings']),
            'completed_bookings' => max(0, (int) $bookingStats['completed_bookings']),
            'cancelled_bookings' => max(0, (int) $bookingStats['cancelled_bookings']),
            'avg_booking_value' => max(0, (float) $bookingStats['avg_booking_value']),
            'total_spent' => max(0, (float) $bookingStats['total_spent']),
            'booking_frequency' => max(0, (float) $bookingStats['booking_frequency']),
            'completion_rate' => min(1.0, max(0.0, $completionRate)),
            'service_diversity' => max(0, (int) $servicePrefs['service_diversity']),
            'price_sensitivity' => min(1.0, max(0.0, (float) $servicePrefs['price_sensitivity'])),
            'preferred_professions_count' => max(0, $preferredProfessionsCount),
            'profile_completeness' => min(1.0, max(0.0, $profileCompleteness)),
            'response_rate' => 0.8, // Placeholder - could be enhanced
            'avg_rating_given' => min(5.0, max(0.0, (float) $engagementStats['avg_rating_given'])),
            'favorites_count' => max(0, (int) $engagementStats['favorites_count']),
            'reviews_written' => max(0, (int) $engagementStats['reviews_written']),
            'engagement_score' => min(1.0, max(0.0, $engagementScore)),
            'location_diversity' => max(0, (int) $locationStats['location_diversity']),
            'peak_booking_hour' => max(0, min(23, (int) $temporalStats['peak_booking_hour'])),
            'weekend_bookings_ratio' => min(1.0, max(0.0, (float) $temporalStats['weekend_bookings_ratio'])),
            'seasonal_pattern' => min(1.0, max(0.0, (float) $temporalStats['seasonal_pattern'])),
            'account_age_days' => max(0, $accountAgeDays),
            'last_activity_days' => max(0, (int) $platformStats['last_activity_days']),
            'login_frequency' => max(0, (float) $platformStats['login_frequency']),
            'search_queries_count' => max(0, (int) $platformStats['search_queries_count']),
            'provider_views_count' => max(0, (int) $platformStats['provider_views_count']),
        ];
    }

    // ── HTTP helpers ────────────────────────────────────────────────────────

    /** POST /predict/{model} for a single prediction. */
    private function callSinglePredict(string $modelType, array $features): ?array
    {
        return $this->curlPost("/predict/{$modelType}", $features);
    }

    /** POST /predict/{model}/batch for multiple predictions. */
    private function callBatchPredict(string $modelType, array $items): array
    {
        $result = $this->curlPost("/predict/{$modelType}/batch", $items);
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
            error_log("[MultiModelRecommender] POST {$path} failed (errno={$errno})");
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

    // ── DB helpers ───────────────────────────────────────────────────────────

    private function queryScalar(string $sql, array $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : null;
        } catch (Throwable $e) {
            error_log("[MultiModelRecommender] DB query failed: " . $e->getMessage());
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
            error_log("[MultiModelRecommender] DB query failed: " . $e->getMessage());
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