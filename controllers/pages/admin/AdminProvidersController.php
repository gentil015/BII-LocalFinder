<?php

require_once __DIR__ . '/../../../services/providers/AdminProvidersService.php';

class AdminProvidersController
{
    private AdminProvidersService $service;

    public function __construct(?AdminProvidersService $service = null)
    {
        $this->service = $service ?? new AdminProvidersService();
    }

    public function index(PDO $db, array $filters): array
    {
        return $this->service->buildViewModel($db, $filters);
    }

    public function show(PDO $db, int $providerId): array
    {
        return $this->service->getProviderDetailViewModel($db, $providerId);
    }

    public function handlePostAction(PDO $db, array $postData): array
    {
        return $this->service->handlePostAction($db, $postData);
    }
}
