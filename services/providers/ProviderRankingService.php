<?php

require_once __DIR__ . '/../../includes/provider_ranking_config.php';

class ProviderRankingService
{
    private array $weights;

    public function __construct(?array $weights = null)
    {
        $this->weights = $weights ?? provider_ranking_get_default_weights();
    }

    public function rankProviders(array $providers, array $context = []): array
    {
        $clientId = (int)($context['client_id'] ?? 0);
        $clientLocation = trim((string)($context['client_location'] ?? ''));
        $searchTerm = strtolower(trim((string)($context['search_term'] ?? '')));
        $category = trim((string)($context['category'] ?? ''));
        $bookedProfessions = [];
        foreach ((array)($context['booked_professions'] ?? []) as $profession) {
            $profession = trim((string) $profession);
            if ($profession !== '') {
                $bookedProfessions[] = strtolower($profession);
            }
        }
        $favoriteIds = array_map('intval', (array)($context['favorite_ids'] ?? []));
        $viewedIds = array_map('intval', (array)($context['viewed_ids'] ?? []));
        $bookedIds = array_map('intval', (array)($context['booked_ids'] ?? []));
        $recentActivityWindow = (int)($context['recent_activity_window'] ?? 90);

        $ranked = [];
        foreach ($providers as $provider) {
            $score = $this->calculateScore($provider, [
                'client_id' => $clientId,
                'client_location' => $clientLocation,
                'search_term' => $searchTerm,
                'category' => $category,
                'booked_professions' => $bookedProfessions,
                'favorite_ids' => $favoriteIds,
                'viewed_ids' => $viewedIds,
                'booked_ids' => $bookedIds,
                'recent_activity_window' => $recentActivityWindow,
            ]);

            $ranked[] = [
                'provider' => $provider,
                'score' => $score,
            ];
        }

        usort($ranked, function ($a, $b) {
            if ((float)$a['score'] === (float)$b['score']) {
                return 0;
            }
            return (float)$a['score'] < (float)$b['score'] ? 1 : -1;
        });

        return array_map(fn ($item) => $item['provider'], $ranked);
    }

    private function calculateScore(array $provider, array $context): float
    {
        $interestMatch = $this->normalize($this->getValue($provider, 'interest_match'), 0, 1);
        $distance = $this->normalize($this->getValue($provider, 'distance_km'), 0, 50, true);
        $categoryMatch = $this->normalize($this->getValue($provider, 'category_match'), 0, 1);
        $availability = $this->normalize($this->getValue($provider, 'availability_score'), 0, 1);
        $trustScore = $this->normalize($this->getValue($provider, 'trust_score'), 0, 100);
        $completedJobs = $this->normalize($this->getValue($provider, 'completed_jobs'), 0, 500);
        $avgRating = $this->normalize($this->getValue($provider, 'average_rating'), 0, 5);
        $reviewCount = $this->normalize($this->getValue($provider, 'review_count'), 0, 200);
        $profileCompleteness = $this->normalize($this->getValue($provider, 'profile_completeness'), 0, 100);
        $verification = $this->normalize($this->getValue($provider, 'verification_status'), 0, 1);
        $premium = $this->normalize($this->getValue($provider, 'premium_subscription'), 0, 1);
        $responseSpeed = $this->normalize($this->getValue($provider, 'response_speed_score'), 0, 1);
        $recentActivity = $this->normalize($this->getValue($provider, 'recent_activity_score'), 0, 1);
        $complaintRatio = $this->normalize($this->getValue($provider, 'complaint_ratio'), 0, 1, true);
        $newProviderBoost = $this->normalize($this->getValue($provider, 'new_provider_boost'), 0, 1);
        $repeatCustomerBonus = $this->normalize($this->getValue($provider, 'repeat_customer_bonus'), 0, 1);
        $popularityInArea = $this->normalize($this->getValue($provider, 'popularity_in_user_area'), 0, 1);

        $score = 0.0;
        $score += ($this->weights['interest_match'] ?? 0) * $interestMatch;
        $score += ($this->weights['distance'] ?? 0) * $distance;
        $score += ($this->weights['category_match'] ?? 0) * $categoryMatch;
        $score += ($this->weights['availability'] ?? 0) * $availability;
        $score += ($this->weights['trust_score'] ?? 0) * $trustScore;
        $score += ($this->weights['completed_jobs'] ?? 0) * $completedJobs;
        $score += ($this->weights['average_rating'] ?? 0) * $avgRating;
        $score += ($this->weights['review_count'] ?? 0) * $reviewCount;
        $score += ($this->weights['profile_completeness'] ?? 0) * $profileCompleteness;
        $score += ($this->weights['verification'] ?? 0) * $verification;
        $score += ($this->weights['premium_subscription'] ?? 0) * $premium;
        $score += ($this->weights['response_speed'] ?? 0) * $responseSpeed;
        $score += ($this->weights['recent_activity'] ?? 0) * $recentActivity;
        $score += ($this->weights['complaint_ratio'] ?? 0) * $complaintRatio;
        $score += ($this->weights['new_provider_boost'] ?? 0) * $newProviderBoost;
        $score += ($this->weights['repeat_customer_bonus'] ?? 0) * $repeatCustomerBonus;
        $score += ($this->weights['popularity_in_user_area'] ?? 0) * $popularityInArea;

        $score += $this->applyClientContextBoost($provider, $context);

        return round($score * 100, 4);
    }

