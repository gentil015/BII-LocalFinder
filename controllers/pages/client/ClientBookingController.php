<?php

require_once __DIR__ . '/../../../services/client/ClientBookingService.php';

class ClientBookingController
{
    private ClientBookingService $service;

    public function __construct(?ClientBookingService $service = null)
    {
        $this->service = $service ?? new ClientBookingService();
    }

    public function index(PDO $db, int $providerId, int $serviceId = 0, ?int $shareId = null): array
    {
        return $this->service->buildViewModel($db, $providerId, $serviceId, $shareId);
    }

    public function handleSubmit(PDO $db, int $providerId, int $serviceId, ?int $shareId, array $post, array $session, array $provider, array $services, array $schedule, array $workingDays, array $timeOffPeriods, array $availabilityExceptions): array
    {
        return $this->service->submitBooking(
            $db,
            $providerId,
            $serviceId,
            $shareId,
            $post,
            $session,
            $provider,
            $services,
            $schedule,
            $workingDays,
            $timeOffPeriods,
            $availabilityExceptions
        );
    }
}
