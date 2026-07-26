<?php
/**
 * RecommendationEngine
 * ---------------------------------------------------------------------
 * 100% rule-based (no ML, no external AI, no Python).
 * Decides:
 *   1) which discovery sections appear on the Providers page,
 *   2) in what order,
 *   3) how a provider's Trust Score and badges are computed.
 *
 * Every decision is a deterministic function of real signals already in
 * the database (bookings, favorites, views, clicks, negotiations,
 * search history, location, time of day / week). Two clients with
 * different histories will therefore get different section orders,
 * and the same client reloading the page keeps a stable order
 * (deterministic seed), instead of random flicker.
 * ---------------------------------------------------------------------
 */
final class RecommendationEngine
{
    /** Master catalog of all possible sections and their static metadata. */
    public const CATALOG = [
        'for_you'            => ['title' => 'Recommended For You',        'icon' => 'fa-wand-magic-sparkles'],
        'continue_booking'   => ['title' => 'Continue Where You Left Off','icon' => 'fa-clock-rotate-left'],
        'recently_viewed'    => ['title' => 'Recently Viewed',            'icon' => 'fa-eye'],
        'matching_interests' => ['title' => 'Providers Matching Your Interests', 'icon' => 'fa-heart-circle-check'],
        'near_you'           => ['title' => 'Near You',                   'icon' => 'fa-location-dot'],
        'popular_in_city'    => ['title' => 'Popular In Your City',       'icon' => 'fa-city'],
        'available_now'      => ['title' => 'Available Right Now',       'icon' => 'fa-bolt'],
        'top_rated_near_you' => ['title' => 'Top Rated Near You',        'icon' => 'fa-star'],
        'fast_responders'    => ['title' => 'Fast Responders',           'icon' => 'fa-gauge-high'],
        'trending'           => ['title' => 'Trending This Week',        'icon' => 'fa-fire'],
        'most_trusted'       => ['title' => 'Most Trusted Providers',    'icon' => 'fa-shield-halved'],
        'verified'           => ['title' => 'Verified Providers',        'icon' => 'fa-circle-check'],
        'premium'            => ['title' => 'Premium Providers',         'icon' => 'fa-crown'],
        'special_offers'     => ['title' => 'Special Offers',            'icon' => 'fa-tags'],
        'weekend_picks'      => ['title' => 'Weekend Picks',             'icon' => 'fa-calendar-week'],
        'emergency_services' => ['title' => 'Emergency Services',        'icon' => 'fa-truck-medical'],
        'new_providers'      => ['title' => 'New Providers',             'icon' => 'fa-seedling'],
        'hidden_gems'        => ['title' => 'Hidden Gems',               'icon' => 'fa-gem'],
        'you_may_like'       => ['title' => 'Providers You May Like',    'icon' => 'fa-thumbs-up'],
    ];

    /** Sections shown to a brand-new client with zero history. */
    private const NEW_USER_ORDER = [
        'trending', 'top_rated_near_you', 'verified', 'near_you',
        'available_now', 'new_providers',
    ];

    /**
     * Build the ordered list of section keys for this client.
     *
     * @param array $signals see ProviderDiscoveryService::collectUserSignals()
     * @return string[] ordered section keys
     */
    public static function planSections(int $userId, array $signals, array $context): array
    {
        if (empty($signals['has_history'])) {
            return self::NEW_USER_ORDER;
        }

        // Base priority score per section, driven by real signals only.
        $priority = [
            'for_you'            => 100, // always the anchor when history exists
            'continue_booking'   => !empty($signals['open_booking']) ? 95 : -1,
            'matching_interests' => !empty($signals['top_professions']) ? 80 : -1,
            'recently_viewed'    => !empty($signals['recently_viewed_ids']) ? 75 : -1,
            'you_may_like'       => !empty($signals['co_occurring_professions']) ? 70 : -1,
            'near_you'           => !empty($signals['has_location']) ? 65 : 40,
            'top_rated_near_you' => 60,
            'available_now'      => self::isDaytime($context['hour']) ? 58 : 30,
            'fast_responders'    => $signals['prefers_fast_response'] ? 62 : 35,
            'trending'           => 50,
            'popular_in_city'    => !empty($signals['has_location']) ? 48 : -1,
            'most_trusted'       => 45,
            'verified'           => 40,
            'weekend_picks'      => self::isWeekend($context['day_of_week']) ? 68 : -1,
            'special_offers'     => $signals['price_sensitive'] ? 55 : 30,
            'emergency_services' => (!self::isDaytime($context['hour']) || self::isWeekend($context['day_of_week'])) ? 44 : 20,
            'premium'            => 25,
            'hidden_gems'        => 33,
            'new_providers'      => 20,
        ];

        // Drop sections this user has no signal for (negative priority).
        $enabled = array_filter($priority, fn($p) => $p >= 0);

        // Deterministic per-user jitter so ordering differs between clients
        // even when priorities tie, but stays stable across reloads.
        foreach ($enabled as $key => $p) {
            $enabled[$key] = $p + self::seededJitter($userId, $key);
        }

        arsort($enabled);

        return array_keys($enabled);
    }