    private function applyClientContextBoost(array $provider, array $context): float
    {
        $boost = 0.0;
        $clientId = (int)($context['client_id'] ?? 0);
        $searchTerm = strtolower(trim((string)($context['search_term'] ?? '')));
        $category = trim((string)($context['category'] ?? ''));
        $bookedProfessions = [];
        foreach ((array)($context['booked_professions'] ?? []) as $profession) {
            $profession = trim((string) $profession);
            if ($profession !== '') {
                $bookedProfessions[] = strtolower($profession);
            }
        }
        $favoriteIds = array_map('intval', (array)($context['favorite_ids'] ?? []));
        $viewedIds = array_map('intval', (array)($context['viewed_ids'] ?? []));
        $bookedIds = array_map('intval', (array)($context['booked_ids'] ?? []));

        if ($clientId > 0) {
            $clientSeed = (($clientId * 17) + ((int)($provider['id'] ?? 0) * 7)) % 11;
            $boost += 0.004 * $clientSeed;
        }

        if ($searchTerm !== '' && $this->matchesSearch($provider, $searchTerm)) {
            $boost += 0.01;
        }

        if ($category !== '' && strtolower((string)($provider['category'] ?? '')) === strtolower($category)) {
            $boost += 0.01;
        }

        if (!empty($bookedProfessions) && in_array(strtolower((string)($provider['profession'] ?? '')), $bookedProfessions, true)) {
            $boost += 0.008;
        }

        if (!empty($favoriteIds) && in_array((int)($provider['id'] ?? 0), $favoriteIds, true)) {
            $boost += 0.004;
        }

        if (!empty($viewedIds) && in_array((int)($provider['id'] ?? 0), $viewedIds, true)) {
            $boost += 0.003;
        }

        if (!empty($bookedIds) && in_array((int)($provider['id'] ?? 0), $bookedIds, true)) {
            $boost += 0.005;
        }

        return $boost;
    }

    private function matchesSearch(array $provider, string $searchTerm): bool
    {
        $haystack = strtolower(trim((string)($provider['name'] ?? '')) . ' ' . (string)($provider['profession'] ?? '') . ' ' . (string)($provider['category'] ?? '') . ' ' . (string)($provider['location'] ?? ''));
        return strpos($haystack, $searchTerm) !== false;
    }

    private function normalize($value, float $min, float $max, bool $invert = false): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $num = (float)$value;
        if ($max <= $min) {
            return 0.0;
        }

        $ratio = ($num - $min) / ($max - $min);
        if ($ratio < 0) {
            $ratio = 0;
        }
        if ($ratio > 1) {
            $ratio = 1;
        }

        if ($invert) {
            $ratio = 1 - $ratio;
        }

        return $ratio;
    }

    private function getValue(array $provider, string $key)
    {
        return $provider[$key] ?? null;
    }
}
