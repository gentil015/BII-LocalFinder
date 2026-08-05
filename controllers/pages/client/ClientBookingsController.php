<?php

require_once __DIR__ . '/../../../services/client/ClientBookingsService.php';

class ClientBookingsController
{
    private ClientBookingsService $service;

    public function __construct(?ClientBookingsService $service = null)
    {
        $this->service = $service ?? new ClientBookingsService();
    }

    public function index(PDO $db, int $userId, array $filters): array
    {
        return $this->service->buildViewModel($db, $userId, $filters);
    }

    public function handlePost(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        return $this->service->handlePost($db, $userId, $post, $systemSettings);
    }
}
