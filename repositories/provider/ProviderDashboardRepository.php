<?php

class ProviderDashboardRepository
{
    public function getProviderByUserId(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("\n            SELECT sp.*, u.email, u.phone, u.profile_image, u.full_name\n            FROM service_providers sp\n            JOIN users u ON sp.user_id = u.id\n            WHERE sp.user_id = ?\n        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProviderStats(PDO $db, int $providerId): array
    {
        $stats = [];
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ?");
        $stmt->execute([$providerId]);
        $stats['total_bookings'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ? AND status = 'pending'");
        $stmt->execute([$providerId]);
        $stats['pending_bookings'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE provider_id = ?");
        $stmt->execute([$providerId]);
        $stats['total_reviews'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE provider_id = ? AND status = 'completed'");
        $stmt->execute([$providerId]);
        $stats['completed_bookings'] = (int) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM provider_views WHERE provider_id = ?");
        $stmt->execute([$providerId]);
        $stats['total_views'] = (int) $stmt->fetchColumn();

        return $stats;
    }

    public function calculateMlScore(PDO $db, array $provider, array $stats): array
    {
        $pid = (int) ($provider['id'] ?? 0);
        $mlScore = 0;
        $mlApiHealthy = false;
        $avgResponseHours = null;

        try {
            if (file_exists(__DIR__ . '/../../includes/MultiModelRecommender.php')) {
                require_once __DIR__ . '/../../includes/MultiModelRecommender.php';
                $recommender = new MultiModelRecommender($db);
                $mlApiHealthy = $recommender->isApiHealthy();
                if ($mlApiHealthy) {
                    $raw = (float) ($recommender->rankByRecommendation([$provider + ['id' => $pid]])[0]['ml_score'] ?? 0);
                    $mlScore = min(100, max(0, (int) round($raw * 100)));
                }
            }
        } catch (Throwable $e) {
            error_log('ML score error: ' . $e->getMessage());
        }

        if (!$mlApiHealthy || $mlScore === 0) {
            $ratingScore = min(100, (float) ($provider['average_rating'] ?? 0) / 5 * 40);
            $bookingScore = min(40, $stats['completed_bookings'] * 2);
            $reviewScore = min(20, $stats['total_reviews']);
            $mlScore = (int) round($ratingScore + $bookingScore + $reviewScore);
        }

        try {
            $stmt = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) FROM bookings WHERE provider_id = ? AND responded_at IS NOT NULL");
            $stmt->execute([$pid]);
            $avgResponseHours = $stmt->fetchColumn();
            if ($avgResponseHours !== null) {
                $avgResponseHours = round((float) $avgResponseHours, 1);
            }
        } catch (Throwable $e) {
            $avgResponseHours = null;
        }

        return [
            'ml_score' => $mlScore,
            'ml_api_healthy' => $mlApiHealthy,
            'avg_response_hours' => $avgResponseHours,
            'total_views' => (int) ($stats['total_views'] ?? 0),
        ];
    }

    public function buildInsights(PDO $db, int $providerId, array $provider, array $stats, int $totalViews): array
    {
        $viewsThisWeek = $this->fetchCount($db, "SELECT COUNT(*) FROM provider_views WHERE provider_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$providerId]);
        $viewsLastWeek = $this->fetchCount($db, "SELECT COUNT(*) FROM provider_views WHERE provider_id = ? AND viewed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)", [$providerId]);
        $viewsGrowth = $viewsLastWeek > 0 ? round((($viewsThisWeek - $viewsLastWeek) / $viewsLastWeek) * 100) : ($viewsThisWeek > 0 ? 100 : 0);

        $avgResponseRaw = $this->fetchValue($db, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) FROM bookings WHERE provider_id = ? AND responded_at IS NOT NULL", [$providerId]);
        $avgResponseHours = $avgResponseRaw !== null ? round((float) $avgResponseRaw, 1) : null;

        $conversionRate = $totalViews > 0 ? round((($stats['completed_bookings'] ?? 0) / $totalViews) * 100, 1) : 0;

        $insights = [];
        if ($viewsGrowth > 0) {
            $insights[] = ['icon' => 'fas fa-eye', 'color' => '#10b981', 'text' => "Profile views <strong>up {$viewsGrowth}%</strong> vs last week — great momentum!", 'type' => 'positive'];
        } elseif ($viewsGrowth < -10) {
            $insights[] = ['icon' => 'fas fa-eye-slash', 'color' => '#f59e0b', 'text' => "Views <strong>dropped {$viewsGrowth}%</strong>. Try updating your profile or adding services.", 'type' => 'warning'];
        }

        if ($avgResponseHours !== null && $avgResponseHours > 4) {
            $insights[] = ['icon' => 'fas fa-clock', 'color' => '#f59e0b', 'text' => "Avg response time is <strong>{$avgResponseHours}h</strong>. Faster replies improve rankings.", 'type' => 'warning'];
        } elseif ($avgResponseHours !== null && $avgResponseHours <= 2) {
            $insights[] = ['icon' => 'fas fa-bolt', 'color' => '#10b981', 'text' => "Lightning-fast <strong>{$avgResponseHours}h</strong> response time — clients love you!", 'type' => 'positive'];
        }

        if ($conversionRate < 1 && $totalViews > 10) {
            $insights[] = ['icon' => 'fas fa-funnel-dollar', 'color' => '#ef4444', 'text' => "Conversion rate is <strong>{$conversionRate}%</strong>. Consider improving your service descriptions or pricing.", 'type' => 'negative'];
        }

        if ((float) ($provider['average_rating'] ?? 0) >= 4.5) {
            $insights[] = ['icon' => 'fas fa-star', 'color' => '#f59e0b', 'text' => "Outstanding <strong>{$provider['average_rating']} ★</strong> rating — you're in the top tier!", 'type' => 'positive'];
        }

        if (empty($insights)) {
            $insights[] = ['icon' => 'fas fa-chart-line', 'color' => '#6366f1', 'text' => "Start getting bookings to unlock personalised AI insights.", 'type' => 'neutral'];
        }

        return $insights;
    }

    public function getChartData(PDO $db, int $providerId): array
    {
        $labels = [];
        $views = [];
        $clicks = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('D', strtotime($date));
            $views[] = $this->fetchCount($db, "SELECT COUNT(*) FROM provider_views WHERE provider_id = ? AND DATE(viewed_at) = ?", [$providerId, $date]);
            $clicks[] = $this->fetchCount($db, "SELECT COUNT(*) FROM click_logs WHERE target_type = 'provider' AND target_id = ? AND DATE(created_at) = ?", [$providerId, $date]);
        }

        return ['labels' => $labels, 'views' => $views, 'clicks' => $clicks];
    }

    public function buildRankFactors(array $provider, array $stats, ?float $avgResponseHours): array
    {
        $rankFactors = [];

        if ((float) ($provider['average_rating'] ?? 0) >= 4.0) {
            $rankFactors[] = ['icon' => 'fas fa-star', 'text' => "High rating ({$provider['average_rating']}★) boosts your visibility", 'good' => true];
        }
        if ($avgResponseHours !== null && $avgResponseHours <= 3) {
            $rankFactors[] = ['icon' => 'fas fa-bolt', 'text' => "Fast {$avgResponseHours}h response time puts you ahead", 'good' => true];
        }
        if (($stats['completed_bookings'] ?? 0) >= 5) {
            $rankFactors[] = ['icon' => 'fas fa-briefcase', 'text' => "{$stats['completed_bookings']} completed jobs builds trust signals", 'good' => true];
        }
        if ((float) ($provider['average_rating'] ?? 0) < 4.0) {
            $rankFactors[] = ['icon' => 'fas fa-star-half-alt', 'text' => "Improve rating to rank higher in search", 'good' => false];
        }
        if ($avgResponseHours === null || $avgResponseHours > 6) {
            $rankFactors[] = ['icon' => 'fas fa-clock', 'text' => "Reply faster to boost your search ranking", 'good' => false];
        }

        if (empty($rankFactors)) {
            $rankFactors[] = ['icon' => 'fas fa-info-circle', 'text' => "Complete more bookings to improve ranking", 'good' => false];
        }

        return $rankFactors;
    }

    public function buildSuggestions(PDO $db, array $provider, array $stats, ?float $avgResponseHours): array
    {
        $suggestions = [];

        $portfolioCount = $this->fetchCount($db, "SELECT COUNT(*) FROM portfolio_images WHERE provider_id = ? AND is_active = 1", [(int) ($provider['id'] ?? 0)]);
        if ($portfolioCount === 0) {
            $suggestions[] = ['icon' => 'fas fa-images', 'text' => 'Add portfolio images to attract more clients', 'priority' => 'high'];
        }
        if ((float) ($provider['average_rating'] ?? 0) < 4.0 && ($stats['total_reviews'] ?? 0) > 2) {
            $suggestions[] = ['icon' => 'fas fa-star', 'text' => 'Ask satisfied clients to leave a review', 'priority' => 'high'];
        }
        if ($avgResponseHours !== null && $avgResponseHours > 4) {
            $suggestions[] = ['icon' => 'fas fa-reply', 'text' => 'Enable notifications to respond faster', 'priority' => 'medium'];
        }
        if (empty($provider['bio'])) {
            $suggestions[] = ['icon' => 'fas fa-user-edit', 'text' => 'Write a bio to build client trust', 'priority' => 'medium'];
        }

        return $suggestions;
    }

    public function getRecentBookings(PDO $db, int $providerId): array
    {
        try {
            $stmt = $db->prepare("\n            SELECT b.*, u.full_name as client_name, s.name as service_name,\n                   DATE_FORMAT(b.preferred_date, '%b %d') as fmt_date\n            FROM bookings b JOIN users u ON b.client_id = u.id\n            LEFT JOIN provider_services s ON b.service_id = s.id\n            WHERE b.provider_id = ? ORDER BY b.created_at DESC LIMIT 5\n        ");
            $stmt->execute([$providerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getTodayBookings(PDO $db, int $providerId): int
    {
        return $this->fetchCount($db, "SELECT COUNT(*) FROM bookings WHERE provider_id = ? AND DATE(preferred_date) = CURDATE() AND status IN ('confirmed','pending')", [$providerId]);
    }

    public function getServiceCount(PDO $db, int $providerId): int
    {
        return $this->fetchCount($db, "SELECT COUNT(*) FROM provider_services WHERE provider_id = ? AND is_available = 1", [$providerId]);
    }

    public function getPhotoCount(PDO $db, int $providerId): int
    {
        return $this->fetchCount($db, "SELECT COUNT(*) FROM portfolio_images WHERE provider_id = ? AND is_active = 1", [$providerId]);
    }

    public function buildUpgradeSuggestions(array $provider, array $planFeatures, int $serviceCount, int $photoCount): array
    {
        $suggestions = [];
        if ($planFeatures['service_limit'] > 0 && $serviceCount >= $planFeatures['service_limit']) {
            $suggestions[] = ['icon' => 'fas fa-plus-circle', 'text' => 'Upgrade to add more services', 'priority' => 'high'];
        }
        if ($planFeatures['photo_limit'] > 0 && $photoCount >= $planFeatures['photo_limit']) {
            $suggestions[] = ['icon' => 'fas fa-images', 'text' => 'Upgrade to add more photos', 'priority' => 'high'];
        }
        if (empty($planFeatures['ai_enabled'])) {
            $suggestions[] = ['icon' => 'fas fa-robot', 'text' => 'Upgrade to unlock AI tools', 'priority' => 'medium'];
        }
        if (($planFeatures['analytics_level'] ?? 'basic') === 'basic') {
            $suggestions[] = ['icon' => 'fas fa-chart-bar', 'text' => 'Upgrade for better analytics', 'priority' => 'medium'];
        }

        return $suggestions;
    }

    public function getFunnelMetrics(PDO $db, int $providerId, int $providerUserId): array
    {
        return [
            'views' => $this->fetchCount($db, 'SELECT COUNT(*) FROM provider_views WHERE provider_id = ?', [$providerId]),
            'clicks' => $this->fetchCount($db, "SELECT COUNT(*) FROM click_logs WHERE target_type = 'provider' AND target_id = ?", [$providerId]),
            'messages' => $this->fetchCount($db, 'SELECT COUNT(*) FROM messages WHERE receiver_id = ?', [$providerUserId]),
        ];
    }

    public function getViewsGrowth(PDO $db, int $providerId): int
    {
        $viewsThisWeek = $this->fetchCount($db, 'SELECT COUNT(*) FROM provider_views WHERE provider_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$providerId]);
        $viewsLastWeek = $this->fetchCount($db, 'SELECT COUNT(*) FROM provider_views WHERE provider_id = ? AND viewed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)', [$providerId]);

        if ($viewsLastWeek > 0) {
            return (int) round((($viewsThisWeek - $viewsLastWeek) / $viewsLastWeek) * 100);
        }

        return $viewsThisWeek > 0 ? 100 : 0;
    }

    private function fetchCount(PDO $db, string $query, array $params = []): int
    {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function fetchValue(PDO $db, string $query, array $params = []): mixed
    {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
