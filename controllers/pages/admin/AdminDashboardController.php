<?php

require_once __DIR__ . '/../../../services/admin/AdminDashboardService.php';

class AdminDashboardController
{
    private AdminDashboardService $service;

    public function __construct(?AdminDashboardService $service = null)
    {
        $this->service = $service ?? new AdminDashboardService();
    }

    public function index(PDO $db): array
    {
        return $this->service->buildViewModel($db);
    }

    public function handlePostAction(PDO $db, array $postData): array
    {
        return $this->service->handlePostAction($db, $postData);
    }
}
