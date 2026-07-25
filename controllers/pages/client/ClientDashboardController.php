<?php

require_once __DIR__ . '/../../../services/client/ClientDashboardService.php';

class ClientDashboardController
{
    private ClientDashboardService $service;

    public function __construct(?ClientDashboardService $service = null)
    {
        $this->service = $service ?? new ClientDashboardService();
    }

    public function index(PDO $db, int $userId, array $query): array
    {
        return $this->service->buildViewModel($db, $userId, $query);
    }
}
