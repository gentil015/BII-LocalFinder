<?php

require_once __DIR__ . '/../../repositories/provider/ProviderDashboardRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/subscription_access.php';

class ProviderDashboardService
{
    private ProviderDashboardRepository $repository;

    public function __construct(?ProviderDashboardRepository $repository = null)
    {
        $this->repository = $repository ?? new ProviderDashboardRepository();
    }

    public function buildViewModel(PDO $db, int $userId): array
    {
        $provider = $this->repository->getProviderByUserId($db, $userId);
        $pid = (int) ($provider['id'] ?? 0);
        $providerAiEnabled = $provider ? isProviderAIEnabled($userId) : false;

        $stats = $this->repository->getProviderStats($db, $pid);
        $mlData = $this->repository->calculateMlScore($db, $provider, $stats);
        $insights = $this->repository->buildInsights($db, $pid, $provider, $stats, $mlData['total_views']);
        $chartData = $this->repository->getChartData($db, $pid);
        $rankFactors = $this->repository->buildRankFactors($provider, $stats, $mlData['avg_response_hours']);
        $suggestions = $this->repository->buildSuggestions($db, $provider, $stats, $mlData['avg_response_hours']);

        $recentBookings = $this->repository->getRecentBookings($db, $pid);
        $todayBookings = $this->repository->getTodayBookings($db, $pid);

        $planFeatures = getPlanFeatures($userId);
        $serviceCount = $this->repository->getServiceCount($db, $pid);
        $photoCount = $this->repository->getPhotoCount($db, $pid);

        $serviceUsagePct = $planFeatures['service_limit'] > 0
            ? min(100, round(($serviceCount / $planFeatures['service_limit']) * 100))
            : 0;
        $serviceUsageText = $planFeatures['service_limit'] === 0 ? 'Unlimited' : "{$serviceCount}/{$planFeatures['service_limit']}";

        $photoUsagePct = $planFeatures['photo_limit'] > 0
            ? min(100, round(($photoCount / $planFeatures['photo_limit']) * 100))
            : 0;
        $photoUsageText = $planFeatures['photo_limit'] === 0 ? 'Unlimited' : "{$photoCount}/{$planFeatures['photo_limit']}";

        $funnel = $this->repository->getFunnelMetrics($db, $pid, (int) ($provider['user_id'] ?? 0));
        $viewsGrowth = $this->repository->getViewsGrowth($db, $pid);
        $conversionRate = $stats['total_views'] > 0 ? round((($stats['completed_bookings'] ?? 0) / $stats['total_views']) * 100, 1) : 0;

        $notifications = getNotifications($userId, ['limit' => 8]);
        $unreadCount = getUnreadNotificationCount($userId);
        $levelData = $this->buildProviderLevel($stats, $provider);

        return [
            'provider' => $provider,
            'provider_ai_enabled' => $providerAiEnabled,
            'stats' => $stats,
            'ml_score' => $mlData['ml_score'],
            'ml_api_healthy' => $mlData['ml_api_healthy'],
            'insights' => $insights,
            'chart' => $chartData,
            'rank_factors' => $rankFactors,
            'suggestions' => $suggestions,
            'recent_bookings' => $recentBookings,
            'today_bookings' => $todayBookings,
            'plan_features' => $planFeatures,
            'service_count' => $serviceCount,
            'photo_count' => $photoCount,
            'service_usage_pct' => $serviceUsagePct,
            'service_usage_text' => $serviceUsageText,
            'photo_usage_pct' => $photoUsagePct,
            'photo_usage_text' => $photoUsageText,
            'upgrade_suggestions' => $this->repository->buildUpgradeSuggestions($provider, $planFeatures, $serviceCount, $photoCount),
            'views_growth' => $viewsGrowth,
            'funnel_views' => $funnel['views'],
            'funnel_clicks' => $funnel['clicks'],
            'funnel_messages' => $funnel['messages'],
            'funnel_hires' => (int) ($stats['completed_bookings'] ?? 0),
            'conversion_rate' => $conversionRate,
            'all_notifications' => $notifications,
            'unread_count' => $unreadCount,
            'level_label' => $levelData['label'],
            'level_next' => $levelData['next'],
            'level_icon' => $levelData['icon'],
            'level_color' => $levelData['color'],
            'level_progress' => $levelData['progress'],
            'views_growth' => $viewsGrowth,
            'funnel_views' => $funnel['views'],
            'funnel_clicks' => $funnel['clicks'],
            'funnel_messages' => $funnel['messages'],
            'funnel_hires' => (int) ($stats['completed_bookings'] ?? 0),
            'conversion_rate' => $conversionRate,
            'all_notifications' => $notifications,
            'unread_count' => $unreadCount,
            'level_label' => $levelData['label'],
            'level_next' => $levelData['next'],
            'level_icon' => $levelData['icon'],
            'level_color' => $levelData['color'],
            'level_progress' => $levelData['progress'],
        ];
    }

    private function buildProviderLevel(array $stats, array $provider): array
    {
        $completedBookings = (int) ($stats['completed_bookings'] ?? 0);
        $rating = (float) ($provider['average_rating'] ?? 0);
        $level = 'bronze';
        $label = 'Bronze';
        $next = 'Silver';
        $icon = 'fas fa-medal';
        $color = '#cd7f32';

        if ($completedBookings >= 10 && $rating >= 4.0) {
            $level = 'gold';
            $label = 'Gold';
            $next = 'Platinum';
            $icon = 'fas fa-trophy';
            $color = '#f59e0b';
        } elseif ($completedBookings >= 3 && $rating >= 3.5) {
            $level = 'silver';
            $label = 'Silver';
            $next = 'Gold';
            $icon = 'fas fa-award';
            $color = '#6b7280';
        }

        $progress = min(100, $level === 'bronze' ? ($completedBookings / 3) * 100 : ($level === 'silver' ? ($completedBookings / 10) * 100 : 100));

        return [
            'label' => $label,
            'next' => $next,
            'icon' => $icon,
            'color' => $color,
            'progress' => $progress,
        ];
    }
}