    /** Small deterministic pseudo-random offset in [0, 4), seeded per user+section. */
    public static function seededJitter(int $userId, string $seedKey): float
    {
        $hash = crc32($userId . '::' . $seedKey);
        return ($hash % 400) / 100; // 0.00 .. 3.99
    }

    public static function isWeekend(int $isoDayOfWeek): bool
    {
        return $isoDayOfWeek >= 6; // 6=Sat, 7=Sun
    }

    public static function isDaytime(int $hour): bool
    {
        return $hour >= 7 && $hour <= 19;
    }

    /**
     * Rule-based Trust Score (0-100). No ML — plain weighted formula over
     * verifiable facts: rating, completion rate, verification level,
     * review volume, and account tenure.
     */
    public static function trustScore(array $provider): float
    {
        $rating = min(5.0, max(0.0, (float) ($provider['average_rating'] ?? 0)));
        $ratingScore = ($rating / 5) * 40;

        $totalJobs = max(0, (int) ($provider['total_jobs'] ?? 0));
        $completedJobs = max(0, (int) ($provider['completed_jobs'] ?? 0));
        $completionRate = $totalJobs > 0 ? min(1.0, $completedJobs / $totalJobs) : 0.3;
        $completionScore = $completionRate * 25;

        $verLevel = strtolower((string) ($provider['verification_level'] ?? 'none'));
        $isVerified = !empty($provider['is_verified']) || !empty($provider['user_verified']);
        $verificationScore = match (true) {
            in_array($verLevel, ['gold', 'premium'], true) => 15,
            $verLevel === 'verified' || $isVerified => 10,
            default => 0,
        };

        $reviews = max(0, (int) ($provider['total_reviews'] ?? 0));
        $reviewScore = min(50, $reviews) / 50 * 10;

        $createdAt = $provider['created_at'] ?? null;
        $tenureMonths = 0;
        if ($createdAt) {
            $tenureMonths = max(0, (int) floor((time() - strtotime($createdAt)) / 2_592_000));
        }
        $tenureScore = min(10, $tenureMonths); // caps at 10 months+ tenure

        return round(min(100, $ratingScore + $completionScore + $verificationScore + $reviewScore + $tenureScore), 1);
    }

    /**
     * Compute which smart badges apply to a provider, given the section
     * context it is being rendered in. Pure rule checks — deterministic
     * and explainable.
     *
     * @return array<string,string> badge_key => human label
     */
    public static function badgesFor(array $provider, array $context = []): array
    {
        $badges = [];

        $rating = (float) ($provider['average_rating'] ?? 0);
        $reviews = (int) ($provider['total_reviews'] ?? 0);
        $trust = (float) ($provider['trust_score'] ?? self::trustScore($provider));
        $isVerified = !empty($provider['is_verified']) || !empty($provider['user_verified'])
            || !in_array(strtolower((string) ($provider['verification_level'] ?? 'none')), ['none', ''], true);

        if ($rating >= 4.5 && $reviews >= 3) {
            $badges['top_rated'] = 'Top Rated';
        }
        if (!empty($provider['is_trending'])) {
            $badges['trending'] = 'Trending';
        }
        if (isset($provider['avg_response_hours']) && $provider['avg_response_hours'] !== null && (float) $provider['avg_response_hours'] <= 3) {
            $badges['fast_response'] = 'Fast Response';
        }
        if ($trust >= 80) {
            $badges['highly_recommended'] = 'Highly Recommended';
        }
        if ($isVerified) {
            $badges['verified'] = 'Verified';
        }
        if (!empty($provider['created_at']) && (time() - strtotime($provider['created_at'])) <= 30 * 86400) {
            $badges['new'] = 'New';
        }
        if (!empty($provider['same_city']) && (int) ($provider['total_jobs'] ?? 0) >= 5) {
            $badges['popular_nearby'] = 'Popular Nearby';
        }
        if ((int) ($provider['total_jobs'] ?? 0) >= 8) {
            $badges['most_booked'] = 'Most Booked';
        }
        if ($trust >= 70) {
            $badges['trusted'] = 'Trusted';
        }
        if (strtolower((string) ($provider['availability'] ?? '')) === 'available') {
            $badges['available_now'] = 'Available Now';
        }

        return $badges;
    }
}
