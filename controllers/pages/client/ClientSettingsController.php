<?php

require_once __DIR__ . '/../../../services/client/ClientSettingsService.php';

class ClientSettingsController
{
    private ClientSettingsService $service;

    public function __construct(?ClientSettingsService $service = null)
    {
        $this->service = $service ?? new ClientSettingsService();
    }

    public function index(PDO $db, int $userId, string $section = 'account'): array
    {
        return $this->service->buildViewModel($db, $userId, $section);
    }
}
