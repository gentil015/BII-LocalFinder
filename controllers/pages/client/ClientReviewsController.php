<?php

require_once __DIR__ . '/../../../services/client/ClientReviewsService.php';

class ClientReviewsController
{
    private ClientReviewsService $service;

    public function __construct(?ClientReviewsService $service = null)
    {
        $this->service = $service ?? new ClientReviewsService();
    }

    public function index(PDO $db, int $userId, int $ratingFilter, string $sort): array
    {
        return $this->service->buildViewModel($db, $userId, $ratingFilter, $sort);
    }

    public function handlePost(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        return $this->service->handlePost($db, $userId, $post, $systemSettings);
    }
}
