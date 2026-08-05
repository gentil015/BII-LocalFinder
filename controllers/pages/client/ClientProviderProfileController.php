<?php

require_once __DIR__ . '/../../../services/client/ClientProviderProfileService.php';

class ClientProviderProfileController
{
    private ClientProviderProfileService $service;

    public function __construct(?ClientProviderProfileService $service = null)
    {
        $this->service = $service ?? new ClientProviderProfileService();
    }

    public function index(PDO $db, int $providerId, ?int $shareId = null, ?int $userId = null): array
    {
        return $this->service->buildViewModel($db, $providerId, $shareId, $userId);
    }
}
