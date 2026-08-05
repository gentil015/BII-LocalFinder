<?php

require_once __DIR__ . '/../../../services/client/ClientBookingDetailsService.php';

class ClientBookingDetailsController
{
    private ClientBookingDetailsService $service;

    public function __construct(?ClientBookingDetailsService $service = null)
    {
        $this->service = $service ?? new ClientBookingDetailsService();
    }

    public function index(PDO $db, int $userId, int $bookingId, $paymentManager): array
    {
        return $this->service->buildViewModel($db, $userId, $bookingId, $paymentManager);
    }
}
