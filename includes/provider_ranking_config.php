<?php

if (!function_exists('provider_ranking_get_default_weights')) {
    function provider_ranking_get_default_weights(): array
    {
        return [
            'interest_match' => 0.18,
            'distance' => 0.12,
            'category_match' => 0.08,
            'availability' => 0.07,
            'trust_score' => 0.14,
            'completed_jobs' => 0.06,
            'average_rating' => 0.08,
            'review_count' => 0.04,
            'profile_completeness' => 0.05,
            'verification' => 0.06,
            'premium_subscription' => 0.04,
            'response_speed' => 0.05,
            'recent_activity' => 0.04,
            'complaint_ratio' => -0.05,
            'new_provider_boost' => 0.03,
            'repeat_customer_bonus' => 0.03,
            'popularity_in_user_area' => 0.04,
        ];
    }
}
