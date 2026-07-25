<?php

require_once __DIR__ . '/../../../services/provider/ProviderProfileService.php';

class ProviderProfileController
{
    private ProviderProfileService $service;

    public function __construct(?ProviderProfileService $service = null)
    {
        $this->service = $service ?? new ProviderProfileService();
    }

    public function index(PDO $db, int $userId, string $section): array
    {
        return $this->service->buildViewModel($db, $userId, $section);
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        return $this->service->handleSubmit($db, $userId, $post, $files, $server);
    }

    public function handleAjaxSection(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        return $this->service->handleAjaxSection($db, $userId, $post, $files, $server);
    }
}
