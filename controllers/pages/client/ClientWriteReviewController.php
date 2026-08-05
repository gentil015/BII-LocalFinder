<?php

require_once __DIR__ . '/../../../services/client/ClientWriteReviewService.php';

class ClientWriteReviewController
{
    private ClientWriteReviewService $service;

    public function __construct(?ClientWriteReviewService $service = null)
    {
        $this->service = $service ?? new ClientWriteReviewService();
    }

    public function index(PDO $db, int $userId, int $providerId, int $bookingId): array
    {
        return $this->service->buildViewModel($db, $userId, $providerId, $bookingId);
    }

    public function handlePost(PDO $db, int $userId, array $post, array $systemSettings): array
    {
        return $this->service->handleReviewSubmission($db, $userId, $post, $systemSettings);
    }
}
