<?php

require_once __DIR__ . '/../../repositories/admin/AdminDashboardRepository.php';

class AdminDashboardService
{
    private AdminDashboardRepository $repository;

    public function __construct(?AdminDashboardRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminDashboardRepository();
    }

    public function buildViewModel(PDO $db): array
    {
        $platformSettings = $this->repository->getPlatformSettings($db);

        return [
            'platform_settings' => $platformSettings,
            'stats' => $this->repository->getOverviewStats($db),
            'recent_users' => $this->repository->getRecentUsers($db),
            'recent_bookings' => $this->repository->getRecentBookings($db),
            'top_providers' => $this->repository->getTopProviders($db),
            'system_health' => $this->repository->getSystemHealth($db),
            'growth' => [
                'users' => $this->repository->calculateGrowth($db, 'users', 'created_at'),
                'bookings' => $this->repository->calculateGrowth($db, 'bookings', 'created_at'),
                'revenue' => 0,
            ],
        ];
    }

    public function handlePostAction(PDO $db, array $postData): array
    {
        $success = '';
        $errors = [];

        if (!empty($postData['toggle_maintenance'])) {
            $currentMode = (string) ($postData['current_mode'] ?? '0');
            $newMode = $currentMode === '1' ? '0' : '1';
            try {
                $this->repository->setSystemSetting($db, 'maintenance_mode', $newMode);
                $success = 'Maintenance mode ' . ($newMode === '1' ? 'enabled' : 'disabled');
            } catch (Throwable $e) {
                $errors[] = 'Failed to update maintenance mode';
            }
        }

        if (!empty($postData['clear_cache'])) {
            try {
                $this->repository->clearCacheFiles(dirname(__DIR__, 2) . '/cache');
                $success = 'Cache cleared successfully';
            } catch (Throwable $e) {
                $errors[] = 'Failed to clear cache';
            }
        }

        $platformSettings = $this->repository->getPlatformSettings($db);

        return [
            'success' => $success,
            'errors' => $errors,
            'platform_settings' => $platformSettings,
        ];
    }
}
