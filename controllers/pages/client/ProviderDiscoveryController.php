<?php

require_once __DIR__ . '/../../../services/providers/ProviderDiscoveryService.php';

class ProviderDiscoveryController
{
    private ProviderDiscoveryService $service;

    public function __construct(?ProviderDiscoveryService $service = null)
    {
        $this->service = $service ?? new ProviderDiscoveryService();
    }

    /**
     * @param array $context optional overrides: ['location' => ..., 'district' => ...]
     */
    public function index(PDO $db, int $userId, array $context = []): array
    {
        return $this->service->buildDiscoveryPage($db, $userId, $context);
    }
}
