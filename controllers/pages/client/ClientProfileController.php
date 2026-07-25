<?php

require_once __DIR__ . '/../../../services/client/ClientProfileService.php';

class ClientProfileController
{
    private ClientProfileService $service;

    public function __construct(?ClientProfileService $service = null)
    {
        $this->service = $service ?? new ClientProfileService();
    }

    public function index(PDO $db, int $userId, array $systemSettings): array
    {
        return $this->service->buildViewModel($db, $userId, $systemSettings);
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $systemSettings): array
    {
        return $this->service->handleSubmit($db, $userId, $post, $files, $systemSettings);
    }
}
