<?php

require_once __DIR__ . '/../../../services/client/ClientMessagesService.php';

class ClientMessagesController
{
    private ClientMessagesService $service;

    public function __construct(?ClientMessagesService $service = null)
    {
        $this->service = $service ?? new ClientMessagesService();
    }

    public function index(PDO $db, int $userId, array $query): array
    {
        return $this->service->buildViewModel($db, $userId, $query);
    }

    public function poll(PDO $db, int $userId, array $query): array
    {
        $with = isset($query['with']) ? intval($query['with']) : 0;
        $bookingId = isset($query['booking_id']) ? intval($query['booking_id']) : 0;
        return $this->service->getPollResponse($db, $userId, $with, $bookingId);
    }

    public function handleSubmit(PDO $db, int $userId, array $post, array $files, array $server): array
    {
        return $this->service->handleSubmit($db, $userId, $post, $files, $server);
    }
}
