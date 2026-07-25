<?php

require_once __DIR__ . '/../../repositories/client/ClientDashboardRepository.php';

class ClientDashboardService
{
    private ClientDashboardRepository $repository;

    public function __construct(?ClientDashboardRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientDashboardRepository();
    }

    public function buildViewModel(PDO $db, int $userId, array $query): array
    {
        $platformName = $this->repository->getPlatformSetting($db, 'platform_name', 'BII LocalFinder');
        $client = $this->repository->getClient($db, $userId);
        $clientLocation = trim($client['location'] ?? '');
        $clientName = $client['full_name'] ?? 'there';
        $firstName = explode(' ', trim($clientName))[0] ?: $clientName;

        $hour = (int) date('H');
        if ($hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour < 17) {
            $greeting = 'Good afternoon';
        } else {
            $greeting = 'Good evening';
        }

        $stats = $this->repository->getClientBookingStats($db, $userId);
        $favCount = $this->repository->getFavoritesCount($db, $userId);
        $reviewCount = $this->repository->getReviewCount($db, $userId);
        $recentBookings = $this->repository->getRecentBookings($db, $userId);
        $favoriteProviders = $this->repository->getFavoriteProviders($db, $userId);
        $recommendedProviders = $this->repository->getRecommendedProviders($db, $userId);
        $pendingReviews = $this->repository->getPendingReviews($db, $userId);
        $bookedProfessions = $this->repository->getBookedProfessions($db, $userId);

        $ajax = !empty($query['ajax']);
        if (!$ajax && function_exists('trackEvent')) {
            try {
                trackEvent('client_dashboard_view', 'page', 0, [], $userId);
            } catch (Throwable $e) {
            }
        }

        return [
            'platform_name' => $platformName,
            'client' => $client,
            'clientLocation' => $clientLocation,
            'clientName' => $clientName,
            'firstName' => $firstName,
            'greeting' => $greeting,
            'totalBookings' => (int) ($stats['total_bookings'] ?? 0),
            'completedBookings' => (int) ($stats['completed_bookings'] ?? 0),
            'pendingBookings' => (int) ($stats['pending_bookings'] ?? 0),
            'favCount' => $favCount,
            'reviewCount' => $reviewCount,
            'recentBookings' => $recentBookings,
            'favoriteProviders' => $favoriteProviders,
            'recommendedProviders' => $recommendedProviders,
            'pendingReviews' => $pendingReviews,
            'bookedProfessions' => $bookedProfessions,
            'ajax' => $ajax,
        ];
    }
}
