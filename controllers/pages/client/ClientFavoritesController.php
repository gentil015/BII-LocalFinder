<?php

require_once __DIR__ . '/../../../services/client/ClientFavoritesService.php';

class ClientFavoritesController
{
    private ClientFavoritesService $service;

    public function __construct(?ClientFavoritesService $service = null)
    {
        $this->service = $service ?? new ClientFavoritesService();
    }

    public function index(PDO $db, int $userId): array
    {
        return $this->service->buildViewModel($db, $userId);
    }

    public function handlePost(PDO $db, int $userId, array $post): array
    {
        return $this->service->handlePost($db, $userId, $post);
    }
}
