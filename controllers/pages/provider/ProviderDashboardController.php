<?php

require_once __DIR__ . '/../../../services/provider/ProviderDashboardService.php';

class ProviderDashboardController
{
    private ProviderDashboardService $service;

    public function __construct(?ProviderDashboardService $service = null)
    {
        $this->service = $service ?? new ProviderDashboardService();
    }

    public function index(PDO $db, int $userId): array
    {
        return $this->service->buildViewModel($db, $userId);
    }
}
