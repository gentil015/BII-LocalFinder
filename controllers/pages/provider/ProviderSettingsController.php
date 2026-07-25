<?php

require_once __DIR__ . '/../../../services/provider/ProviderSettingsService.php';

class ProviderSettingsController
{
    private ProviderSettingsService $service;

    public function __construct(?ProviderSettingsService $service = null)
    {
        $this->service = $service ?? new ProviderSettingsService();
    }

    public function index(PDO $db, int $userId, string $section = 'identity'): array
    {
        return $this->service->buildViewModel($db, $userId, $section);
    }
}
