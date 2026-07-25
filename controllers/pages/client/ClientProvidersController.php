<?php

require_once __DIR__ . '/../../../services/providers/ClientProvidersService.php';

class ClientProvidersController
{
    private ClientProvidersService $service;

    public function __construct(?ClientProvidersService $service = null)
    {
        $this->service = $service ?? new ClientProvidersService();
    }

    public function index(PDO $db, int $userId, array $filters): array
    {
        return $this->service->buildViewModel($db, $userId, $filters);
    }
}
