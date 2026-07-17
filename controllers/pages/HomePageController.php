<?php

require_once __DIR__ . '/../../services/HomePageService.php';

class HomePageController
{
    private HomePageService $service;

    public function __construct(?HomePageService $service = null)
    {
        $this->service = $service ?? new HomePageService();
    }

    public function index(PDO $db): array
    {
        return $this->service->buildViewModel($db);
    }
}
